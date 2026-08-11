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
    die("Numéro NP obligatoire.");
}

$stmt = $pdo->prepare("
    SELECT 
        np.*,
        nd.numero_nd,
        nd.total_exigible,
        nd.montant_acte AS nd_montant_acte,
        nd.montant_frais_admin AS nd_montant_frais_admin,
        nd.montant_frais_tech AS nd_montant_frais_tech,
        nd.penalite_assiette AS nd_penalite_assiette,
        nd.penalite_recouvrement AS nd_penalite_recouvrement,
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
    JOIN notes_debit nd ON np.note_debit_id = nd.id
    JOIN notes_taxation nt ON nd.note_taxation_id = nt.id
    JOIN contribuables c ON nt.contribuable_id = c.id
    LEFT JOIN users u ON np.user_ordonnateur_id = u.id
    WHERE np.numero_np = ?
    LIMIT 1
");
$stmt->execute([$numero]);
$np = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$np) {
    die("NP introuvable.");
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
$stmt->execute([$np['note_taxation_id']]);
$detailsNT = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    ORDER BY nb.id ASC
");
$stmt->execute([$np['id']]);
$banques = $stmt->fetchAll(PDO::FETCH_ASSOC);

function npTxt($value, $default = '-')
{
    $value = trim(preg_replace('/\s+/u', ' ', (string)($value ?? '')));
    return $value !== '' ? $value : $default;
}
function npUtf($value) { return pdfTxt(npTxt($value)); }
function npName($c)
{
    if (!empty($c['raison_sociale'])) return npTxt($c['raison_sociale']);
    return npTxt(($c['nom'] ?? '') . ' ' . ($c['postnom'] ?? '') . ' ' . ($c['prenom'] ?? ''));
}
function npDate($date)
{
    if (empty($date)) return '-';
    $t = strtotime($date);
    return $t ? date('d/m/Y', $t) : '-';
}
function npDateTime($date)
{
    if (empty($date)) return '-';
    $t = strtotime($date);
    return $t ? date('d/m/Y H:i:s', $t) : '-';
}
function npMoney($value, $devise = 'CDF')
{
    return number_format((float)$value, 2, ',', ' ') . ' ' . strtoupper($devise ?: 'CDF');
}
function npMoney0($value, $devise = 'CDF')
{
    $v = (float)$value;
    if (abs($v - round($v)) < 0.005) return number_format($v, 0, ',', ' ') . ' ' . strtoupper($devise ?: 'CDF');
    return npMoney($v, $devise);
}
function npNumber($value, $decimals = 2)
{
    $value = (float)$value;
    if (abs($value - round($value)) < 0.00001) return number_format($value, 0, ',', ' ');
    return number_format($value, $decimals, ',', ' ');
}
function npDeviseSource($d)
{
    return strtoupper($d['devise_source'] ?? $d['article_devise_base'] ?? 'CDF');
}
function npDetailTotalCDF($d)
{
    foreach (['total_ligne_cdf','montant_cdf','montant_total','total_ligne','montant_acte'] as $k) {
        if (isset($d[$k]) && $d[$k] !== '') return (float)$d[$k];
    }
    return 0;
}
function npJsonDetails($d)
{
    if (empty($d['details_calcul'])) return [];
    $json = json_decode((string)$d['details_calcul'], true);
    return is_array($json) ? $json : [];
}
function npPrincipalSource($d)
{
    $json = npJsonDetails($d);
    if (isset($json['principal_source']) && $json['principal_source'] !== '') return (float)$json['principal_source'];
    if (!empty($json['details']) && is_array($json['details'])) {
        foreach ($json['details'] as $line) {
            if (($line['type'] ?? '') === 'principal' && isset($line['montant_source'])) return (float)$line['montant_source'];
        }
    }
    $montantActe = (float)($d['montant_acte'] ?? 0);
    $devise = npDeviseSource($d);
    $taux = (float)($d['taux_change'] ?? 0);
    if ($devise === 'USD' && $taux > 1 && $montantActe > 0) return $montantActe / $taux;
    return $montantActe;
}
function npRateText($d)
{
    $mode = strtolower((string)($d['type_calcul'] ?? $d['mode_calcul'] ?? $d['article_mode_calcul'] ?? $d['article_type_taux'] ?? ''));
    $devise = npDeviseSource($d);
    if (isset($d['taux_irl']) && (float)$d['taux_irl'] > 0) return npNumber($d['taux_irl']) . ' % IRL';
    if (isset($d['taux_rl']) && (float)$d['taux_rl'] > 0) return npNumber($d['taux_rl']) . ' % RL';
    if (isset($d['taux_pourcentage']) && (float)$d['taux_pourcentage'] > 0) return npNumber($d['taux_pourcentage']) . ' %';
    $taux = (float)($d['article_taux_acte'] ?? 0);
    if ($taux <= 0) {
        $qte = max(1, (float)($d['quantite'] ?? 1));
        $principal = npPrincipalSource($d);
        $taux = $qte > 0 ? $principal / $qte : 0;
    }
    if ($taux <= 0) return '-';
    if (strpos($mode, 'pourcentage') !== false || in_array($mode, ['irl','rl'], true)) return npNumber($taux) . ' %';
    return npMoney0($taux, $devise);
}
function npBaseLabel($d)
{
    return npTxt($d['article_base_calcul_libelle'] ?? $d['unite_assiette'] ?? 'Base imposable');
}
function npBaseValue($d)
{
    foreach (['base_imposable','base_calcul','loyer_mensuel'] as $k) {
        if (isset($d[$k]) && $d[$k] !== '') return (float)$d[$k];
    }
    return 0;
}
function npIsMonetaryBase($d)
{
    $label = strtoupper(npTxt($d['article_base_calcul_libelle'] ?? $d['unite_assiette'] ?? ''));
    $mode = strtolower((string)($d['mode_calcul'] ?? $d['article_mode_calcul'] ?? $d['article_type_taux'] ?? ''));
    if (strpos($mode, 'pourcentage') !== false || in_array($mode, ['irl','rl'], true)) return true;
    foreach (['LOYER','MONTANT','REVENU','BASE IMPOSABLE','VALEUR MARCHANDE'] as $mot) {
        if (strpos($label, $mot) !== false) return true;
    }
    return false;
}
function npQteValeurText($d)
{
    $devise = npDeviseSource($d);
    $base = npBaseValue($d);
    $qte = (float)($d['quantite'] ?? 1);
    $baseText = npIsMonetaryBase($d) ? npMoney0($base, $devise) : npNumber($base);
    return npBaseLabel($d) . " :\n" . $baseText . "\nQté : " . npNumber($qte);
}
function npPeriodText($d, $exercice)
{
    $period = $d['periode_libelle'] ?? $d['periodicite_info'] ?? $d['periodicite'] ?? '';
    if (empty($period)) $period = $exercice ?: date('Y');
    $mois = $d['mois_concernes'] ?? '';
    return npTxt($period . (!empty($mois) ? ' / ' . $mois : ''));
}
function npDesignation($d)
{
    $parts = [];
    if (!empty($d['nom_service'])) $parts[] = strtoupper($d['nom_service']);
    if (!empty($d['secteur'])) $parts[] = strtoupper($d['secteur']);
    $parts[] = strtoupper($d['libelle_acte'] ?? $d['nature_acte'] ?? $d['acte_generateur'] ?? $d['fait_generateur'] ?? '-');
    if (!empty($d['article_libelle_taux'])) $parts[] = $d['article_libelle_taux'];
    if (!empty($d['code_article'])) $parts[] = 'Code : ' . $d['code_article'];
    return npTxt(implode("\n", $parts));
}
function npCalculationLines($d)
{
    $devise = npDeviseSource($d);
    $tauxChange = (float)($d['taux_change'] ?? 0);
    $qte = (float)($d['quantite'] ?? 1);
    $tauxActe = (float)($d['article_taux_acte'] ?? 0);
    if ($tauxActe <= 0 && isset($d['taux_pourcentage']) && (float)$d['taux_pourcentage'] > 0) $tauxActe = (float)$d['taux_pourcentage'];
    if ($tauxActe <= 0 && $qte > 0) $tauxActe = npPrincipalSource($d) / $qte;
    $principalSource = npPrincipalSource($d);
    $principalCdf = (float)($d['montant_acte'] ?? 0);
    if ($principalCdf <= 0) $principalCdf = ($devise === 'USD' && $tauxChange > 1) ? $principalSource * $tauxChange : $principalSource;
    $libelle = npTxt($d['libelle_acte'] ?? $d['nature_acte'] ?? 'Acte taxable');

    $lines = [];
    if ($qte > 0 && $tauxActe > 0) {
        if ($devise === 'USD') {
            $line = $libelle . ' : ' . npNumber($qte) . ' × ' . npNumber($tauxActe) . ' USD = ' . npMoney($principalSource, 'USD');
            if ($tauxChange > 1) $line .= "\nou soit " . npMoney($principalCdf, 'CDF');
        } else {
            $line = $libelle . ' : ' . npNumber($qte) . ' × ' . npMoney0($tauxActe, 'CDF') . ' = ' . npMoney($principalCdf, 'CDF');
        }
        $lines[] = $line;
    } else {
        $lines[] = $libelle . ' : ' . npMoney($principalCdf, 'CDF');
    }

    $faCdf = (float)($d['montant_frais_admin'] ?? 0);
    $ftCdf = (float)($d['montant_frais_tech'] ?? 0);
    $faUsd = ($tauxChange > 1 && $faCdf > 0) ? $faCdf / $tauxChange : (float)($d['article_frais_administratif'] ?? 0);
    $ftUsd = ($tauxChange > 1 && $ftCdf > 0) ? $ftCdf / $tauxChange : (float)($d['article_frais_technique'] ?? 0);

    if ($faCdf > 0) {
        $lines[] = ($tauxChange > 1 && $faUsd > 0)
            ? 'Frais administratif : ' . npNumber($faUsd) . ' USD × ' . npNumber($tauxChange, 0) . ' = ' . npMoney($faCdf, 'CDF')
            : 'Frais administratif : ' . npMoney($faCdf, 'CDF');
    }
    if ($ftCdf > 0) {
        $lines[] = ($tauxChange > 1 && $ftUsd > 0)
            ? 'Frais technique : ' . npNumber($ftUsd) . ' USD × ' . npNumber($tauxChange, 0) . ' = ' . npMoney($ftCdf, 'CDF')
            : 'Frais technique : ' . npMoney($ftCdf, 'CDF');
    }
    return implode("\n", $lines);
}
function npExchangeText($pdo, $details)
{
    $tauxTrouve = 0;
    foreach ($details as $d) {
        $taux = (float)($d['taux_change'] ?? 0);
        if ($taux > $tauxTrouve) $tauxTrouve = $taux;
    }
    if ($tauxTrouve <= 1) {
        try {
            $stmt = $pdo->query("SELECT taux FROM taux_change_officiel WHERE devise='USD' AND actif=1 ORDER BY date_application DESC, id DESC LIMIT 1");
            $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
            if ($row && (float)$row['taux'] > 1) $tauxTrouve = (float)$row['taux'];
        } catch (Exception $e) {}
    }
    return $tauxTrouve > 1 ? number_format($tauxTrouve, 0, ',', ' ') : '-';
}
function npTotalPrincipal($details) { $s=0; foreach($details as $d){ $s += (float)($d['montant_acte'] ?? 0); } return $s; }
function npTotalFA($details) { $s=0; foreach($details as $d){ $s += (float)($d['montant_frais_admin'] ?? 0); } return $s; }
function npTotalFT($details) { $s=0; foreach($details as $d){ $s += (float)($d['montant_frais_tech'] ?? 0); } return $s; }
function npCleanFileName($text) { return preg_replace('/[^A-Za-z0-9_\-]/', '_', (string)$text); }

function npNumberToWordsBelow1000($n)
{
    $units = ['', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf', 'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize'];
    $tens = [2=>'vingt',3=>'trente',4=>'quarante',5=>'cinquante',6=>'soixante'];
    $n=(int)$n;
    if ($n < 17) return $units[$n];
    if ($n < 20) return 'dix-' . $units[$n-10];
    if ($n < 70) { $ten=intdiv($n,10); $u=$n%10; if($u==0)return $tens[$ten]; if($u==1)return $tens[$ten].' et un'; return $tens[$ten].'-'.$units[$u]; }
    if ($n < 80) return 'soixante-' . npNumberToWordsBelow1000($n-60);
    if ($n == 80) return 'quatre-vingts';
    if ($n < 100) return 'quatre-vingt-' . npNumberToWordsBelow1000($n-80);
    $hund=intdiv($n,100); $r=$n%100; $hText=$hund==1?'cent':$units[$hund].' cent'; if($r==0)return $hText.($hund>1?'s':''); return $hText.' '.npNumberToWordsBelow1000($r);
}
function npNumberToWords($n)
{
    $n=(int)round($n); if($n===0)return 'zéro'; $parts=[];
    $milliards=intdiv($n,1000000000); $n%=1000000000;
    $millions=intdiv($n,1000000); $n%=1000000;
    $mille=intdiv($n,1000); $n%=1000;
    if($milliards) $parts[] = ($milliards==1?'un':npNumberToWordsBelow1000($milliards)).' milliard'.($milliards>1?'s':'');
    if($millions) $parts[] = ($millions==1?'un':npNumberToWordsBelow1000($millions)).' million'.($millions>1?'s':'');
    if($mille) $parts[] = ($mille==1?'mille':npNumberToWordsBelow1000($mille).' mille');
    if($n) $parts[] = npNumberToWordsBelow1000($n);
    return implode(' ', $parts);
}
function npMontantEnLettres($amount)
{
    return ucfirst(npNumberToWords($amount)) . ' Franc Congolais';
}

$montantQR = (float)($np['solde_restant'] ?? $np['montant_initial'] ?? 0);
$qrContent = buildEncryptedQrContent($pdo, 'NP', $np['numero_np'], $montantQR);
$GLOBALS['qrMatrix'] = QRcode::text($qrContent, false, QR_ECLEVEL_L, 0, 1);

class NPProPDF extends FPDF
{
    public $widths = [];
    public $aligns = [];

    function DrawQRCode($matrix, $x, $y, $size = 0.90)
    {
        if (!is_array($matrix)) return;
        $this->SetFillColor(0,0,0);
        foreach ($matrix as $rowIndex => $row) {
            for ($colIndex = 0; $colIndex < strlen($row); $colIndex++) {
                if ($row[$colIndex] === '1') $this->Rect($x + $colIndex*$size, $y + $rowIndex*$size, $size, $size, 'F');
            }
        }
    }
    function Header()
    {
        if (file_exists('../assets/images/logo_province.png')) $this->Image('../assets/images/logo_province.png', 12, 8, 22);
        $this->DrawQRCode($GLOBALS['qrMatrix'] ?? [], 164, 8, 0.88);
        $this->SetFont('Arial','',5);
        $this->SetXY(160, 41);
        $this->Cell(42,3,pdfTxt('QR Code sécurisé'),0,0,'C');

        $this->SetY(8);
        $this->SetFont('Arial','B',10.5);
        $this->Cell(0,5,pdfTxt('REPUBLIQUE DEMOCRATIQUE DU CONGO'),0,1,'C');
        $this->Cell(0,5,pdfTxt('PROVINCE DE LA TSHOPO'),0,1,'C');
        $this->SetFont('Arial','B',9.5);
        $this->Cell(0,5,pdfTxt('DIRECTION GENERALE DES RECETTES DE LA TSHOPO'),0,1,'C');
        $this->Cell(0,5,pdfTxt('DIRECTION IMPOT / KISANGANI'),0,1,'C');
        $this->Ln(15);
    }
    function Footer()
    {
        $this->SetY(-11);
        $this->SetFont('Arial','',7);
        $this->Cell(0,5,pdfTxt('Page '.$this->PageNo().' - Copyright '.date('Y').' - cOllect_Pay, Tous droits réservés'),0,0,'C');
    }
    function Section($title)
    {
        $this->Ln(2);
        $this->SetFillColor(235,235,235);
        $this->SetFont('Arial','B',8.5);
        $this->Cell(0,6,pdfTxt($title),1,1,'L',true);
    }
    function LabelValue($label, $value, $wLabel=42, $wValue=144)
    {
        $this->SetFont('Arial','',8);
        $this->Cell($wLabel,6,pdfTxt($label),1,0,'L');
        $this->Cell($wValue,6,pdfTxt($value),1,1,'L');
    }
    function SetWidths($w) { $this->widths = $w; }
    function SetAligns($a) { $this->aligns = $a; }
    function Row($data, $fill = false)
    {
        $nb = 0;
        for ($i=0; $i<count($data); $i++) $nb = max($nb, $this->NbLines($this->widths[$i], pdfTxt((string)$data[$i])));
        $h = 4.5 * $nb + 2;
        $this->CheckPageBreak($h);
        for ($i=0; $i<count($data); $i++) {
            $w = $this->widths[$i];
            $a = isset($this->aligns[$i]) ? $this->aligns[$i] : 'L';
            $x = $this->GetX();
            $y = $this->GetY();
            $this->Rect($x, $y, $w, $h, $fill ? 'DF' : 'D');
            $this->SetXY($x+1, $y+1);
            $this->MultiCell($w-2, 4.5, pdfTxt((string)$data[$i]), 0, $a);
            $this->SetXY($x+$w, $y);
        }
        $this->Ln($h);
    }
    function CheckPageBreak($h)
    {
        if ($this->GetY() + $h > $this->PageBreakTrigger) $this->AddPage($this->CurOrientation);
    }
    function NbLines($w, $txt)
    {
        $cw = &$this->CurrentFont['cw'];
        if ($w == 0) $w = $this->w - $this->rMargin - $this->x;
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', (string)$txt);
        $nb = strlen($s);
        if ($nb > 0 && $s[$nb-1] == "\n") $nb--;
        $sep = -1; $i = 0; $j = 0; $l = 0; $nl = 1;
        while ($i < $nb) {
            $c = $s[$i];
            if ($c == "\n") { $i++; $sep = -1; $j = $i; $l = 0; $nl++; continue; }
            if ($c == ' ') $sep = $i;
            $l += $cw[$c] ?? 0;
            if ($l > $wmax) {
                if ($sep == -1) { if ($i == $j) $i++; }
                else $i = $sep + 1;
                $sep = -1; $j = $i; $l = 0; $nl++;
            } else $i++;
        }
        return $nl;
    }
}

$pdf = new NPProPDF('P','mm','A4');
$pdf->SetMargins(12, 12, 12);
$pdf->SetAutoPageBreak(true, 13);
$pdf->AddPage();

$totalAPayer = (float)($np['solde_restant'] ?? $np['montant_initial'] ?? $np['total_exigible'] ?? 0);
$principal = npTotalPrincipal($detailsNT);
$fa = npTotalFA($detailsNT);
$ft = npTotalFT($detailsNT);
$pa = (float)($np['penalite_assiette'] ?? $np['nd_penalite_assiette'] ?? 0);
$pr = (float)($np['penalite_recouvrement'] ?? $np['nd_penalite_recouvrement'] ?? 0);
$tauxChangeText = npExchangeText($pdo, $detailsNT);

$pdf->SetFont('Arial','B',12);
$pdf->Cell(0,7,pdfTxt('NOTE DE PERCEPTION N° ' . $np['numero_np']),0,1,'C');
$pdf->SetFont('Arial','B',9.5);
$pdf->Cell(0,6,pdfTxt('EXERCICE ' . ($np['exercice'] ?? date('Y'))),0,1,'C');

$pdf->Section('I. Contribuable');
$pdf->LabelValue('Type', strtoupper(npTxt($np['type_personne'] ?? '-')));
$pdf->LabelValue('Nom / Raison sociale', npName($np));
$pdf->LabelValue('Adresse', npTxt(trim(($np['ville'] ?? '') . ' / ' . ($np['adresse'] ?? ''))));
$pdf->LabelValue('NIF', npTxt($np['nif'] ?? '-'));
$pdf->LabelValue('N° RCCM / Patente', npTxt($np['rccm'] ?? '-'));
$pdf->LabelValue('Contacts', npTxt($np['telephone'] ?? '-'));
$pdf->LabelValue('Taux de change appliqué', $tauxChangeText . ($tauxChangeText !== '-' ? ' CDF/USD' : ''));

$pdf->Section('II. Base et liquidation');
$pdf->SetFont('Arial','B',7.5);
$pdf->SetFillColor(245,245,245);
$pdf->SetWidths([22, 67, 25, 23, 24, 25]);
$pdf->SetAligns(['L','L','L','R','R','R']);
$pdf->Row(['Période','Désignation','Qté/Valeur','Taux','Principal','TOTAL'], true);
$pdf->SetFont('Arial','',7.2);

if (empty($detailsNT)) {
    $pdf->SetWidths([186]);
    $pdf->SetAligns(['L']);
    $pdf->Row(['Aucun détail disponible.']);
} else {
    foreach ($detailsNT as $d) {
        $pdf->SetWidths([22, 67, 25, 23, 24, 25]);
        $pdf->SetAligns(['L','L','L','R','R','R']);
        $pdf->Row([
            npPeriodText($d, $np['exercice'] ?? date('Y')),
            npDesignation($d),
            npQteValeurText($d),
            npRateText($d),
            npMoney0((float)($d['montant_acte'] ?? 0), 'CDF'),
            npMoney0(npDetailTotalCDF($d), 'CDF')
        ]);

        $pdf->SetWidths([186]);
        $pdf->SetAligns(['L']);
        $pdf->SetFont('Arial','',7.2);
        $pdf->Row(['Détail : ' . npCalculationLines($d)]);
    }
}

$pdf->Ln(1);
$pdf->SetFont('Arial','',8);
$pdf->Cell(132,6,pdfTxt('Principal dû'),1,0,'L');
$pdf->Cell(54,6,pdfTxt(npMoney0($principal, 'CDF')),1,1,'R');
$pdf->Cell(132,6,pdfTxt('Frais administratifs'),1,0,'L');
$pdf->Cell(54,6,pdfTxt(npMoney0($fa, 'CDF')),1,1,'R');
$pdf->Cell(132,6,pdfTxt('Frais techniques'),1,0,'L');
$pdf->Cell(54,6,pdfTxt(npMoney0($ft, 'CDF')),1,1,'R');
$pdf->Cell(132,6,pdfTxt("Pénalités d'assiette"),1,0,'L');
$pdf->Cell(54,6,pdfTxt(npMoney0($pa, 'CDF')),1,1,'R');
$pdf->Cell(132,6,pdfTxt('Pénalités de recouvrement'),1,0,'L');
$pdf->Cell(54,6,pdfTxt(npMoney0($pr, 'CDF')),1,1,'R');
$pdf->SetFont('Arial','B',8.5);
$pdf->Cell(132,7,pdfTxt('TOTAL GENERAL'),1,0,'L');
$pdf->Cell(54,7,pdfTxt(npMoney0($totalAPayer, 'CDF')),1,1,'R');
$pdf->SetFont('Arial','',8);
$pdf->MultiCell(186,6,pdfTxt('Nous disons : ' . npMontantEnLettres($totalAPayer)),1,'L');

$pdf->Section('III. Comptes de paiement');
$pdf->SetFont('Arial','B',7.5);
$pdf->SetWidths([43, 57, 58, 28]);
$pdf->SetAligns(['L','L','L','R']);
$pdf->Row(['Banque','Compte','Intitulé','Montant'], true);
$pdf->SetFont('Arial','',7.2);
if (empty($banques)) {
    $pdf->SetWidths([186]);
    $pdf->SetAligns(['L']);
    $pdf->Row(['Aucun compte bancaire affecté à cette Note de Perception.']);
} else {
    foreach ($banques as $b) {
        $pdf->SetWidths([43, 57, 58, 28]);
        $pdf->SetAligns(['L','L','L','R']);
        $pdf->Row([
            npTxt($b['banque'] ?? '-'),
            npTxt($b['numero_compte'] ?? '-') . ' / ' . npTxt($b['devise'] ?? 'CDF'),
            npTxt($b['intitule_compte'] ?? '-'),
            npMoney0($b['montant_affecte'] ?? 0, 'CDF')
        ]);
    }
}

$pdf->Section('IV. Observation');
$observation = npTxt($np['observation'] ?? $np['motif'] ?? 'Veuillez exiger votre acquis libératoire (quittance informatisée) lors du paiement de cette Note de Perception.');
$pdf->SetFont('Arial','',8);
$pdf->MultiCell(186,6,pdfTxt($observation),1,'L');

$pdf->Section('V. Validation');
$pdf->LabelValue('Référence NT', npTxt($np['numero_nt'] ?? '-'));
$pdf->LabelValue('Référence ND', npTxt($np['numero_nd'] ?? '-'));
$pdf->LabelValue('Type NP', strtoupper(npTxt($np['type_np'] ?? 'GLOBALE')));
$pdf->LabelValue('Statut', strtoupper(npTxt($np['statut'] ?? '-')));
$pdf->LabelValue('Ordonnateur', npTxt($np['ordonnateur'] ?? '-'));
$pdf->LabelValue('Date émission', npDateTime($np['date_emission'] ?? null));
$pdf->LabelValue('Date échéance', npDate($np['date_echeance'] ?? null));

$pdf->Ln(6);
$pdf->SetFont('Arial','',8);
$pdf->Cell(90,18,pdfTxt('Signature déclarant'),1,0,'C');
$pdf->Cell(96,18,pdfTxt('Sceau / Ordonnateur'),1,1,'C');

$pdf->Ln(3);
$pdf->SetFont('Arial','B',8);
$pdf->Cell(0,5,pdfTxt('GUICHET UNIQUE'),0,1,'L');
$pdf->SetFont('Arial','',7.5);
$pdf->Cell(0,5,pdfTxt('Délivrée le ' . date('d/m/Y H:i:s')),0,1,'L');
$pdf->Cell(0,5,pdfTxt('Document vérifiable par QR Code sécurisé'),0,1,'L');

$fileName = 'NP_' . npCleanFileName($np['numero_np']) . '.pdf';
cpCleanOutputBeforePdf();
$pdf->Output('I', $fileName);
exit;
?>
