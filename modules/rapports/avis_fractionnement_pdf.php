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
require_once "../core/functions.php";
require_once "../lib/fpdf/fpdf.php";
require_once "../lib/phpqrcode/qrlib.php";
require_once "../core/secure_qr_engine.php";

checkAuth();

function cpPublicBaseUrlPDF()
{
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $https ? 'https' : 'http';

    if (
        stripos($host, 'collectpay.flyflash-systems.com') !== false ||
        stripos($host, 'flyflash-systems.com') !== false
    ) {
        return $scheme . '://' . $host;
    }

    return $scheme . '://' . $host . '/collect_pay';
}

$QR_VERIFY_BASE_URL = cpPublicBaseUrlPDF() . "/verify.php";

$numero = $_GET['numero'] ?? null;

if (!$numero) {
    die("Numéro avis manquant.");
}

$stmt = $pdo->prepare("
    SELECT 
        av.*,
        np.numero_np AS numero_np_mere,
        np.montant_initial,
        np.solde_restant,
        nd.numero_nd,
        nt.numero_nt,
        nt.exercice,
        c.type_personne,
        c.raison_sociale,
        c.nom,
        c.postnom,
        c.prenom,
        c.nif,
        c.rccm,
        c.telephone,
        c.adresse,
        c.ville,
        u.nom AS nom_directeur
    FROM avis_fractionnement av
    JOIN notes_perception np ON av.note_perception_id = np.id
    JOIN notes_debit nd ON np.note_debit_id = nd.id
    JOIN notes_taxation nt ON nd.note_taxation_id = nt.id
    JOIN contribuables c ON nt.contribuable_id = c.id
    LEFT JOIN users u ON av.user_directeur_recouvrement_id = u.id
    WHERE av.numero_avis = ?
    LIMIT 1
");
$stmt->execute([$numero]);
$avis = $stmt->fetch();

if (!$avis) {
    die("Avis de fractionnement introuvable.");
}

$stmt = $pdo->prepare("
    SELECT 
        numero_np,
        numero_tranche,
        montant_initial,
        montant_paye,
        solde_restant,
        date_echeance,
        statut
    FROM notes_perception
    WHERE avis_fractionnement_id = ?
    AND type_np = 'fractionnee'
    ORDER BY numero_tranche ASC
");
$stmt->execute([$avis['id']]);
$tranches = $stmt->fetchAll();

function cleanFileNameAvis($text)
{
    return preg_replace('/[^A-Za-z0-9_\-]/', '_', $text);
}

function nomContribuableAvisPDF($c)
{
    if (!empty($c['raison_sociale'])) {
        return $c['raison_sociale'];
    }

    return trim(($c['nom'] ?? '') . ' ' . ($c['postnom'] ?? '') . ' ' . ($c['prenom'] ?? ''));
}

function formatDateAvisPDF($date)
{
    if (!$date) {
        return '-';
    }

    return date('d/m/Y H:i:s', strtotime($date));
}

$montantAvis = (float)($avis['montant_total'] ?? $avis['solde_restant'] ?? 0);

$mentionDuplicata = enregistrerImpressionDocument(
    $pdo,
    'AVF',
    $avis['numero_avis']
);

$token = getOrCreateDocumentToken(
    $pdo,
    'AVF',
    $avis['numero_avis'],
    $montantAvis
);

$verifyUrl = $QR_VERIFY_BASE_URL . "?t=" . urlencode($token);

$GLOBALS['qrMatrix'] = QRcode::text(
    $verifyUrl,
    false,
    QR_ECLEVEL_L,
    1,
    1
);

class AvisFractionnementPDF extends FPDF
{
    function DrawQRCode($matrix, $x, $y, $size = 0.63)
    {
        if (!is_array($matrix)) return;

        $this->SetFillColor(0, 0, 0);

        foreach ($matrix as $rowIndex => $row) {
            for ($colIndex = 0; $colIndex < strlen($row); $colIndex++) {
                if ($row[$colIndex] === '1') {
                    $this->Rect(
                        $x + $colIndex * $size,
                        $y + $rowIndex * $size,
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

        $this->DrawQRCode($GLOBALS['qrMatrix'] ?? [], 174.8, 8.8, 0.63);

        $this->SetFont('Arial', '', 5);
        $this->SetXY(171, 34);
        $this->Cell(34, 3, pdfTxt('Vérification sécurisée'), 0, 0, 'C');

        $this->SetY(9);
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(0, 5, pdfTxt('REPUBLIQUE DEMOCRATIQUE DU CONGO'), 0, 1, 'C');
        $this->Cell(0, 5, pdfTxt('PROVINCE DE LA TSHOPO'), 0, 1, 'C');

        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 5, pdfTxt('DIRECTION GENERALE DES RECETTES DE LA TSHOPO'), 0, 1, 'C');
        $this->Cell(0, 5, pdfTxt('DIRECTION DE RECOUVREMENT'), 0, 1, 'C');

        $this->Ln(13);
    }

    function Footer()
    {
        $this->SetY(-12);
        $this->SetFont('Arial', '', 8);
        $this->Cell(0, 5, pdfTxt('Copyright 2026 - cOllect_Pay, Tout droit réservé'), 0, 0, 'C');
    }

    function SectionTitle($title)
    {
        $this->SetFillColor(230, 230, 230);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(0, 7, pdfTxt($title), 1, 1, 'L', true);
    }
}
$pdf = new AvisFractionnementPDF('P', 'mm', 'A4');
$pdf->AddPage();

$pdf->SetFont('Arial', 'B', 13);
$pdf->Cell(0, 8, pdfTxt('AVIS DE FRACTIONNEMENT'), 0, 1, 'C');

$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 7, pdfTxt('N° ' . $avis['numero_avis']), 0, 1, 'C');

if (!empty($mentionDuplicata)) {
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->SetTextColor(220, 38, 38);
    $pdf->Cell(0, 8, pdfTxt($mentionDuplicata), 0, 1, 'C');
    $pdf->SetTextColor(0, 0, 0);
}

$pdf->Ln(3);

$pdf->SectionTitle('I. IDENTIFICATION DE L ASSUJETTI');

$pdf->SetFont('Arial', '', 9);
$pdf->Cell(55, 6, pdfTxt('Nom / Raison sociale'), 1);
$pdf->Cell(135, 6, pdfTxt(nomContribuableAvisPDF($avis)), 1, 1);

$pdf->Cell(55, 6, pdfTxt('NIF'), 1);
$pdf->Cell(135, 6, pdfTxt($avis['nif'] ?? '-'), 1, 1);

$pdf->Cell(55, 6, pdfTxt('Téléphone'), 1);
$pdf->Cell(135, 6, pdfTxt($avis['telephone'] ?? '-'), 1, 1);

$pdf->Cell(55, 6, pdfTxt('Adresse'), 1);
$pdf->Cell(135, 6, pdfTxt(($avis['ville'] ?? '') . ' - ' . ($avis['adresse'] ?? '-')), 1, 1);

$pdf->Ln(3);

$pdf->SectionTitle('II. REFERENCES');

$pdf->Cell(65, 6, pdfTxt('NP mère'), 1);
$pdf->Cell(125, 6, pdfTxt($avis['numero_np_mere']), 1, 1);

$pdf->Cell(65, 6, pdfTxt('Note de Débit'), 1);
$pdf->Cell(125, 6, pdfTxt($avis['numero_nd']), 1, 1);

$pdf->Cell(65, 6, pdfTxt('Note de Taxation'), 1);
$pdf->Cell(125, 6, pdfTxt($avis['numero_nt']), 1, 1);

$pdf->Cell(65, 6, pdfTxt('Exercice'), 1);
$pdf->Cell(125, 6, pdfTxt($avis['exercice'] ?? date('Y')), 1, 1);

$pdf->Ln(3);

$pdf->SectionTitle('III. DECISION DE L AUTORITE');

$pdf->Cell(65, 6, pdfTxt('Autorité'), 1);
$pdf->Cell(125, 6, pdfTxt($avis['autorite_type'] ?? '-'), 1, 1);

$pdf->Cell(65, 6, pdfTxt('Nom autorité'), 1);
$pdf->Cell(125, 6, pdfTxt($avis['autorite_nom'] ?? '-'), 1, 1);

$pdf->Cell(65, 6, pdfTxt('Directeur recouvrement'), 1);
$pdf->Cell(125, 6, pdfTxt($avis['nom_directeur'] ?? 'SYSTEME'), 1, 1);

$pdf->Cell(65, 6, pdfTxt('Nombre de tranches'), 1);
$pdf->Cell(125, 6, pdfTxt($avis['nombre_tranches'] ?? '-'), 1, 1);

$pdf->Cell(65, 6, pdfTxt('Montant total accordé'), 1);
$pdf->Cell(125, 6, number_format($avis['montant_total'] ?? 0, 2, ',', ' ') . ' CDF', 1, 1, 'R');

$pdf->Cell(65, 6, pdfTxt('Statut'), 1);
$pdf->Cell(125, 6, pdfTxt(strtoupper($avis['statut'] ?? '-')), 1, 1);

$pdf->Cell(65, 6, pdfTxt('Annotation'), 1);
$pdf->MultiCell(125, 6, pdfTxt($avis['annotation'] ?? '-'), 1);

$pdf->Ln(3);

$pdf->SectionTitle('IV. TABLEAU DES TRANCHES GENEREES');

$pdf->SetFont('Arial', 'B', 7);
$pdf->Cell(55, 6, pdfTxt('N° NPF'), 1);
$pdf->Cell(20, 6, pdfTxt('Tranche'), 1);
$pdf->Cell(35, 6, pdfTxt('Montant'), 1);
$pdf->Cell(35, 6, pdfTxt('Solde'), 1);
$pdf->Cell(30, 6, pdfTxt('Echéance'), 1);
$pdf->Cell(15, 6, pdfTxt('Statut'), 1, 1);

$pdf->SetFont('Arial', '', 7);

foreach ($tranches as $t) {
    $pdf->Cell(55, 6, pdfTxt($t['numero_np']), 1);
    $pdf->Cell(20, 6, str_pad((int)$t['numero_tranche'], 3, '0', STR_PAD_LEFT), 1, 0, 'C');
    $pdf->Cell(35, 6, number_format($t['montant_initial'], 0, ',', ' ') . ' CDF', 1, 0, 'R');
    $pdf->Cell(35, 6, number_format($t['solde_restant'], 0, ',', ' ') . ' CDF', 1, 0, 'R');
    $pdf->Cell(
        30,
        6,
        pdfTxt(!empty($t['date_echeance']) ? date('d/m/Y', strtotime($t['date_echeance'])) : '-'),
        1
    );
    $pdf->Cell(15, 6, pdfTxt(strtoupper(substr($t['statut'], 0, 4))), 1, 1);
}

if (empty($tranches)) {
    $pdf->Cell(190, 7, pdfTxt('Aucune NPF générée pour cet avis.'), 1, 1, 'C');
}

$pdf->Ln(4);

$pdf->SetFont('Arial', 'B', 9);
$pdf->MultiCell(
    0,
    6,
    pdfTxt(
        "Le présent avis autorise le fractionnement de la Note de Perception indiquée ci-dessus conformément à la décision de l'autorité compétente."
    ),
    1,
    'C'
);

$pdf->Ln(4);

$pdf->SectionTitle('V. SIGNATURE ET SCEAU');

$pdf->SetFont('Arial', '', 9);
$pdf->Cell(90, 28, pdfTxt('Directeur de Recouvrement'), 1, 0, 'C');
$pdf->Cell(10, 28, '', 0, 0);
$pdf->Cell(90, 28, pdfTxt('Sceau Officiel'), 1, 1, 'C');

$pdf->Ln(4);
$pdf->SetFont('Arial', '', 8);
$pdf->Cell(0, 5, pdfTxt('Délivré le ' . date('d/m/Y H:i:s')), 0, 1);
$pdf->Cell(0, 5, pdfTxt('Document vérifié par QR Code sécurisé'), 0, 1);

cpCleanOutputBeforePdf();
$pdf->Output(
    'I',
    'AVIS_FRACTIONNEMENT_' . cleanFileNameAvis($avis['numero_avis']) . '.pdf'
);

exit;