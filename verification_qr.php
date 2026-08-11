<?php
ini_set('display_errors','0');
error_reporting(E_ALL);

$databaseFile=__DIR__.'/config/database.php';
if(is_file($databaseFile)) require_once $databaseFile;
$engineFile=__DIR__.'/core/secure_qr_engine.php';
if(is_file($engineFile)) require_once $engineFile;

function qh($v): string { return htmlspecialchars((string)($v??''),ENT_QUOTES,'UTF-8'); }
function qm($v): string { return number_format((float)$v,2,',',' ').' CDF'; }

$db=isset($pdo)&&$pdo instanceof PDO?$pdo:null;
$result=null;
$qrContent='';

if($_SERVER['REQUEST_METHOD']==='POST'){
    $qrContent=trim((string)($_POST['qr_content']??''));

    if($qrContent===''){
        $result=['valid'=>false,'message'=>'Aucun contenu QR n’a été fourni.'];
    } elseif(!$db || !function_exists('verifyEncryptedQrPayload')){
        $result=['valid'=>false,'message'=>'Le service de vérification est momentanément indisponible.'];
    } else {
        try{
            $result=verifyEncryptedQrPayload($db,$qrContent);
        }catch(Throwable $e){
            error_log('QR public : '.$e->getMessage());
            $result=['valid'=>false,'message'=>'La vérification n’a pas pu être effectuée.'];
        }
    }
}

$doc=($result && !empty($result['valid']))?($result['document_token']??[]):[];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Scanner QR | cOllect_Pay</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="assets/css/public.css" rel="stylesheet">
<link href="assets/css/public_interactive.css" rel="stylesheet">
</head>
<body class="public-tool-page qr-page">
<nav class="navbar navbar-dark premium-nav public-tool-nav">
<div class="container">
<a class="navbar-brand" href="index.php">
<span class="brand-mark"><i class="bi bi-shield-check"></i></span>
<span class="brand-copy"><strong>cOllect_Pay</strong><small>LECTEUR QR PUBLIC SÉCURISÉ</small></span>
</a>
<div class="public-tool-actions">
<a href="consultation_np.php" class="btn btn-nav-outline"><i class="bi bi-receipt"></i> Consulter NP</a>
<a href="index.php" class="btn btn-nav-gold"><i class="bi bi-house"></i> Vitrine</a>
</div>
</div>
</nav>

<main class="public-tool-main">
<section class="tool-hero qr-tool-hero">
<div class="tool-grid-overlay"></div>
<div class="qr-beam qr-beam-one"></div><div class="qr-beam qr-beam-two"></div>
<div class="container position-relative">
<div class="row align-items-center g-5">
<div class="col-lg-6">
<span class="tool-eyebrow"><i class="bi bi-fingerprint"></i> Moteur cryptographique cOllect_Pay</span>
<h1>Vérifiez le <span>QR sécurisé</span> d’un document</h1>
<p>Le QR ne contient pas les informations fiscales lisibles directement. Il contient un jeton chiffré contrôlé par le serveur officiel.</p>
<div class="tool-trust">
<span><i class="bi bi-lock-fill"></i> QR chiffré</span>
<span><i class="bi bi-shield-check"></i> Signature serveur</span>
<span><i class="bi bi-database-check"></i> Registre officiel</span>
</div>
</div>
<div class="col-lg-6">
<div class="qr-scanner-shell">
<div class="scanner-status"><span></span><b id="scannerStatus">Caméra prête</b></div>
<div class="camera-stage">
<video id="qrVideo" playsinline muted></video>
<div class="scanner-frame"><span></span><span></span><span></span><span></span><div class="scanner-line"></div></div>
<div class="camera-placeholder" id="cameraPlaceholder"><i class="bi bi-camera"></i><strong>Lancer le scanner</strong><span>Autorisez la caméra pour lire le QR.</span></div>
</div>
<div class="scanner-actions">
<button type="button" id="startQrCamera" class="scanner-main-btn"><i class="bi bi-camera-video"></i> Scanner avec la caméra</button>
<button type="button" id="stopQrCamera" class="scanner-stop-btn" disabled><i class="bi bi-stop-circle"></i> Arrêter</button>
</div>
<small class="scanner-compatibility" id="scannerCompatibility">Le scanner utilise la détection QR disponible dans le navigateur. Un mode manuel reste disponible.</small>
</div>
</div>
</div>
</section>

<section class="tool-result-section qr-result-section">
<div class="container">
<div class="row g-4">
<div class="col-lg-5">
<article class="manual-qr-card">
<span class="section-kicker">MODE MANUEL</span>
<h2>Coller le contenu QR</h2>
<p>Si la caméra n’est pas disponible, utilisez le contenu commençant par <strong>CP:</strong> lu par votre scanner.</p>
<form method="post" id="qrVerifyForm">
<textarea id="qrContent" name="qr_content" placeholder="CP:..." required><?= qh($qrContent) ?></textarea>
<button type="submit"><i class="bi bi-shield-check"></i> Vérifier maintenant</button>
</form>
</article>
</div>
<div class="col-lg-7">
<?php if($result): ?>
<article class="qr-verification-result <?= !empty($result['valid'])?'valid':'invalid' ?>">
<div class="result-visual"><div class="result-ring"><i class="bi <?= !empty($result['valid'])?'bi-check2':'bi-x-lg' ?>"></i></div></div>
<div class="result-copy">
<small>RÉSULTAT DU CONTRÔLE</small>
<h2><?= !empty($result['valid'])?'Document authentique':'Document non validé' ?></h2>
<p><?= qh($result['message']??'') ?></p>
<?php if(!empty($result['valid'])): ?>
<div class="qr-doc-grid">
<div><span>Type</span><strong><?= qh($doc['type_document']??'-') ?></strong></div>
<div><span>Numéro</span><strong><?= qh($doc['numero_document']??'-') ?></strong></div>
<div><span>Montant de référence</span><strong><?= qm($doc['montant']??0) ?></strong></div>
<div><span>Statut du jeton</span><strong><?= qh(strtoupper($doc['statut']??'ACTIF')) ?></strong></div>
</div>
<?php endif; ?>
</div>
</article>
<?php else: ?>
<article class="qr-waiting-card">
<div class="qr-waiting-animation"><i class="bi bi-qr-code"></i><span></span></div>
<h2>En attente d’un QR</h2>
<p>Scannez un document ou collez son contenu sécurisé pour lancer la vérification cryptographique.</p>
</article>
<?php endif; ?>
</div>
</div>
</div>
</section>
</main>

<script>
window.CP_QR_AUTO_SUBMIT = true;
</script>
<script src="assets/js/public_interactive.js"></script>
</body>
</html>
