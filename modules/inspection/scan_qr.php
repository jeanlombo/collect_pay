<?php
require_once "../../config/database.php";
require_once "../../config/security.php";
require_once "../../core/secure_qr_engine.php";
checkAuth();
requirePermission('inspection', 'scan');
requireRole([
    'SUPER_ADMIN',
    'INSPECTEUR',
    'AUDITEUR'
]);

$page_title = "Lecteur QR Collect_Pay";

$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $qrContent = trim($_POST['qr_content'] ?? '');

    if ($qrContent === '') {
        $result = [
            'valid' => false,
            'message' => 'Aucun contenu QR reçu.'
        ];
    } else {
        $result = verifyEncryptedQrPayload($pdo, $qrContent);

        /*
        |--------------------------------------------------------------------------
        | Journal Inspection
        |--------------------------------------------------------------------------
        | qr_verifications accepte uniquement :
        | valide / invalide / annule / suspect
        */
        try {
            $docToken = $result['document_token'] ?? [];
            $statutLog = !empty($result['valid']) ? 'valide' : 'invalide';
            $numeroLog = $docToken['numero_document'] ?? null;
            $typeLog   = $docToken['type_document'] ?? null;

            $userInspecteur = (int)($_SESSION['user_id'] ?? 0);
            if ($userInspecteur <= 0) {
                $userInspecteur = null;
            }

            $adresseIp = $_SERVER['REMOTE_ADDR'] ?? null;
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

            $stmtLog = $pdo->prepare("
                INSERT INTO qr_verifications
                (
                    numero_document,
                    type_document,
                    resultat,
                    ip_inspecteur,
                    user_inspecteur_id,
                    adresse_ip,
                    appareil,
                    user_agent
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmtLog->execute([
                $numeroLog,
                $typeLog,
                $statutLog,
                $adresseIp,
                $userInspecteur,
                $adresseIp,
                $userAgent,
                $userAgent
            ]);
        } catch (Throwable $e) {
            // La vérification du document reste utilisable même si le journal échoue.
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($page_title) ?> | cOllect_Pay</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="../../assets/css/admin.css">

<script src="https://unpkg.com/html5-qrcode"></script>

<style>
.scan-hero{
    background:linear-gradient(135deg,#06152b,#0f3460);
    color:white;
    border-radius:24px;
    padding:24px;
    margin-bottom:22px;
}

.scan-hero h2{
    margin:0;
    font-weight:900;
}

.scan-hero p{
    margin:8px 0 0;
    color:#dbeafe;
}

.scan-layout{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:22px;
}

.scan-box{
    background:white;
    border-radius:24px;
    padding:22px;
    box-shadow:0 15px 35px rgba(0,0,0,.08);
}

#reader{
    width:100%;
    min-height:320px;
    border:2px dashed #0f3460;
    border-radius:20px;
    overflow:hidden;
    background:#f8fafc;
}

.result-auth{
    background:#dcfce7;
    border:1px solid #86efac;
    color:#166534;
    padding:18px;
    border-radius:18px;
    font-weight:900;
    margin-bottom:15px;
}

.result-fake{
    background:#fee2e2;
    border:1px solid #fca5a5;
    color:#991b1b;
    padding:18px;
    border-radius:18px;
    font-weight:900;
    margin-bottom:15px;
}

.manual-form textarea{
    width:100%;
    min-height:110px;
    border-radius:16px;
    padding:14px;
    border:1px solid #d1d5db;
}

.btn-scan{
    background:linear-gradient(135deg,#0f766e,#134e4a);
    color:white;
    border:none;
    padding:13px 20px;
    border-radius:15px;
    font-weight:900;
    cursor:pointer;
    margin-top:10px;
}

@media(max-width:900px){
    .scan-layout{
        grid-template-columns:1fr;
    }
}
</style>
<link rel="stylesheet" href="../../assets/css/inspection.css">
</head>

<body class="cp-inspection-page">
<div class="admin-layout">

<?php require_once "../../includes/sidebar.php"; ?>

<main class="main-content">
<?php require_once "../../includes/topbar.php"; ?>

<div class="scan-hero">
    <h2>Lecteur officiel QR Collect_Pay</h2>
    <p>Ce lecteur vérifie uniquement les QR Codes générés par le système Collect_Pay.</p>
</div>
<div class="scan-layout">

    <div class="scan-box">
        <h3>Scanner avec la caméra</h3>

        <div id="reader"></div>

        <form method="POST" id="scanForm">
            <input type="hidden" name="qr_content" id="qr_content">
        </form>
    </div>

    <div class="scan-box">
        <h3>Résultat de vérification</h3>

        <?php if ($result): ?>
            <?php if ($result['valid']): ?>
                <div class="result-auth">
                    ✅ DOCUMENT AUTHENTIQUE
                </div>

                <p>
                    <?= htmlspecialchars($result['message']) ?>
                </p>

                <table class="table-premium cp-inspection-table">
                    <tr>
                        <th>Type document</th>
                        <td><?= htmlspecialchars($result['document_token']['type_document']) ?></td>
                    </tr>
                    <tr>
                        <th>Numéro</th>
                        <td><?= htmlspecialchars($result['document_token']['numero_document']) ?></td>
                    </tr>
                    <tr>
                        <th>Montant</th>
                        <td><?= number_format($result['document_token']['montant'] ?? 0, 2, ',', ' ') ?> CDF</td>
                    </tr>
                    <tr>
                        <th>Statut QR</th>
                        <td><?= htmlspecialchars($result['document_token']['statut']) ?></td>
                    </tr>
                    <tr>
                        <th>Date vérification</th>
                        <td><?= date('d/m/Y H:i:s') ?></td>
                    </tr>
                </table>

            <?php else: ?>
                <div class="result-fake">
                    ❌ DOCUMENT CONTREFAIT OU QR NON COLLECT_PAY
                </div>

                <p>
                    <?= htmlspecialchars($result['message']) ?>
                </p>
            <?php endif; ?>
        <?php else: ?>
            <div class="result-fake">
                En attente de scan...
            </div>
        <?php endif; ?>

        <hr>

        <h4>Vérification manuelle</h4>

        <form method="POST" class="manual-form">
            <textarea name="qr_content" placeholder="Coller ici le contenu QR crypté CP:..."></textarea>
            <button type="submit" class="btn-scan">
                Vérifier manuellement
            </button>
        </form>
    </div>

</div>

<script>
function onScanSuccess(decodedText) {
    document.getElementById('qr_content').value = decodedText;
    document.getElementById('scanForm').submit();
}

function onScanFailure(error) {
    // Rien à afficher pendant la recherche caméra
}

const html5QrCode = new Html5QrcodeScanner(
    "reader",
    {
        fps: 10,
        qrbox: 250
    }
);

html5QrCode.render(onScanSuccess, onScanFailure);
</script>

</main>
</div>
</body>
</html>