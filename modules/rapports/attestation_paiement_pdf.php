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

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../config/security.php";
require_once __DIR__ . "/../../lib/fpdf/fpdf.php";
require_once __DIR__ . "/../../lib/phpqrcode/qrlib.php";
require_once __DIR__ . "/../../core/secure_qr_engine.php";

checkAuth();

$numero = $_GET['numero'] ?? null;

if (!$numero) {
    die("Numéro NP / NPF obligatoire.");
}

$stmt = $pdo->prepare("
    SELECT 
        np.*,
        mere.numero_np AS numero_np_mere,
        nd.numero_nd,
        nt.id AS note_taxation_id,
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
        c.ville
    FROM notes_perception np
    LEFT JOIN notes_perception mere ON np.np_mere_id = mere.id
    JOIN notes_debit nd ON np.note_debit_id = nd.id
    JOIN notes_taxation nt ON nd.note_taxation_id = nt.id
    JOIN contribuables c ON nt.contribuable_id = c.id
    WHERE np.numero_np = ?
    LIMIT 1
");
$stmt->execute([$numero]);
$np = $stmt->fetch();

if (!$np) {
    die("NP / NPF introuvable.");
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
$stmt->execute([$np['note_taxation_id']]);
$detailsNT = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT *
    FROM paiements
    WHERE note_perception_id = ?
    ORDER BY id DESC
    LIMIT 1
");
$stmt->execute([$np['id']]);
$paiement = $stmt->fetch();

function nomContribuableAP($c)
{
    if (!empty($c['raison_sociale'])) return $c['raison_sociale'];
    return trim(($c['nom'] ?? '') . ' ' . ($c['postnom'] ?? '') . ' ' . ($c['prenom'] ?? ''));
}

function moneyAP($v, $decimals = 2)
{
    return number_format((float)$v, $decimals, ',', ' ') . ' CDF';
}

function numberAP($v, $decimals = 2)
{
    return number_format((float)$v, $decimals, ',', ' ');
}

function dateAP($d)
{
    if (!$d) return '-';
    return date('d/m/Y H:i:s', strtotime($d));
}

function cleanAP($text)
{
    return preg_replace('/[^A-Za-z0-9_\-]/', '_', $text);
}

function safeTextAP($text)
{
    $text = (string)($text ?? '-');
    $text = preg_replace('/\s+/', ' ', trim($text));
    return $text === '' ? '-' : $text;
}

function fmtTauxAP($taux)
{
    $taux = (float)$taux;
    if ($taux <= 0) return '-';
    return rtrim(rtrim(number_format($taux, 4, ',', ' '), '0'), ',');
}

function buildDetailCalculAP($d)
{
    $acte = safeTextAP($d['libelle_acte'] ?? $d['nature_acte'] ?? $d['acte_generateur'] ?? 'Acte taxable');
    $base = (float)($d['base_imposable'] ?? 0);
    $quantite = (float)($d['quantite'] ?? 1);
    $devise = strtoupper($d['devise_source'] ?? 'CDF');
    $tauxChange = (float)($d['taux_change'] ?? 1);

    $principalCdf = (float)($d['montant_acte'] ?? 0);
    $faCdf = (float)($d['montant_frais_admin'] ?? 0);
    $ftCdf = (float)($d['montant_frais_tech'] ?? 0);

    $totalSource = (float)($d['total_ligne_source'] ?? 0);
    $totalCdf = (float)($d['total_ligne_cdf'] ?? $d['montant_cdf'] ?? $d['total_ligne'] ?? 0);

    $faSource = ($devise === 'USD' && $tauxChange > 0) ? ($faCdf / $tauxChange) : $faCdf;
    $ftSource = ($devise === 'USD' && $tauxChange > 0) ? ($ftCdf / $tauxChange) : $ftCdf;
    $principalSource = ($devise === 'USD' && $tauxChange > 0) ? ($principalCdf / $tauxChange) : $principalCdf;

    // Si total_ligne_source est bien renseigné, on garde le principal source déduit du total source - frais sources.
    if ($devise === 'USD' && $totalSource > 0) {
        $principalSource = max(0, $totalSource - $faSource - $ftSource);
    }

    $lines = [];
    $lines[] = $acte . ' :';

    if ($devise === 'USD') {
        $lines[] = numberAP($base) . ' × ' . numberAP($quantite) . ' USD = ' . numberAP($principalSource) . ' USD';
        $lines[] = 'ou soit ' . moneyAP($principalCdf);
        $lines[] = '';
        $lines[] = 'Frais administratif : ' . numberAP($faSource) . ' USD × ' . fmtTauxAP($tauxChange) . ' = ' . moneyAP($faCdf);
        $lines[] = 'Frais technique : ' . numberAP($ftSource) . ' USD × ' . fmtTauxAP($tauxChange) . ' = ' . moneyAP($ftCdf);
        $lines[] = 'Total ligne : ' . moneyAP($totalCdf);
    } else {
        $lines[] = numberAP($base) . ' × ' . numberAP($quantite) . ' CDF = ' . moneyAP($principalCdf);
        $lines[] = 'Frais administratif : ' . moneyAP($faCdf);
        $lines[] = 'Frais technique : ' . moneyAP($ftCdf);
        $lines[] = 'Total ligne : ' . moneyAP($totalCdf);
    }

    return implode("\n", $lines);
}

$qrContent = buildEncryptedQrContent(
    $pdo,
    'ATTESTATION',
    $np['numero_np'],
    (float)($np['montant_paye'] ?? 0)
);

$GLOBALS['qrMatrix'] = QRcode::text($qrContent, false, QR_ECLEVEL_L, 0, 1);

class AttestationPaiementPDF extends FPDF
{
    function DrawQRCode($matrix, $x, $y, $size = 0.63)
    {
        if (!is_array($matrix)) return;
        $this->SetFillColor(0, 0, 0);

        foreach ($matrix as $rowIndex => $row) {
            for ($colIndex = 0; $colIndex < strlen($row); $colIndex++) {
                if ($row[$colIndex] === '1') {
                    $this->Rect($x + ($colIndex * $size), $y + ($rowIndex * $size), $size, $size, 'F');
                }
            }
        }
    }

    function Header()
    {

        $logoProvince = __DIR__ . '/../../assets/images/logo_province.png';
        if (is_file($logoProvince)) {
            $this->Image($logoProvince, 10, 8, 24);
        }
// QR Code lisible, même gabarit que les autres documents.
        $this->DrawQRCode($GLOBALS['qrMatrix'] ?? [], 166, 8, 0.63);

        $this->SetFont('Arial', '', 5);
        $this->SetXY(162, 43);
        $this->Cell(42, 3, pdfTxt('Vérification sécurisée'), 0, 0, 'C');

        $this->SetY(8);
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(0, 5, pdfTxt('REPUBLIQUE DEMOCRATIQUE DU CONGO'), 0, 1, 'C');
        $this->Cell(0, 5, pdfTxt('PROVINCE DE LA TSHOPO'), 0, 1, 'C');
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 5, pdfTxt('DIRECTION GENERALE DES RECETTES DE LA TSHOPO'), 0, 1, 'C');
        $this->Cell(0, 5, pdfTxt('DIRECTION DU RECOUVREMENT'), 0, 1, 'C');
        $this->Ln(16);
    }

    function Footer()
    {
        $this->SetY(-12);
        $this->SetFont('Arial', '', 8);
        $this->Cell(0, 5, pdfTxt('Copyright ' . date('Y') . ' - cOllect_Pay, Tous droits réservés'), 0, 0, 'C');
    }

    function SectionTitle($title)
    {
        $this->SetFillColor(230, 230, 230);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(0, 7, pdfTxt($title), 1, 1, 'L', true);
    }

    function RowLabelValue($label, $value)
    {
        $this->SetFont('Arial', '', 8.5);
        $this->Cell(58, 7, pdfTxt($label), 1, 0, 'L');
        $this->MultiCell(128, 7, pdfTxt((string)$value), 1, 'L');
    }

    function CheckPageSpace($neededHeight)
    {
        if ($this->GetY() + $neededHeight > 276) {
            $this->AddPage();
        }
    }
}

$pdf = new AttestationPaiementPDF('P', 'mm', 'A4');
$pdf->SetMargins(12, 12, 12);
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 15);

