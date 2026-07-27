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

$numero = $_GET['numero'] ?? null;
if (!$numero) { die("Numéro quittance manquant."); }

$stmt = $pdo->prepare("\n    SELECT \n        q.*,\n        ap.reference_type,\n        ap.reference_id,\n        ap.montant_du,\n        ap.montant_paye AS montant_apure,\n        ap.penalite_validee,\n        ap.solde_restant AS solde_apurement,\n        ap.statut AS statut_apurement,\n        ap.date_apurement,\n        np.numero_np,\n        np.type_np,\n        np.np_mere_id,\n        np.montant_initial,\n        np.montant_paye,\n        np.solde_restant,\n        np.date_echeance,\n        np.date_emission,\n        nd.numero_nd,\n        nt.id AS note_taxation_id,\n        nt.numero_nt,\n        nt.exercice,\n        c.type_personne,\n        c.raison_sociale,\n        c.nom,\n        c.postnom,\n        c.prenom,\n        c.nif,\n        c.rccm,\n        c.telephone,\n        c.adresse,\n        c.ville,\n        u.nom AS nom_comptable\n    FROM quittances q\n    JOIN apurements ap ON q.apurement_id = ap.id\n    JOIN notes_perception np ON ap.reference_id = np.id\n    JOIN notes_debit nd ON np.note_debit_id = nd.id\n    JOIN notes_taxation nt ON nd.note_taxation_id = nt.id\n    JOIN contribuables c ON nt.contribuable_id = c.id\n    LEFT JOIN users u ON q.user_comptable_id = u.id\n    WHERE q.numero_quittance = ?\n    LIMIT 1\n");
$stmt->execute([$numero]);
$q = $stmt->fetch();
if (!$q) { die("Quittance introuvable."); }

function qtCleanFile($text){ return preg_replace('/[^A-Za-z0-9_\-]/','_', (string)$text); }
function qtName($r){ return !empty($r['raison_sociale']) ? $r['raison_sociale'] : trim(($r['nom']??'').' '.($r['postnom']??'').' '.($r['prenom']??'')); }
function qtDate($date, $withTime=false){ if(empty($date)) return '-'; return date($withTime?'d/m/Y H:i:s':'d/m/Y', strtotime($date)); }
function qtMoney($v, $dec=0){ return number_format((float)$v, $dec, ',', ' ') . ' FC'; }
function qtMoneyCdf($v, $dec=0){ return number_format((float)$v, $dec, ',', ' ') . ' CDF'; }
function qtFit($txt, $max=90){
    $txt = trim(preg_replace('/\s+/', ' ', (string)$txt));
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($txt, 'UTF-8') > $max ? mb_substr($txt, 0, max(0, $max - 3), 'UTF-8') . '...' : $txt;
    }
    return strlen($txt) > $max ? substr($txt, 0, max(0, $max - 3)) . '...' : $txt;
}
function qtShortActivity($txt){ return qtFit($txt, 58); }
function qtCellBlock($pdf, $x, $y, $w, $label, $value, $h=22){
    $pdf->Rect($x, $y, $w, $h);
    $pdf->SetXY($x + 2, $y + 2);
    $pdf->SetFont('Arial', '', 7);
    $pdf->MultiCell($w - 4, 3.6, pdfTxt($label), 0, 'L');
    $pdf->SetX($x + 2);
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->MultiCell($w - 4, 4.1, pdfTxt(qtFit($value, 85)), 0, 'L');
}

