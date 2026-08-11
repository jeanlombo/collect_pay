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
    die("Numéro NT obligatoire.");
}

$stmt = $pdo->prepare("
    SELECT 
        nt.*,
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
        u.nom AS taxateur
    FROM notes_taxation nt
    JOIN contribuables c ON nt.contribuable_id = c.id
    LEFT JOIN users u ON nt.user_taxateur_id = u.id
    WHERE nt.numero_nt = ?
    LIMIT 1
");
$stmt->execute([$numero]);
$nt = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$nt) {
    die("NT introuvable.");
}

$stmt = $pdo->prepare("
    SELECT 
        d.*,
        ab.code_article,
        ab.secteur,
        ab.nature_acte,
        ab.fait_generateur,
        ab.type_taux AS article_type_taux,
        ab.mode_calcul AS article_mode_calcul,
        ab.taux_acte AS article_taux_acte,
        ab.frais_administratif AS article_frais_administratif,
        ab.frais_technique AS article_frais_technique,
        ab.devise_base AS article_devise_base,
        ab.libelle_taux AS article_libelle_taux,
        ab.base_calcul_libelle AS article_base_calcul_libelle,
        dir.nom_direction,
        srv.nom_service
    FROM notes_taxation_details d
    LEFT JOIN articles_budgetaires ab ON d.article_id = ab.id
    LEFT JOIN directions dir ON d.direction_id = dir.id
    LEFT JOIN services_assiette srv ON d.service_id = srv.id
    WHERE d.note_taxation_id = ?
    ORDER BY d.id ASC
");
$stmt->execute([$nt['id']]);
$detailsNT = $stmt->fetchAll(PDO::FETCH_ASSOC);

function ntText($value, $default = '-')
{
    $value = trim(preg_replace('/\s+/u', ' ', (string)($value ?? '')));
    return $value !== '' ? $value : $default;
}

function ntUtf($value)
{
    return pdfTxt(ntText($value));
}

function ntName($c)
{
    if (!empty($c['raison_sociale'])) {
        return ntText($c['raison_sociale']);
    }
    return ntText(($c['nom'] ?? '') . ' ' . ($c['postnom'] ?? '') . ' ' . ($c['prenom'] ?? ''));
}

function ntMoney($value, $devise = 'CDF')
{
    return number_format((float)$value, 2, ',', ' ') . ' ' . strtoupper($devise ?: 'CDF');
}

function ntMoney0($value, $devise = 'CDF')
{
    $v = (float)$value;
    if (abs($v - round($v)) < 0.005) {
        return number_format($v, 0, ',', ' ') . ' ' . strtoupper($devise ?: 'CDF');
    }
    return ntMoney($v, $devise);
}

function ntDate($date)
{
    if (empty($date)) return '-';
    $t = strtotime($date);
    return $t ? date('d/m/Y', $t) : '-';
}

function ntDateTime($date)
{
    if (empty($date)) return '-';
    $t = strtotime($date);
    return $t ? date('d/m/Y H:i:s', $t) : '-';
}

function ntDetailTotalCDF($d)
{
    foreach (['total_ligne_cdf', 'montant_cdf', 'montant_total', 'total_ligne', 'montant_acte'] as $k) {
        if (isset($d[$k]) && $d[$k] !== '') {
            return (float)$d[$k];
        }
    }
    return 0;
}

function ntJsonDetails($d)
{
    if (empty($d['details_calcul'])) return [];
    $json = json_decode((string)$d['details_calcul'], true);
    return is_array($json) ? $json : [];
}

function ntPrincipalSource($d)
{
    $json = ntJsonDetails($d);
    if (isset($json['principal_source']) && $json['principal_source'] !== '') {
        return (float)$json['principal_source'];
    }
    if (!empty($json['details']) && is_array($json['details'])) {
        foreach ($json['details'] as $line) {
            if (($line['type'] ?? '') === 'principal' && isset($line['montant_source'])) {
                return (float)$line['montant_source'];
            }
        }
    }
    if (isset($d['principal_source']) && $d['principal_source'] !== '') return (float)$d['principal_source'];

    // Dans notes_taxation_details, montant_acte est généralement figé en CDF.
    // Si l'acte est en USD, on reconstitue la source USD avec le taux enregistré.
    if (isset($d['montant_acte']) && $d['montant_acte'] !== '') {
        $montantActe = (float)$d['montant_acte'];
        $devise = ntDeviseSource($d);
        $taux = (float)($d['taux_change'] ?? 0);
        if ($devise === 'USD' && $taux > 1) {
            return $montantActe / $taux;
        }
        return $montantActe;
    }

    return ntDetailTotalCDF($d);
}

function ntDeviseSource($d)
{
    return strtoupper($d['devise_source'] ?? $d['article_devise_base'] ?? 'CDF');
}

function ntRateText($d)
{
    $mode = strtolower((string)($d['type_calcul'] ?? $d['mode_calcul'] ?? $d['article_mode_calcul'] ?? $d['article_type_taux'] ?? ''));
    $devise = ntDeviseSource($d);

    $taux = null;
    if (isset($d['taux_irl']) && (float)$d['taux_irl'] > 0) {
        return number_format((float)$d['taux_irl'], 2, ',', ' ') . ' % IRL';
    }
    if (isset($d['taux_rl']) && (float)$d['taux_rl'] > 0) {
        return number_format((float)$d['taux_rl'], 2, ',', ' ') . ' % RL';
    }
    if (isset($d['taux_pourcentage']) && (float)$d['taux_pourcentage'] > 0) {
        return number_format((float)$d['taux_pourcentage'], 2, ',', ' ') . ' %';
    }
    if (isset($d['article_taux_acte']) && $d['article_taux_acte'] !== '') {
        $taux = (float)$d['article_taux_acte'];
    }

    if ($taux === null) return '-';

    if (strpos($mode, 'pourcentage') !== false || in_array($mode, ['irl', 'rl'], true)) {
        return number_format($taux, 2, ',', ' ') . ' %';
    }
    return ntMoney0($taux, $devise);
}


function ntCalculationLines($d)
{
    // IMPORTANT : on reconstruit l'affichage à partir des montants réellement figés
    // dans notes_taxation_details, parce que les anciens details_calcul peuvent contenir
    // des formules mal libellées (ex: USD affiché en CDF ou "taux du jour" à 0).
    $lines = [];

    $devise = ntDeviseSource($d);
    $tauxChange = (float)($d['taux_change'] ?? 0);

    $qte = (float)($d['quantite'] ?? 1);
    $tauxActe = (float)($d['article_taux_acte'] ?? 0);
    if ($tauxActe <= 0 && isset($d['taux_pourcentage']) && (float)$d['taux_pourcentage'] > 0) {
        $tauxActe = (float)$d['taux_pourcentage'];
    }

    $principalSource = ntPrincipalSource($d);
    $principalCdf = (float)($d['montant_acte'] ?? 0);
    if ($principalCdf <= 0) {
        $principalCdf = ($devise === 'USD' && $tauxChange > 1)
            ? $principalSource * $tauxChange
            : $principalSource;
    }

    $libelle = ntText($d['libelle_acte'] ?? $d['nature_acte'] ?? 'Acte taxable');

    if ($qte > 0 && $tauxActe > 0) {
        if ($devise === 'USD') {
            $textePrincipal = ntNumberSmart($qte) . ' × ' . ntNumberSmart($tauxActe) . ' USD = ' . ntMoney($principalSource, 'USD');
            if ($tauxChange > 1) {
                $textePrincipal .= ' ou soit ' . ntMoney($principalCdf, 'CDF');
            }
        } else {
            $textePrincipal = ntNumberSmart($qte) . ' × ' . ntMoney0($tauxActe, 'CDF') . ' = ' . ntMoney($principalCdf, 'CDF');
        }
        $lines[] = $libelle . ' : ' . $textePrincipal;
    } else {
        $lines[] = $libelle . ' : ' . ntMoney($principalCdf, 'CDF');
    }

    $faCdf = (float)($d['montant_frais_admin'] ?? 0);
    $ftCdf = (float)($d['montant_frais_tech'] ?? 0);

    // Si l'acte est en USD, les FA/FT sont des montants fixes USD convertis en CDF.
    // Exemple : 5 USD × 2800 = 14 000 CDF.
    $faUsd = 0;
    $ftUsd = 0;
    if ($tauxChange > 1) {
        $faUsd = $faCdf > 0 ? ($faCdf / $tauxChange) : (float)($d['article_frais_administratif'] ?? 0);
        $ftUsd = $ftCdf > 0 ? ($ftCdf / $tauxChange) : (float)($d['article_frais_technique'] ?? 0);
    }

    if ($faCdf > 0) {
        if ($faUsd > 0 && $tauxChange > 1) {
            $lines[] = 'Frais administratif : ' . ntNumberSmart($faUsd) . ' USD × ' . ntNumberSmart($tauxChange, 0) . ' = ' . ntMoney($faCdf, 'CDF');
        } else {
            $lines[] = 'Frais administratif : ' . ntMoney($faCdf, 'CDF');
        }
    }

    if ($ftCdf > 0) {
        if ($ftUsd > 0 && $tauxChange > 1) {
            $lines[] = 'Frais technique : ' . ntNumberSmart($ftUsd) . ' USD × ' . ntNumberSmart($tauxChange, 0) . ' = ' . ntMoney($ftCdf, 'CDF');
        } else {
            $lines[] = 'Frais technique : ' . ntMoney($ftCdf, 'CDF');
        }
    }

    return implode("\n", $lines);
}

function ntExchangeText($pdo, $details)
{
    // 1) Priorité au taux enregistré dans les détails de la NT
    $tauxTrouve = 0;
    foreach ($details as $d) {
        $taux = (float)($d['taux_change'] ?? 0);
        if ($taux > $tauxTrouve) {
            $tauxTrouve = $taux;
        }
    }

    // 2) Si les détails sont en CDF et portent 1, on affiche le taux officiel actif USD/CDF
    if ($tauxTrouve <= 1) {
        try {
            $stmt = $pdo->query("
                SELECT taux
                FROM taux_change_officiel
                WHERE devise = 'USD' AND actif = 1
                ORDER BY date_application DESC, id DESC
                LIMIT 1
            ");
            $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
            if ($row && (float)$row['taux'] > 1) {
                $tauxTrouve = (float)$row['taux'];
            }
        } catch (Exception $e) {
            // On garde le PDF imprimable même si la table du taux n'existe pas.
        }
    }

    return $tauxTrouve > 1 ? number_format($tauxTrouve, 0, ',', ' ') : '-';
}

function ntNumberSmart($value, $decimals = 2)
{
    $value = (float)$value;
    if (abs($value - round($value)) < 0.00001) {
        return number_format($value, 0, ',', ' ');
    }
    return number_format($value, $decimals, ',', ' ');
}

function ntIsMonetaryBase($d)
{
    $label = strtoupper(ntText($d['article_base_calcul_libelle'] ?? $d['unite_assiette'] ?? ''));
    $mode  = strtolower((string)($d['mode_calcul'] ?? $d['article_mode_calcul'] ?? $d['article_type_taux'] ?? ''));

    if (strpos($mode, 'pourcentage') !== false || in_array($mode, ['irl', 'rl'], true)) {
        return true;
    }

    foreach (['LOYER', 'MONTANT', 'REVENU', 'BASE IMPOSABLE', 'VALEUR MARCHANDE'] as $mot) {
        if (strpos($label, $mot) !== false) {
            return true;
        }
    }

    return false;
}

function ntBaseDisplay($d, $base, $devise)
{
    if (ntIsMonetaryBase($d)) {
        return ntMoney0($base, $devise);
    }
    return ntNumberSmart($base);
}

function ntQteValeurText($d)
{
    $devise = ntDeviseSource($d);
    $base = ntBaseValue($d);
    $qte = (float)($d['quantite'] ?? 1);
    $label = ntBaseLabel($d);

    // Si la base est monétaire (IRL, RL, montant, revenu, etc.), on garde la devise.
    // Si la base est une quantité/valeur physique (licence, hectare, m2, nombre...), on enlève CDF.
    $baseTxt = ntBaseDisplay($d, $base, $devise);
    $qteTxt = ntNumberSmart($qte);

    return $label . " :
" . $baseTxt . "
Qté : " . $qteTxt;
}

function ntBaseValue($d)
{
    foreach (['base_imposable', 'base_calcul', 'loyer_mensuel'] as $k) {
        if (isset($d[$k]) && $d[$k] !== '') return (float)$d[$k];
    }
    return 0;
}

function ntPeriodText($d, $exercice)
{
    $period = $d['periode_libelle'] ?? $d['periodicite_info'] ?? $d['periodicite'] ?? '';
    if (empty($period)) $period = $exercice ?: date('Y');
    $mois = $d['mois_concernes'] ?? '';
    return ntText($period . (!empty($mois) ? ' / ' . $mois : ''));
}

function ntDesignation($d)
{
    $parts = [];
    if (!empty($d['nom_service'])) $parts[] = strtoupper($d['nom_service']);
    if (!empty($d['secteur'])) $parts[] = strtoupper($d['secteur']);
    $acte = $d['libelle_acte'] ?? $d['nature_acte'] ?? $d['acte_generateur'] ?? $d['fait_generateur'] ?? '-';
    $parts[] = strtoupper($acte);
    if (!empty($d['article_libelle_taux'])) $parts[] = $d['article_libelle_taux'];
    if (!empty($d['code_article'])) $parts[] = 'Code : ' . $d['code_article'];
    return ntText(implode("\n", $parts));
}

function ntBaseLabel($d)
{
    $label = $d['article_base_calcul_libelle'] ?? $d['unite_assiette'] ?? 'Base imposable';
    return ntText($label);
}

function ntCleanFileName($text)
{
    return preg_replace('/[^A-Za-z0-9_\-]/', '_', (string)$text);
}

function ntNumberToWordsBelow1000($n)
{
    $units = ['', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf', 'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize'];
    $tens = [2 => 'vingt', 3 => 'trente', 4 => 'quarante', 5 => 'cinquante', 6 => 'soixante'];
    $n = (int)$n;
    if ($n < 17) return $units[$n];
    if ($n < 20) return 'dix-' . $units[$n - 10];
    if ($n < 70) {
        $ten = intdiv($n, 10); $u = $n % 10;
        if ($u == 0) return $tens[$ten];
        if ($u == 1) return $tens[$ten] . ' et un';
        return $tens[$ten] . '-' . $units[$u];
    }
    if ($n < 80) return 'soixante-' . ntNumberToWordsBelow1000($n - 60);
    if ($n == 80) return 'quatre-vingts';
    if ($n < 100) return 'quatre-vingt-' . ntNumberToWordsBelow1000($n - 80);
    $hund = intdiv($n, 100); $r = $n % 100;
    $hText = $hund == 1 ? 'cent' : $units[$hund] . ' cent';
    if ($r == 0) return $hText . ($hund > 1 ? 's' : '');
    return $hText . ' ' . ntNumberToWordsBelow1000($r);
}

function ntNumberToWords($n)
{
    $n = (int)round($n);
    if ($n === 0) return 'zéro';
    $parts = [];
    $billions = intdiv($n, 1000000000); $n %= 1000000000;
    $millions = intdiv($n, 1000000); $n %= 1000000;
    $thousands = intdiv($n, 1000); $n %= 1000;
    if ($billions) $parts[] = ($billions == 1 ? 'un' : ntNumberToWordsBelow1000($billions)) . ' milliard' . ($billions > 1 ? 's' : '');
    if ($millions) $parts[] = ($millions == 1 ? 'un' : ntNumberToWordsBelow1000($millions)) . ' million' . ($millions > 1 ? 's' : '');
    if ($thousands) $parts[] = ($thousands == 1 ? 'mille' : ntNumberToWordsBelow1000($thousands) . ' mille');
    if ($n) $parts[] = ntNumberToWordsBelow1000($n);
    return implode(' ', $parts);
}

$totalActes = 0;
foreach ($detailsNT as $d) {
    $totalActes += ntDetailTotalCDF($d);
}
$penaliteAssiette = (float)($nt['penalite_assiette'] ?? 0);
$penaliteRecouvrement = (float)($nt['penalite_recouvrement'] ?? 0);
$totalGeneral = $totalActes + $penaliteAssiette + $penaliteRecouvrement;

$qrContent = buildEncryptedQrContent($pdo, 'NT', $nt['numero_nt'], $totalGeneral);
$GLOBALS['qrMatrix'] = QRcode::text($qrContent, false, QR_ECLEVEL_L, 0, 1);

class CollectPayNTPDF extends FPDF
{
    public function DrawQRCode($matrix, $x, $y, $size = 0.85)
    {
        if (!is_array($matrix) || empty($matrix)) return;
        $this->SetFillColor(0, 0, 0);
        foreach ($matrix as $rowIndex => $row) {
            $row = (string)$row;
            for ($colIndex = 0; $colIndex < strlen($row); $colIndex++) {
                if ($row[$colIndex] === '1') {
                    $this->Rect($x + ($colIndex * $size), $y + ($rowIndex * $size), $size, $size, 'F');
                }
            }
        }
    }

    public function QrSize($matrix, $size = 0.85)
    {
        return is_array($matrix) ? count($matrix) * $size : 0;
    }

    public function Header()
    {
        $logoProvince = __DIR__ . '/../../assets/images/logo_province.png';
        if (is_file($logoProvince)) {
            $this->Image($logoProvince, 12, 8, 22);
        }

        $qrMatrix = $GLOBALS['qrMatrix'] ?? [];
        $qrX = 162; $qrY = 8; $qrCell = 0.85;
        $this->DrawQRCode($qrMatrix, $qrX, $qrY, $qrCell);
        $qrSize = $this->QrSize($qrMatrix, $qrCell);
        $this->SetFont('Arial', '', 6);
        $this->SetXY($qrX - 2, $qrY + $qrSize + 1);
        $this->Cell(42, 3, pdfTxt('QR Code sécurisé'), 0, 0, 'C');

        $this->SetY(8);
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(0, 5, pdfTxt('REPUBLIQUE DEMOCRATIQUE DU CONGO'), 0, 1, 'C');
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 5, pdfTxt('PROVINCE DE LA TSHOPO'), 0, 1, 'C');
        $this->Cell(0, 5, pdfTxt('DIRECTION GENERALE DES RECETTES DE LA TSHOPO'), 0, 1, 'C');
        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 5, pdfTxt('SERVICE DE CONSTATATION'), 0, 1, 'C');
        $this->Ln(15);
    }

    public function Footer()
    {
        $this->SetY(-12);
        $this->SetFont('Arial', '', 7);
        $this->Cell(0, 4, pdfTxt('Page ' . $this->PageNo() . ' - Copyright ' . date('Y') . ' - cOllect_Pay, Tous droits réservés'), 0, 0, 'C');
    }

    public function NbLines($w, $txt)
    {
        $cw = &$this->CurrentFont['cw'];
        if ($w == 0) $w = $this->w - $this->rMargin - $this->x;
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', (string)$txt);
        $nb = strlen($s);
        if ($nb > 0 && $s[$nb - 1] == "\n") $nb--;
        $sep = -1; $i = 0; $j = 0; $l = 0; $nl = 1;
        while ($i < $nb) {
            $c = $s[$i];
            if ($c == "\n") { $i++; $sep = -1; $j = $i; $l = 0; $nl++; continue; }
            if ($c == ' ') $sep = $i;
            $l += $cw[$c] ?? 0;
            if ($l > $wmax) {
                if ($sep == -1) { if ($i == $j) $i++; }
                else { $i = $sep + 1; }
                $sep = -1; $j = $i; $l = 0; $nl++;
            } else $i++;
        }
        return $nl;
    }

    public function Section($title)
    {
        $this->SetFillColor(232, 232, 232);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(0, 7, pdfTxt($title), 1, 1, 'C', true);
    }

    public function InfoLine($label, $value, $labelW = 42, $valueW = 144)
    {
        $this->SetFont('Arial', 'B', 8);
        $this->Cell($labelW, 5.5, pdfTxt($label), 1, 0, 'L');
        $this->SetFont('Arial', '', 8);
        $this->Cell($valueW, 5.5, pdfTxt($value), 1, 1, 'L');
    }

    public function Row($data, $widths, $aligns, $lineH = 4.5)
    {
        $nb = 0;
        for ($i = 0; $i < count($data); $i++) {
            $nb = max($nb, $this->NbLines($widths[$i], pdfTxt((string)$data[$i])));
        }
        $h = $lineH * $nb;
        if ($this->GetY() + $h > $this->PageBreakTrigger) $this->AddPage($this->CurOrientation);
        for ($i = 0; $i < count($data); $i++) {
            $w = $widths[$i]; $a = $aligns[$i] ?? 'L';
            $x = $this->GetX(); $y = $this->GetY();
            $this->Rect($x, $y, $w, $h);
            $this->MultiCell($w, $lineH, pdfTxt((string)$data[$i]), 0, $a);
            $this->SetXY($x + $w, $y);
        }
        $this->Ln($h);
    }

    public function SummaryLine($label, $value, $bold = false)
    {
        $this->SetFont('Arial', $bold ? 'B' : '', $bold ? 9 : 8);
        $this->Cell(125, 6, pdfTxt($label), 1, 0, 'R');
        $this->Cell(61, 6, pdfTxt($value), 1, 1, 'L');
    }
}

$pdf = new CollectPayNTPDF('P', 'mm', 'A4');
$pdf->SetMargins(12, 12, 12);
$pdf->SetAutoPageBreak(true, 15);
$pdf->AddPage();

$pdf->SetFont('Arial', 'B', 13);
$pdf->Cell(0, 8, pdfTxt('NOTE DE TAXATION N° ' . $nt['numero_nt']), 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 6, pdfTxt('EXERCICE ' . ntText($nt['exercice'] ?? date('Y'))), 0, 1, 'C');
$pdf->Ln(2);

$pdf->Section('I. CONTRIBUABLE');
$pdf->InfoLine('Type', strtoupper(ntText($nt['type_personne'] ?? '-')));
$pdf->InfoLine('Nom / Raison sociale', ntName($nt));
$pdf->InfoLine('Adresse', ntText(($nt['ville'] ?? '') . ' / ' . ($nt['adresse'] ?? '')));
$pdf->InfoLine('NIF', ntText($nt['nif'] ?? '-'));
$pdf->InfoLine('N° RCCM / Patente', ntText($nt['rccm'] ?? '-'));
$pdf->InfoLine('Contacts', ntText($nt['telephone'] ?? '-'));
$pdf->InfoLine('Taux de change appliqué', ntExchangeText($pdo, $detailsNT));
$pdf->Ln(3);

$pdf->Section('II. BASE ET LIQUIDATION');
$pdf->SetFont('Arial', 'B', 7.5);
$pdf->SetFillColor(245, 245, 245);
$widths = [22, 67, 29, 24, 22, 22];
$headers = ['Période', 'Désignation', 'Qté / Valeur', 'Taux', 'Principal', 'TOTAL'];
foreach ($headers as $i => $h) {
    $pdf->Cell($widths[$i], 7, pdfTxt($h), 1, 0, 'C', true);
}
$pdf->Ln();

$pdf->SetFont('Arial', '', 7.2);
if (empty($detailsNT)) {
    $pdf->Cell(186, 8, pdfTxt('Aucun acte taxable constaté.'), 1, 1, 'L');
} else {
    foreach ($detailsNT as $d) {
        $devise = ntDeviseSource($d);
        $principalSource = ntPrincipalSource($d);
        $totalCDF = ntDetailTotalCDF($d);
        $qteValeur = ntQteValeurText($d);
        $pdf->Row([
            ntPeriodText($d, $nt['exercice'] ?? date('Y')),
            ntDesignation($d),
            $qteValeur,
            ntRateText($d),
            ntMoney0($principalSource, $devise),
            ntMoney0($totalCDF, 'CDF')
         ], $widths, ['L', 'L', 'L', 'L', 'L', 'L'], 4.2);

        $calculText = ntCalculationLines($d);
        if (!empty($calculText)) {
            $pdf->SetFont('Arial', '', 6.8);
            $pdf->MultiCell(186, 4.2, pdfTxt('Détail du calcul : ' . $calculText), 1, 'L');
            $pdf->SetFont('Arial', '', 7.2);
        }
    }
}

$pdf->Ln(2);
$pdf->SetFont('Arial', '', 8);
$pdf->SummaryLine('Principal dû', ntMoney0($totalActes, 'CDF'));
$pdf->SummaryLine("Pénalités d'assiette", ntMoney0($penaliteAssiette, 'CDF'));
$pdf->SummaryLine('Pénalités de recouvrement', ntMoney0($penaliteRecouvrement, 'CDF'));
$pdf->SummaryLine('TOTAL GENERAL', ntMoney0($totalGeneral, 'CDF'), true);

$pdf->Ln(3);
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(35, 7, pdfTxt('Nous disons :'), 1, 0, 'L');
$pdf->SetFont('Arial', '', 8);
$pdf->MultiCell(151, 7, pdfTxt(ucfirst(ntNumberToWords($totalGeneral)) . ' Franc Congolais'), 1, 'L');

$pdf->Ln(3);
$pdf->Section('III. OBSERVATION');
$pdf->SetFont('Arial', '', 8);
$obs = $nt['observation'] ?? $nt['observations'] ?? "La présente Note de Taxation constitue la constatation officielle de l'assiette et du montant dû. Elle sert de base à la liquidation et à l'émission des titres de perception.";
$pdf->MultiCell(186, 5.5, pdfTxt(ntText($obs)), 1, 'L');

$pdf->Ln(4);
$pdf->Section('IV. VALIDATION');
$pdf->SetFont('Arial', '', 8);
$pdf->InfoLine('Statut', strtoupper(ntText($nt['statut'] ?? 'BROUILLON')));
$pdf->InfoLine('Taxateur', ntText($nt['taxateur'] ?? '-'));
$pdf->InfoLine('Date de création', ntDateTime($nt['created_at'] ?? null));

$pdf->Ln(8);
$pdf->SetFont('Arial', '', 8);
$pdf->Cell(90, 20, pdfTxt('Signature du taxateur'), 1, 0, 'L');
$pdf->Cell(96, 20, pdfTxt('Sceau / Chef de constatation'), 1, 1, 'L');

$pdf->Ln(4);
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(0, 5, pdfTxt('GUICHET UNIQUE'), 0, 1, 'C');
$pdf->SetFont('Arial', '', 7.5);
$pdf->Cell(0, 5, pdfTxt('Délivrée le ' . date('d/m/Y H:i:s')), 0, 1, 'L');
$pdf->Cell(0, 5, pdfTxt('Document vérifiable par QR Code sécurisé'), 0, 1, 'L');

$fileName = 'NT_' . ntCleanFileName($nt['numero_nt']) . '.pdf';
cpCleanOutputBeforePdf();
$pdf->Output('I', $fileName);
exit;
?>