$pdf->SetFont('Arial', 'B', 13);
$pdf->Cell(0, 9, pdfTxt('ATTESTATION DE PAIEMENT'), 0, 1, 'C');

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 7, pdfTxt('Référence : ' . $np['numero_np']), 0, 1, 'C');
$pdf->Ln(3);

$pdf->SectionTitle('I. IDENTIFICATION DE L’ASSUJETTI');
$pdf->RowLabelValue('Type', strtoupper($np['type_personne'] ?? '-'));
$pdf->RowLabelValue('Nom / Raison sociale', nomContribuableAP($np));
$pdf->RowLabelValue('NIF', $np['nif'] ?? '-');
$pdf->RowLabelValue('RCCM / Patente', $np['rccm'] ?? '-');
$pdf->RowLabelValue('Téléphone', $np['telephone'] ?? '-');
$pdf->RowLabelValue('Ville / Adresse', trim(($np['ville'] ?? '') . ' - ' . ($np['adresse'] ?? '-')));

$pdf->Ln(3);

$pdf->SectionTitle('II. RÉFÉRENCES');
$pdf->RowLabelValue('Note de Taxation', $np['numero_nt'] ?? '-');
$pdf->RowLabelValue('Note de Débit', $np['numero_nd'] ?? '-');
$pdf->RowLabelValue('NP / NPF', $np['numero_np'] ?? '-');
$pdf->RowLabelValue('NP mère', $np['numero_np_mere'] ?? '-');
$pdf->RowLabelValue('Type', strtoupper($np['type_np'] ?? '-'));
$pdf->RowLabelValue('Statut', strtoupper($np['statut'] ?? '-'));