function qtNumberToWordsFr($n){
    $n = (int)round($n);
    if (function_exists('nombreEnLettres')) return nombreEnLettres($n);
    if (function_exists('montantEnLettres')) return montantEnLettres($n);
    $u=['','un','deux','trois','quatre','cinq','six','sept','huit','neuf','dix','onze','douze','treize','quatorze','quinze','seize'];
    $d=[20=>'vingt',30=>'trente',40=>'quarante',50=>'cinquante',60=>'soixante',80=>'quatre-vingt'];
    $under100=function($x) use (&$under100,$u,$d){
        if($x<17) return $u[$x];
        if($x<20) return 'dix-'.$u[$x-10];
        if($x<70){ $ten=intdiv($x,10)*10; $r=$x%10; return $d[$ten].($r?($r==1?' et un':'-'.$u[$r]):''); }
        if($x<80) return 'soixante-'.($under100($x-60));
        $r=$x-80; return 'quatre-vingt'.($r?'-'.$under100($r):'s');
    };
    $under1000=function($x) use (&$under1000,$under100){
        if($x<100) return $under100($x);
        $c=intdiv($x,100); $r=$x%100;
        $s=($c==1?'cent':$under100($c).' cent');
        if($r==0 && $c>1) $s.='s';
        return $s.($r?' '.$under100($r):'');
    };
    if($n===0) return 'zéro';
    $parts=[];
    $m=intdiv($n,1000000); $n%=1000000;
    $k=intdiv($n,1000); $n%=1000;
    if($m) $parts[]=$under1000($m).' million'.($m>1?'s':'');
    if($k) $parts[]=($k==1?'mille':$under1000($k).' mille');
    if($n) $parts[]=$under1000($n);
    return ucfirst(implode(' ', $parts));
}

$npIds = [(int)$q['reference_id']];
if (($q['type_np'] ?? '') === 'globale') {
    $stmt = $pdo->prepare("SELECT id FROM notes_perception WHERE np_mere_id = ? AND type_np = 'fractionnee' ORDER BY numero_tranche ASC, id ASC");
    $stmt->execute([(int)$q['reference_id']]);
    foreach($stmt->fetchAll() as $r){ $npIds[]=(int)$r['id']; }
}
$in = implode(',', array_fill(0, count($npIds), '?'));

$stmt = $pdo->prepare("\n    SELECT numero_np, date_emission, date_echeance, penalite_recouvrement, penalite_assiette, montant_initial, solde_restant, type_np, numero_tranche\n    FROM notes_perception\n    WHERE id IN ($in)\n    ORDER BY CASE WHEN type_np='globale' THEN 0 ELSE 1 END, numero_tranche ASC, id ASC\n");
$stmt->execute($npIds);
$references = $stmt->fetchAll();

$stmt = $pdo->prepare("\n    SELECT p.*, u.nom AS nom_comptable\n    FROM paiements p\n    LEFT JOIN users u ON p.user_comptable_id = u.id\n    WHERE p.note_perception_id IN ($in)\n    ORDER BY p.date_paiement ASC, p.id ASC\n");
$stmt->execute($npIds);
$paiements = $stmt->fetchAll();

$stmt = $pdo->prepare("\n    SELECT d.*, ab.nature_acte, ab.secteur\n    FROM notes_taxation_details d\n    LEFT JOIN articles_budgetaires ab ON d.article_id = ab.id\n    WHERE d.note_taxation_id = ?\n    ORDER BY d.id ASC\n");
$stmt->execute([(int)$q['note_taxation_id']]);
$details = $stmt->fetchAll();
$motifParts=[];
foreach($details as $d){ $motifParts[] = $d['libelle_acte'] ?? $d['nature_acte'] ?? $d['acte_generateur'] ?? ''; }
$motifFull = strtoupper(trim(implode(' / ', array_filter($motifParts))));
$motif = qtFit($motifFull, 165);
$activite = qtShortActivity($motifFull ?: 'PAIEMENT DE LA NOTE DE PERCEPTION');

$montantAcquitte = (float)($q['montant_acquitte'] ?? $q['montant_apure'] ?? $q['montant_paye'] ?? 0);
$token = getOrCreateDocumentToken($pdo, 'QT', $q['numero_quittance'], $montantAcquitte);
$qrContent = buildEncryptedQrContent($pdo, 'QT', $q['numero_quittance'], $montantAcquitte);
$GLOBALS['qrMatrix'] = QRcode::text($qrContent, false, QR_ECLEVEL_L, 0, 1);

