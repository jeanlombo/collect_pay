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
require_once "../lib/fpdf/fpdf.php";
require_once "../lib/phpqrcode/qrlib.php";
require_once "../core/secure_qr_engine.php";

checkAuth();

$numero = $_GET['numero'] ?? null;

if (!$numero) {
    die("Numéro NPF obligatoire.");
}

$stmt = $pdo->prepare("
    SELECT 
        np.*,
        mere.numero_np AS numero_np_mere,
        nd.numero_nd,
        nt.id AS note_taxation_id,
        nt.numero_nt,
        nt.exercice,
        nt.penalite_assiette,
        nt.penalite_recouvrement,
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
        u.nom AS ordonnateur
    FROM notes_perception np
    LEFT JOIN notes_perception mere ON np.np_mere_id = mere.id
    JOIN notes_debit nd ON np.note_debit_id = nd.id
    JOIN notes_taxation nt ON nd.note_taxation_id = nt.id
    JOIN contribuables c ON nt.contribuable_id = c.id
    LEFT JOIN users u ON np.user_ordonnateur_id = u.id
    WHERE np.numero_np = ?
    LIMIT 1
");
$stmt->execute([$numero]);
$npf = $stmt->fetch();

if (!$npf) {
    die("NPF introuvable.");
}

$stmt = $pdo->prepare("
    SELECT 
        d.*,
        ab.code_article,
        ab.nature_acte
    FROM notes_taxation_details d
    LEFT JOIN articles_budgetaires ab ON d.article_id = ab.id
    WHERE d.note_taxation_id = ?
    ORDER BY d.id ASC
");
$stmt->execute([$npf['note_taxation_id']]);
$detailsNT = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT 
        nb.*,
        cb.banque,
        cb.numero_compte,
        cb.intitule_compte,
        cb.devise
    FROM note_banques nb
    JOIN comptes_bancaires cb ON nb.compte_bancaire_id = cb.id
    WHERE nb.note_perception_id = ?
       OR nb.note_perception_id = ?
    ORDER BY nb.id ASC
");
$stmt->execute([
    $npf['id'],
    $npf['np_mere_id'] ?? 0
]);
$banques = $stmt->fetchAll();

function nomContribuableNPF($c)
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

function formatDateNPF($date)
{
    if (!$date) return '-';
    return date('d/m/Y H:i:s', strtotime($date));
}

function montantNPF($value)
{
    return number_format((float)$value, 2, ',', ' ') . ' CDF';
}

function cleanFileNameNPF($text)
{
    return preg_replace('/[^A-Za-z0-9_\-]/', '_', $text);
}

function detailCalculTextNPF($json)
{
    if (empty($json)) return '-';

    $data = json_decode($json, true);

    if (!is_array($data)) {
        return $json;
    }

    $lines = [];

    if (!empty($data['periode'])) {
        $lines[] = "Période : " . ($data['periode']['libelle'] ?? '-');
        $lines[] = "Mois : " . ($data['periode']['mois'] ?? '-');
    }

    if (!empty($data['details']) && is_array($data['details'])) {
        foreach ($data['details'] as $d) {
            $lines[] =
                ($d['libelle'] ?? '-') . " : " .
                ($d['formule'] ?? '-') . " = " .
                number_format((float)($d['montant'] ?? 0), 2, ',', ' ') . " CDF";
        }
    }

    return implode("\n", $lines);
}

$montantQR = (float)($npf['solde_restant'] ?? $npf['montant_initial'] ?? 0);

$qrContent = buildEncryptedQrContent(
    $pdo,
    'NPF',
    $npf['numero_np'],
    $montantQR
);

$GLOBALS['qrMatrix'] = QRcode::text(
    $qrContent,
    false,
    QR_ECLEVEL_L,
    0,
    1
);

class NPFPDF extends FPDF
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
        $this->Cell(0, 5, pdfTxt('DIRECTION IMPOT / KISANGANI'), 0, 1, 'C');

        $this->Ln(14);
    }

    function Footer()
    {
        $this->SetY(-12);
        $this->SetFont('Arial', '', 8);
        $this->Cell(
            0,
            5,
            pdfTxt('Copyright ' . date('Y') . ' - cOllect_Pay, Tous droits réservés'),
            0,
            0,
            'C'
        );
    }

    function SectionTitle($title)
    {
        $this->SetFillColor(230, 230, 230);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(0, 7, pdfTxt($title), 1, 1, 'L', true);
    }

    function RowLabelValue($label, $value)
    {
        $this->SetFont('Arial', '', 9);
        $this->Cell(60, 7, pdfTxt($label), 1);
        $this->Cell(126, 7, pdfTxt($value), 1, 1);
    }
}