$pdf->Ln(3);

$pdf->SectionTitle('III. PAIEMENT EFFECTUÉ');
$pdf->RowLabelValue('Montant initial', moneyAP($np['montant_initial'] ?? 0));
$pdf->RowLabelValue('Montant payé', moneyAP($np['montant_paye'] ?? 0));
$pdf->RowLabelValue('Solde restant', moneyAP($np['solde_restant'] ?? 0));
$pdf->RowLabelValue('Date paiement', dateAP($paiement['date_paiement'] ?? $paiement['created_at'] ?? null));
$pdf->RowLabelValue('Référence transaction', $paiement['reference_transaction'] ?? '-');
$pdf->RowLabelValue('Mode paiement', $paiement['mode_paiement_id'] ?? '-');
$pdf->RowLabelValue('Devise paiement', $paiement['devise'] ?? 'CDF');
$pdf->RowLabelValue('Montant converti CDF', moneyAP($paiement['montant_converti_cdf'] ?? $np['montant_paye'] ?? 0));

$pdf->Ln(3);

$pdf->SectionTitle('IV. DÉTAIL DE LA NOTE DE CALCUL');

if (empty($detailsNT)) {
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(0, 8, pdfTxt('Aucun détail de calcul disponible.'), 1, 1);
} else {
    foreach ($detailsNT as $d) {
        $pdf->CheckPageSpace(55);

        $acte = safeTextAP($d['libelle_acte'] ?? $d['nature_acte'] ?? $d['acte_generateur'] ?? '-');
        $typeCalcul = strtoupper($d['type_calcul'] ?? $d['mode_calcul'] ?? '-');
        $periode = $d['periode_libelle'] ?? $d['periodicite_info'] ?? $d['periodicite'] ?? '-';
        $mois = $d['mois_concernes'] ?? '';
        $base = (float)($d['base_imposable'] ?? 0);
        $quantite = (float)($d['quantite'] ?? 1);
        $tauxChange = (float)($d['taux_change'] ?? 1);
        $montant = (float)($d['total_ligne_cdf'] ?? $d['montant_cdf'] ?? $d['montant_acte'] ?? 0);

        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetFillColor(245, 245, 245);
        $pdf->MultiCell(0, 6, pdfTxt('Acte taxable : ' . $acte), 1, 'L', true);

        $pdf->SetFont('Arial', '', 8);
        $pdf->Cell(38, 7, pdfTxt('Type calcul'), 1, 0, 'L');
        $pdf->Cell(55, 7, pdfTxt($typeCalcul), 1, 0, 'L');
        $pdf->Cell(38, 7, pdfTxt('Base'), 1, 0, 'L');
        $pdf->Cell(55, 7, pdfTxt(numberAP($base)), 1, 1, 'R');

        $pdf->Cell(38, 7, pdfTxt('Quantité'), 1, 0, 'L');
        $pdf->Cell(55, 7, pdfTxt(numberAP($quantite)), 1, 0, 'L');
        $pdf->Cell(38, 7, pdfTxt('Taux change'), 1, 0, 'L');
        $pdf->Cell(55, 7, pdfTxt(fmtTauxAP($tauxChange)), 1, 1, 'R');

        $pdf->Cell(38, 7, pdfTxt('Période'), 1, 0, 'L');
        $pdf->Cell(55, 7, pdfTxt($periode), 1, 0, 'L');
        $pdf->Cell(38, 7, pdfTxt('Montant'), 1, 0, 'L');
        $pdf->Cell(55, 7, pdfTxt(moneyAP($montant)), 1, 1, 'R');

        if (!empty($mois)) {
            $pdf->MultiCell(0, 6, pdfTxt('Mois concernés : ' . $mois), 1, 'L');
        }

        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(0, 6, pdfTxt('Détail du calcul'), 1, 1, 'L');

        $pdf->SetFont('Arial', '', 8);
        $pdf->MultiCell(0, 5, pdfTxt(buildDetailCalculAP($d)), 1, 'L');
        $pdf->Ln(2);
    }
}

