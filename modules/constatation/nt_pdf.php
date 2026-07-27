<?php
require_once "../../config/database.php";
require_once "../../config/security.php";



$numero_nt = $_GET['numero'] ?? null;

if (!$numero_nt) {
    die("Numéro NT obligatoire.");
}

function cpPdfClean($txt): string
{
    $txt = (string)($txt ?? '');
    return iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $txt);
}

function cpMoneyPdf($amount): string
{
    return number_format((float)$amount, 2, ',', ' ') . ' CDF';
}

function cpJsonPdf($json): array
{
    $arr = json_decode((string)$json, true);
    return is_array($arr) ? $arr : [];
}

$stmt = $pdo->prepare("SELECT * FROM notes_taxation WHERE numero_nt = ? LIMIT 1");
$stmt->execute([$numero_nt]);
$nt = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$nt) {
    die("NT introuvable.");
}

$stmt = $pdo->prepare("
    SELECT d.*, a.code_article, a.nature_acte AS article_nature, a.libelle_taux AS article_libelle_taux
    FROM notes_taxation_details d
    LEFT JOIN articles_budgetaires a ON a.id = d.article_id
    WHERE d.note_taxation_id = ?
    ORDER BY d.id ASC
");
$stmt->execute([(int)$nt['id']]);
$details = $stmt->fetchAll(PDO::FETCH_ASSOC);

$fpdf = __DIR__ . "/../../lib/fpdf/fpdf.php";
if (!file_exists($fpdf)) {
    die("FPDF introuvable : lib/fpdf/fpdf.php");
}
require_once $fpdf;

$pdf = new FPDF('P', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 15);

$pdf->SetFont('Arial', 'B', 15);
$pdf->Cell(0, 8, cpPdfClean('NOTE DE TAXATION'), 0, 1, 'C');

$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, cpPdfClean('Numéro : ' . $numero_nt), 0, 1, 'C');
$pdf->Ln(5);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 7, cpPdfClean('Détails des actes taxables'), 0, 1);

$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(45, 8, cpPdfClean('Acte'), 1);
$pdf->Cell(35, 8, cpPdfClean('Base/Qté'), 1);
$pdf->Cell(35, 8, cpPdfClean('Période'), 1);
$pdf->Cell(35, 8, cpPdfClean('Montant'), 1);
$pdf->Cell(35, 8, cpPdfClean('Total'), 1);
$pdf->Ln();

$pdf->SetFont('Arial', '', 8);

$total = 0;

foreach ($details as $d) {
    $lib = $d['libelle_acte'] ?? $d['nature_acte'] ?? $d['article_nature'] ?? 'Acte';
    $type = strtolower($d['type_calcul'] ?? $d['mode_calcul'] ?? '');
    $base = (float)($d['base_imposable'] ?? 0);
    $qte = (float)($d['quantite'] ?? 0);
    $loyer = (float)($d['loyer_mensuel'] ?? 0);
    $periode = $d['periode_libelle'] ?? $d['periodicite_info'] ?? 'Ponctuelle';
    $mois = $d['mois_liste'] ?? '';
    $montant = (float)($d['montant_acte_cdf'] ?? $d['montant_acte'] ?? 0);
    $ligne = (float)($d['total_ligne_cdf'] ?? $d['total_ligne'] ?? 0);

    $total += $ligne;

    if (in_array($type, ['irl','rl'], true)) {
        $baseTxt = 'Loyer: ' . cpMoneyPdf($loyer) . "\nBase: " . cpMoneyPdf($base);
    } elseif (in_array($type, ['par_unite','fixe'], true)) {
        $baseTxt = 'Qté: ' . number_format($qte, 2, ',', ' ');
    } else {
        $baseTxt = 'Base: ' . cpMoneyPdf($base);
    }

    $periodTxt = $periode;
    if ($mois) {
        $periodTxt .= "\n" . $mois;
    }

    $x = $pdf->GetX();
    $y = $pdf->GetY();

    $pdf->MultiCell(45, 5, cpPdfClean($lib), 1);
    $h = $pdf->GetY() - $y;

    $pdf->SetXY($x + 45, $y);
    $pdf->MultiCell(35, 5, cpPdfClean($baseTxt), 1);
    $h = max($h, $pdf->GetY() - $y);

    $pdf->SetXY($x + 80, $y);
    $pdf->MultiCell(35, 5, cpPdfClean($periodTxt), 1);
    $h = max($h, $pdf->GetY() - $y);

    $pdf->SetXY($x + 115, $y);
    $pdf->MultiCell(35, 5, cpPdfClean(cpMoneyPdf($montant)), 1);
    $h = max($h, $pdf->GetY() - $y);

    $pdf->SetXY($x + 150, $y);
    $pdf->MultiCell(35, 5, cpPdfClean(cpMoneyPdf($ligne)), 1);
    $h = max($h, $pdf->GetY() - $y);

    $pdf->SetXY($x, $y + $h);
}

$pdf->Ln(5);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 8, cpPdfClean('TOTAL : ' . cpMoneyPdf($total)), 0, 1, 'R');

$pdf->Output('I', 'NT_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $numero_nt) . '.pdf');
exit;