$pdf = new NPFPDF('P', 'mm', 'A4');
$pdf->SetMargins(12, 12, 12);
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 15);

$pdf->SetFont('Arial', 'B', 13);
$pdf->Cell(
    0,
    9,
    pdfTxt('NOTE DE PERCEPTION FRACTIONNÉE N° ' . $npf['numero_np']),
    0,
    1,
    'C'
);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(
    0,
    7,
    pdfTxt('EXERCICE ' . ($npf['exercice'] ?? date('Y'))),
    0,
    1,
    'C'
);

$pdf->Ln(3);

$pdf->SectionTitle('I. IDENTIFICATION DE L’ASSUJETTI');

$pdf->RowLabelValue('Type', strtoupper($npf['type_personne'] ?? '-'));
$pdf->RowLabelValue('Nom / Raison sociale', nomContribuableNPF($npf));
$pdf->RowLabelValue('NIF', $npf['nif'] ?? '-');
$pdf->RowLabelValue('RCCM / Patente', $npf['rccm'] ?? '-');
$pdf->RowLabelValue('Téléphone', $npf['telephone'] ?? '-');
$pdf->RowLabelValue(
    'Ville / Adresse',
    trim(($npf['ville'] ?? '') . ' - ' . ($npf['adresse'] ?? '-'))
);

$pdf->Ln(3);

$pdf->SectionTitle('II. RÉFÉRENCES');

$pdf->RowLabelValue('Note de Taxation', $npf['numero_nt'] ?? '-');
$pdf->RowLabelValue('Note de Débit', $npf['numero_nd'] ?? '-');
$pdf->RowLabelValue('NP mère', $npf['numero_np_mere'] ?? '-');
$pdf->RowLabelValue('NPF', $npf['numero_np'] ?? '-');
$pdf->RowLabelValue('Tranche', str_pad((int)($npf['numero_tranche'] ?? 0), 3, '0', STR_PAD_LEFT));
$pdf->RowLabelValue('Statut', strtoupper($npf['statut'] ?? '-'));

$pdf->Ln(3);

$pdf->SectionTitle('III. ORDONNANCEMENT');

$pdf->RowLabelValue('Ordonnateur', $npf['ordonnateur'] ?? '-');
$pdf->RowLabelValue('Date émission', formatDateNPF($npf['date_emission'] ?? null));
$pdf->RowLabelValue('Date échéance', formatDateNPF($npf['date_echeance'] ?? null));

$pdf->Ln(3);

$pdf->SectionTitle('IV. MONTANT À PERCEVOIR');

$pdf->RowLabelValue('Montant initial', montantNPF($npf['montant_initial'] ?? 0));
$pdf->RowLabelValue('Montant payé', montantNPF($npf['montant_paye'] ?? 0));
$pdf->RowLabelValue('Solde restant', montantNPF($npf['solde_restant'] ?? 0));
$pdf->RowLabelValue('Pénalité assiette', montantNPF($npf['penalite_assiette'] ?? 0));
$pdf->RowLabelValue('Pénalité recouvrement', montantNPF($npf['penalite_recouvrement'] ?? 0));

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(60, 8, pdfTxt('TOTAL À PAYER'), 1);
$pdf->Cell(
    126,
    8,
    pdfTxt(montantNPF($npf['solde_restant'] ?? $npf['montant_initial'] ?? 0)),
    1,
    1,
    'R'
);

$pdf->Ln(3);

$pdf->SectionTitle('V. DÉTAIL DE LA NOTE DE CALCUL');