$pdf->Ln(3);

$pdf->SetTextColor(150, 0, 0);
$pdf->SetFont('Arial', 'B', 8);
$pdf->MultiCell(
    0,
    6,
    pdfTxt(
        "La présente attestation confirme un paiement effectué sur une NP/NPF. Elle ne vaut acquit libératoire final que si le solde est totalement apuré et qu'une quittance officielle est émise."
    ),
    1,
    'L'
);
$pdf->SetTextColor(0, 0, 0);

$pdf->Ln(5);

$pdf->SectionTitle('V. SCEAU ET SIGNATURE');
$pdf->Ln(8);
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(90, 20, pdfTxt('Signature déclarant'), 1, 0, 'C');
$pdf->Cell(96, 20, pdfTxt('Sceau / Agent de recouvrement'), 1, 1, 'C');

$pdf->Ln(6);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(0, 6, pdfTxt('GUICHET UNIQUE'), 0, 1, 'L');
$pdf->SetFont('Arial', '', 8);
$pdf->Cell(0, 5, pdfTxt('Délivrée le ' . date('d/m/Y H:i:s')), 0, 1, 'L');
$pdf->Cell(0, 5, pdfTxt('Document vérifié par QR Code sécurisé'), 0, 1, 'L');

$fileName = 'ATTESTATION_' . cleanAP($np['numero_np']) . '.pdf';
cpCleanOutputBeforePdf();
$pdf->Output('I', $fileName);
exit;
?>