class QuittanceLuxPDF extends FPDF
{
    function DrawQRCode($matrix, $x, $y, $size=0.72){
        if(!is_array($matrix)) return;
        $this->SetFillColor(0,0,0);
        foreach($matrix as $ri=>$row){
            for($ci=0;$ci<strlen($row);$ci++){
                if($row[$ci]==='1') $this->Rect($x+$ci*$size,$y+$ri*$size,$size,$size,'F');
            }
        }
    }
    function Header(){
        if(file_exists("../assets/images/logo_province.png")) $this->Image("../assets/images/logo_province.png",10,8,24);
        $this->DrawQRCode($GLOBALS['qrMatrix'] ?? [], 168, 7, 0.72);
        $this->SetY(9);
        $this->SetFont('Arial','',10);
        $this->Cell(0,5,pdfTxt('PROVINCE DE LA TSHOPO'),0,1,'C');
        $this->SetFont('Arial','B',13);
        $this->Cell(0,6,pdfTxt('DIRECTION GENERALE DES RECETTES DE LA TSHOPO'),0,1,'C');
        $this->SetFont('Arial','B',10);
        $this->Cell(0,5,pdfTxt('DIRECTION GENERALE'),0,1,'C');
        $this->Ln(14);
    }
    function Footer(){
        $this->SetY(-12);
        $this->SetFont('Arial','',7);
        $this->Cell(0,5,pdfTxt('Copyright '.date('Y').' - cOllect_Pay, Tout droit réservé'),0,0,'L');
    }
    function Section($title){
        $this->Ln(2);
        $this->SetFont('Arial','B',9);
        $this->Cell(0,6,pdfTxt($title),0,1,'L');
    }
    function SmallCell($w,$h,$txt,$border=1,$ln=0,$align='L',$bold=false){
        $this->SetFont('Arial',$bold?'B':'',8);
        $this->Cell($w,$h,pdfTxt((string)$txt),$border,$ln,$align);
    }
}

$pdf = new QuittanceLuxPDF('P','mm','A4');
$pdf->SetMargins(10,10,10);
$pdf->SetAutoPageBreak(true,14);
$pdf->AddPage();

$pdf->SetFont('Arial','B',14);
$pdf->Cell(0,8,pdfTxt('QUITTANCE INFORMATISEE N° '.$q['numero_quittance']),0,1,'C');
$pdf->SetFont('Arial','B',11);
$pdf->Cell(0,6,pdfTxt('EXERCICE '.($q['exercice'] ?? date('Y'))),0,1,'C');
$pdf->Ln(4);

$pdf->Section('I. Contribuable');

// Bloc contribuable en 3 colonnes avec MultiCell pour empêcher tout débordement.
$x = $pdf->GetX();
$y = $pdf->GetY();
$h = 28;
$w1 = 94;
$w2 = 46;
$w3 = 50;

$typePers = strtolower((string)($q['type_personne'] ?? '')) === 'morale' ? 'Personne Morale' : 'Personne Physique';
$identite = $typePers . "\n" . qtName($q) . "\n" . ($q['adresse'] ?? '-') . "\nN° RCCM / PATENTE : " . ($q['rccm'] ?? '-');
$contacts = "Contacts :\n" . ($q['telephone'] ?? '-') . "\nNIF : " . ($q['nif'] ?? 'NON ATTRIBUE');
$bien = "Bien / Activité :\n" . $activite . "\n" . trim(($q['ville'] ?? '') . ' / ' . ($q['adresse'] ?? '-'));

qtCellBlock($pdf, $x, $y, $w1, '', $identite, $h);
qtCellBlock($pdf, $x + $w1, $y, $w2, '', $contacts, $h);
qtCellBlock($pdf, $x + $w1 + $w2, $y, $w3, '', $bien, $h);
$pdf->SetY($y + $h + 4);

$pdf->Section('II. Références');
$pdf->SetFont('Arial','B',8);
$pdf->Cell(25,6,pdfTxt('Date'),1,0,'C');
$pdf->Cell(55,6,pdfTxt('Numéro'),1,0,'C');
$pdf->Cell(35,6,pdfTxt('Echéance'),1,0,'C');
$pdf->Cell(35,6,pdfTxt('Pénalités'),1,0,'R');
$pdf->Cell(40,6,pdfTxt('Montant'),1,1,'R');
$pdf->SetFont('Arial','B',9);
foreach($references as $r){
    $pen = (float)($r['penalite_recouvrement'] ?? 0) + (float)($r['penalite_assiette'] ?? 0);
    $pdf->Cell(25,8,pdfTxt(qtDate($r['date_emission'] ?? $q['date_emission'] ?? $q['date_apurement'] ?? null)),1,0,'C');
    $pdf->Cell(55,8,pdfTxt($r['numero_np']),1,0,'L');
    $pdf->Cell(35,8,pdfTxt(qtDate($r['date_echeance'] ?? null)),1,0,'C');
    $pdf->Cell(35,8,pdfTxt(qtMoney($pen,3)),1,0,'R');
    $pdf->Cell(40,8,pdfTxt(qtMoney($r['montant_initial'] ?? 0,3)),1,1,'R');
}