if (empty($detailsNT)) {
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(0, 8, pdfTxt('Aucun détail de calcul disponible.'), 1, 1);
} else {
    foreach ($detailsNT as $d) {
        $acte = $d['libelle_acte']
            ?? $d['nature_acte']
            ?? $d['acte_generateur']
            ?? '-';

        $typeCalcul = strtoupper($d['type_calcul'] ?? $d['mode_calcul'] ?? '-');

        $base = $d['base_imposable']
            ?? $d['base_calcul']
            ?? 0;

        $periode = $d['periode_libelle']
            ?? $d['periodicite_info']
            ?? $d['periodicite']
            ?? '-';

        $mois = $d['mois_concernes'] ?? '';

        $montant = $d['total_ligne_cdf']
            ?? $d['montant_cdf']
            ?? $d['montant_total']
            ?? $d['montant_acte']
            ?? 0;

        $detailsCalcul = detailCalculTextNPF($d['details_calcul'] ?? null);

        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetFillColor(245, 245, 245);
        $pdf->Cell(
            0,
            7,
            pdfTxt('Acte taxable : ' . $acte),
            1,
            1,
            'L',
            true
        );

        $pdf->SetFont('Arial', '', 8);
        $pdf->Cell(38, 7, pdfTxt('Type calcul'), 1);
        $pdf->Cell(55, 7, pdfTxt($typeCalcul), 1);
        $pdf->Cell(38, 7, pdfTxt('Base imposable'), 1);
        $pdf->Cell(55, 7, pdfTxt(montantNPF($base)), 1, 1, 'R');

        $pdf->Cell(38, 7, pdfTxt('Période'), 1);
        $pdf->Cell(55, 7, pdfTxt($periode), 1);
        $pdf->Cell(38, 7, pdfTxt('Montant'), 1);
        $pdf->Cell(55, 7, pdfTxt(montantNPF($montant)), 1, 1, 'R');

        if (!empty($mois)) {
            $pdf->SetFont('Arial', '', 8);
            $pdf->MultiCell(
                0,
                6,
                pdfTxt('Mois concernés : ' . $mois),
                1,
                'L'
            );
        }

        $pdf->SetFont('Arial', '', 8);
        $pdf->MultiCell(
            0,
            6,
            pdfTxt("Détail du calcul :\n" . $detailsCalcul),
            1,
            'L'
        );

        $pdf->Ln(2);
    }
}

$pdf->SectionTitle('VI. RÉPARTITION BANCAIRE');

$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(45, 7, pdfTxt('Banque'), 1);
$pdf->Cell(60, 7, pdfTxt('Compte'), 1);
$pdf->Cell(25, 7, pdfTxt('Devise'), 1);
$pdf->Cell(56, 7, pdfTxt('Montant affecté'), 1, 1);

$pdf->SetFont('Arial', '', 8);

if (empty($banques)) {
    $pdf->Cell(186, 7, pdfTxt('Aucune répartition bancaire affectée.'), 1, 1);
} else {
    foreach ($banques as $b) {
        $pdf->Cell(45, 7, pdfTxt($b['banque'] ?? '-'), 1);
        $pdf->Cell(60, 7, pdfTxt($b['numero_compte'] ?? '-'), 1);
        $pdf->Cell(25, 7, pdfTxt($b['devise'] ?? 'CDF'), 1);
        $pdf->Cell(
            56,
            7,
            pdfTxt(montantNPF($b['montant_affecte'] ?? 0)),
            1,
            1,
            'R'
        );
    }
}

$pdf->Ln(3);

$pdf->SetTextColor(150, 0, 0);
$pdf->SetFont('Arial', 'B', 8);
$pdf->MultiCell(
    0,
    6,
    pdfTxt(
        'Cette Note de Perception Fractionnée constitue une tranche de paiement. Elle ne vaut pas acquit libératoire final tant que toutes les fractions ne sont pas payées.'
    ),
    1,
    'C'
);
$pdf->SetTextColor(0, 0, 0);

$pdf->Ln(4);

$pdf->SectionTitle('VII. SCEAU ET SIGNATURE');

$pdf->Ln(8);

$pdf->SetFont('Arial', '', 9);
$pdf->Cell(90, 20, pdfTxt('Signature déclarant'), 1, 0, 'C');
$pdf->Cell(96, 20, pdfTxt('Sceau / Ordonnateur'), 1, 1, 'C');

$pdf->Ln(6);

$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(0, 6, pdfTxt('GUICHET UNIQUE'), 0, 1);
$pdf->SetFont('Arial', '', 8);
$pdf->Cell(0, 5, pdfTxt('Délivrée le ' . date('d/m/Y H:i:s')), 0, 1);
$pdf->Cell(0, 5, pdfTxt('Document vérifié par QR Code sécurisé'), 0, 1);

$fileName = 'NPF_' . cleanFileNameNPF($npf['numero_np']) . '.pdf';

cpCleanOutputBeforePdf();
$pdf->Output('I', $fileName);
exit;
?>