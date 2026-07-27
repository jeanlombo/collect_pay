<?php
ob_start();
ini_set("display_errors", "0");
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);

/*
|--------------------------------------------------------------------------
| Compatibilité PHP 8.2 / 8.3 pour FPDF
|--------------------------------------------------------------------------
*/
if (!function_exists('pdfTxt')) {
    function pdfTxt($text)
    {
        $text = (string)($text ?? '');

        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $text);
            if ($converted !== false) {
                return $converted;
            }
        }

        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');
        }

        return $text;
    }
}

if (!function_exists('cpCleanOutputBeforePdf')) {
    function cpCleanOutputBeforePdf()
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }
}

require_once "../config/database.php";
require_once "../config/security.php";
require_once "../config/app.php";
require_once "../lib/fpdf/fpdf.php";
require_once "../lib/phpqrcode/qrlib.php";
require_once "../core/secure_qr_engine.php";

checkAuth();

$numero = $_GET['numero'] ?? null;

if (!$numero) {
    die("Numéro AMR obligatoire.");
}

$stmt = $pdo->prepare("
    SELECT 
        amr.*,
        np.numero_np,
        np.type_np,
        np.date_echeance,
        nd.numero_nd,
        nt.numero_nt,
        c.raison_sociale,
        c.nom,
        c.postnom,
        c.prenom,
        c.nif,
        c.telephone,
        c.adresse,
        c.ville,
        ue.nom AS nom_emetteur,
        uv.nom AS nom_validateur
    FROM amr
    JOIN notes_perception np ON amr.note_perception_id = np.id
    JOIN notes_debit nd ON np.note_debit_id = nd.id
    JOIN notes_taxation nt ON nd.note_taxation_id = nt.id
    JOIN contribuables c ON nt.contribuable_id = c.id
    LEFT JOIN users ue ON amr.user_emission_id = ue.id
    LEFT JOIN users uv ON amr.user_validation_id = uv.id
    WHERE amr.numero_amr = ?
    LIMIT 1
");
$stmt->execute([$numero]);
$amr = $stmt->fetch();

if (!$amr) {
    die("AMR introuvable.");
}

function cleanFileNameAMR($text)
{
    return preg_replace('/[^A-Za-z0-9_\-]/', '_', $text);
}

function nomContribuableAMRPDF($c)
{
    if (!empty($c['raison_sociale'])) {
        return $c['raison_sociale'];
    }

    return trim(
        ($c['nom'] ?? '') . ' ' .
        ($c['postnom'] ?? '') . ' ' .
        ($c['prenom'] ?? '')
    );
}

function formatDateAMRPDF($date)
{
    if (!$date) return '-';
    return date('d/m/Y H:i:s', strtotime($date));
}

$montantQR = (float)($amr['montant_total'] ?? 0);

$qrContent = buildEncryptedQrContent(
    $pdo,
    'AMR',
    $amr['numero_amr'],
    $montantQR
);

if (!$qrContent) {
    die("Erreur génération QR sécurisé AMR.");
}

$GLOBALS['qrMatrix'] = QRcode::text(
    $qrContent,
    false,
    QR_ECLEVEL_L,
    0,
    1
);

class AMRPDF extends FPDF
{
    function DrawQRCode($matrix, $x, $y, $size = 0.38)
    {
        if (!is_array($matrix)) return;

        $this->SetFillColor(0, 0, 0);

        foreach ($matrix as $rowIndex => $row) {
            for ($colIndex = 0; $colIndex < strlen($row); $colIndex++) {
                if ($row[$colIndex] === '1') {
                    $this->Rect(
                        $x + ($colIndex * $size),
                        $y + ($rowIndex * $size),
                        $size,
                        $size,
                        'F'
                    );
                }
            }
        }
    }

    function Header()
    {
        if (file_exists("../assets/images/logo_province.png")) {
            $this->Image("../assets/images/logo_province.png", 10, 8, 24);
        }

        $this->DrawQRCode(
            $GLOBALS['qrMatrix'] ?? [],
            172,
            8,
            0.38
        );

        $this->SetFont('Arial', '', 5);
        $this->SetXY(165, 34);
        $this->Cell(35, 3, pdfTxt('Vérification sécurisée'), 0, 0, 'C');

        $this->SetY(8);
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(0, 5, pdfTxt('REPUBLIQUE DEMOCRATIQUE DU CONGO'), 0, 1, 'C');
        $this->Cell(0, 5, pdfTxt('PROVINCE DE LA TSHOPO'), 0, 1, 'C');

        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 5, pdfTxt('DIRECTION GENERALE DES RECETTES DE LA TSHOPO'), 0, 1, 'C');
        $this->Cell(0, 5, pdfTxt('DIRECTION DU RECOUVREMENT'), 0, 1, 'C');

        $this->Ln(12);
    }

    function Footer()
    {
        $this->SetY(-12);
        $this->SetFont('Arial', '', 8);
        $this->Cell(0, 5, pdfTxt('Document sécurisé par cOllect_Pay - QR Code vérifiable'), 0, 0, 'C');
    }

    function SectionTitle($title)
    {
        $this->SetFillColor(230, 230, 230);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(0, 7, pdfTxt($title), 1, 1, 'L', true);
    }
}
$pdf = new AMRPDF('P', 'mm', 'A4');
$pdf->SetMargins(12, 12, 12);
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 15);

/*
|--------------------------------------------------------------------------
| TITRE
|--------------------------------------------------------------------------
*/

$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(
    0,
    10,
    pdfTxt('AVIS DE MISE EN RECOUVREMENT (AMR)'),
    0,
    1,
    'C'
);