$pdf->Section('III. Paiements');
$pdf->SetFont('Arial','B',8);
$pdf->Cell(25,6,pdfTxt('Date'),1,0,'C');
$pdf->Cell(88,6,pdfTxt('Compte crédité'),1,0,'C');
$pdf->Cell(37,6,pdfTxt('Ref. Banque'),1,0,'C');
$pdf->Cell(40,6,pdfTxt('Montant'),1,1,'R');
$pdf->SetFont('Arial','B',8);
foreach($paiements as $p){
    $compte = trim(($p['banque'] ?? '').' '.(!empty($p['numero_compte'])?'('.$p['numero_compte'].' / '.($p['devise'] ?? '').')':''));
    if($compte==='') $compte = $p['compte_credite'] ?? '-';
    $montantTxt = number_format((float)$p['montant_paye'],0,',',' ') . ' ' . ($p['devise'] ?? 'CDF');
    $pdf->Cell(25,6,pdfTxt(qtDate($p['date_paiement'] ?? $p['created_at'] ?? null)),1,0,'C');
    $pdf->Cell(88,6,pdfTxt(qtFit($compte,54)),1,0,'L');
    $pdf->Cell(37,6,pdfTxt(qtFit($p['reference_transaction'] ?? '-',22)),1,0,'C');
    $pdf->Cell(40,6,pdfTxt($montantTxt),1,1,'R');
}
if(empty($paiements)){
    $pdf->Cell(190,7,pdfTxt('Aucun paiement trouvé.'),1,1,'C');
}

$pdf->Ln(3);
$pdf->SetFont('Arial','B',15);
$pdf->Cell(120,11,pdfTxt('MONTANT ACQUITTE'),1,0,'L');
$pdf->Cell(70,11,pdfTxt(qtMoney($montantAcquitte,0)),1,1,'R');
$pdf->SetFont('Arial','',8);
$pdf->MultiCell(0,5,pdfTxt('Nous disons : '.qtNumberToWordsFr($montantAcquitte).' Franc Congolais'),0,'L');

$pdf->Ln(3);
$pdf->SetFont('Arial','',8);
$pdf->Cell(0,5,pdfTxt('Motif :'),0,1,'L');
$pdf->SetFont('Arial','B',8);
$pdf->MultiCell(185,4.5,pdfTxt($motif ?: 'PAIEMENT DE LA NOTE DE PERCEPTION'),0,'L');

$pdf->Ln(8);
$pdf->SetFont('Arial','B',8);
$pdf->Cell(95,5,pdfTxt('Timbre : '.($token ? substr($token,0,8) : '-')),0,0,'L');
$pdf->Cell(95,5,pdfTxt('GUICHET UNIQUE'),0,1,'L');
$pdf->SetFont('Arial','',8);
$pdf->Cell(95,5,pdfTxt('Accusé reception'),0,0,'L');
$pdf->Cell(95,5,pdfTxt('Délivrée le '.qtDate($q['date_emission'] ?? date('Y-m-d H:i:s'), true)),0,1,'L');
$pdf->Ln(6);
$pdf->SetFont('Arial','B',9);
$pdf->Cell(95,5,pdfTxt(qtFit(qtName($q),45)),0,0,'L');
$pdf->Cell(95,5,pdfTxt($q['nom_comptable'] ?? 'COMPTABLE'),0,1,'L');
$pdf->SetFont('Arial','',9);
$pdf->Cell(95,5,'',0,0,'L');
$pdf->Cell(95,5,pdfTxt('COMPTABLE'),0,1,'L');

cpCleanOutputBeforePdf();
$pdf->Output('I','QUITTANCE_'.qtCleanFile($q['numero_quittance']).'.pdf');
exit;
?>