$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(
    0,
    8,
    pdfTxt('N° ' . $amr['numero_amr']),
    0,
    1,
    'C'
);

$pdf->Ln(3);

/*
|--------------------------------------------------------------------------
| REFERENCE
|--------------------------------------------------------------------------
*/

$pdf->SectionTitle('INFORMATIONS DE LA NOTE');

$pdf->SetFont('Arial', '', 10);

$pdf->Cell(
    60,
    8,
    pdfTxt('Type de document')
);
$pdf->Cell(
    120,
    8,
    pdfTxt($amr['reference_type']),
    0,
    1
);

$pdf->Cell(
    60,
    8,
    pdfTxt('Numéro NP / NPF')
);
$pdf->Cell(
    120,
    8,
    pdfTxt($amr['reference_numero']),
    0,
    1
);

$pdf->Cell(
    60,
    8,
    pdfTxt('Numéro ND')
);
$pdf->Cell(
    120,
    8,
    pdfTxt($amr['numero_nd']),
    0,
    1
);

$pdf->Cell(
    60,
    8,
    pdfTxt('Numéro NT')
);
$pdf->Cell(
    120,
    8,
    pdfTxt($amr['numero_nt']),
    0,
    1
);

$pdf->Cell(
    60,
    8,
    pdfTxt('Date échéance')
);
$pdf->Cell(
    120,
    8,
    pdfTxt(formatDateAMRPDF($amr['date_echeance'])),
    0,
    1
);

$pdf->Cell(
    60,
    8,
    pdfTxt('Jours de retard')
);
$pdf->Cell(
    120,
    8,
    pdfTxt($amr['jours_retard'] . ' jour(s)'),
    0,
    1
);

$pdf->Ln(3);

/*
|--------------------------------------------------------------------------
| CONTRIBUABLE
|--------------------------------------------------------------------------
*/

$pdf->SectionTitle('CONTRIBUABLE');

$pdf->Cell(
    60,
    8,
    pdfTxt('Nom / Raison sociale')
);
$pdf->Cell(
    120,
    8,
    pdfTxt(nomContribuableAMRPDF($amr)),
    0,
    1
);

$pdf->Cell(
    60,
    8,
    pdfTxt('NIF')
);
$pdf->Cell(
    120,
    8,
    pdfTxt($amr['nif'] ?? '-'),
    0,
    1
);

$pdf->Cell(
    60,
    8,
    pdfTxt('Téléphone')
);
$pdf->Cell(
    120,
    8,
    pdfTxt($amr['telephone'] ?? '-'),
    0,
    1
);

$pdf->Cell(
    60,
    8,
    pdfTxt('Adresse')
);
$pdf->Cell(
    120,
    8,
    pdfTxt($amr['adresse'] ?? '-'),
    0,
    1
);

$pdf->Ln(3);

/*
|--------------------------------------------------------------------------
| RECOUVREMENT
|--------------------------------------------------------------------------
*/

$pdf->SectionTitle('MONTANTS A RECOUVRER');

$pdf->SetFont('Arial', 'B', 10);

$pdf->Cell(
    90,
    10,
    pdfTxt('Montant principal'),
    1
);

$pdf->Cell(
    90,
    10,
    number_format(
        $amr['montant_principal'],
        2,
        ',',
        ' '
    ) . ' CDF',
    1,
    1,
    'R'
);

$pdf->Cell(
    90,
    10,
    pdfTxt('Pénalité de recouvrement'),
    1
);

$pdf->Cell(
    90,
    10,
    number_format(
        $amr['montant_penalite'],
        2,
        ',',
        ' '
    ) . ' CDF',
    1,
    1,
    'R'
);

$pdf->Cell(
    90,
    10,
    pdfTxt('TOTAL A RECOUVRER'),
    1
);

$pdf->Cell(
    90,
    10,
    number_format(
        $amr['montant_total'],
        2,
        ',',
        ' '
    ) . ' CDF',
    1,
    1,
    'R'
);

$pdf->Ln(8);

/*
|--------------------------------------------------------------------------
| MENTION LEGALE
|--------------------------------------------------------------------------
*/

$pdf->MultiCell(
    0,
    7,
    pdfTxt(
        "Le présent Avis de Mise en Recouvrement (AMR) est émis pour dépassement de la date d'échéance de paiement de la Note de Perception. Conformément aux procédures de recouvrement, le paiement de la note concernée demeure bloqué jusqu'à validation du présent AMR et régularisation de la situation fiscale."
    ),
    1,
    'J'
);

$pdf->Ln(15);

/*
|--------------------------------------------------------------------------
| SIGNATURES
|--------------------------------------------------------------------------
*/

$pdf->Cell(90, 8,
    pdfTxt('Agent de Recouvrement'),
    0,
    0,
    'C'
);

$pdf->Cell(90, 8,
    pdfTxt('Chef de Recouvrement'),
    0,
    1,
    'C'
);

$pdf->Ln(20);

$pdf->Cell(
    90,
    8,
    pdfTxt($amr['nom_emetteur'] ?? '________________'),
    0,
    0,
    'C'
);

$pdf->Cell(
    90,
    8,
    pdfTxt($amr['nom_validateur'] ?? '________________'),
    0,
    1,
    'C'
);

/*
|--------------------------------------------------------------------------
| SORTIE
|--------------------------------------------------------------------------
*/

$fileName =
    'AMR_' .
    cleanFileNameAMR($amr['numero_amr']) .
    '.pdf';

cpCleanOutputBeforePdf();
$pdf->Output(
    'I',
    $fileName
);
exit;
?>