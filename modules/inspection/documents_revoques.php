<?php
require_once "../../config/database.php";
require_once "../../config/security.php";

checkAuth();
$page_title = "Documents révoqués";

$stmt = $pdo->prepare("
    SELECT *
    FROM document_tokens
    WHERE statut = 'revoque'
    ORDER BY date_revocation DESC, created_at DESC
");
$stmt->execute();
$docs = $stmt->fetchAll();

function formatDateRevocation($date)
{
    if (!$date) return '-';
    return date('d/m/Y H:i:s', strtotime($date));
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($page_title) ?> | cOllect_Pay</title>
<link rel="stylesheet" href="../../assets/css/admin.css">

<style>
.hero{
    background:linear-gradient(135deg,#06152b,#0f3460);
    color:white;
    padding:24px;
    border-radius:24px;
    margin-bottom:20px;
}
.hero h2{margin:0;font-weight:900}
.hero p{color:#cbd5e1;margin:8px 0 0}
.badge-red{
    background:#fee2e2;
    color:#991b1b;
    padding:7px 12px;
    border-radius:999px;
    font-weight:900;
}
.amount{
    font-weight:900;
    color:#0f3460;
}
.actions{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    margin-bottom:18px;
}
.btn-premium{
    display:inline-block;
    padding:11px 16px;
    border-radius:14px;
    text-decoration:none;
    font-weight:900;
    background:#0f3460;
    color:white;
}
</style>
<link rel="stylesheet" href="../../assets/css/inspection.css">
</head>

<body class="cp-inspection-page">
<div class="admin-layout">

<?php require_once "../../includes/sidebar.php"; ?>

<main class="main-content">
<?php require_once "../../includes/topbar.php"; ?>

<div class="hero">
    <h2>Documents révoqués</h2>
    <p>Liste des QR Codes et documents officiellement révoqués dans Collect_Pay.</p>
</div>

<div class="actions">
    <a class="btn-premium" href="dashboard.php">🛡️ Tableau Inspection</a>
    <a class="btn-premium" href="verifications.php">📋 Journal vérifications</a>
    <a class="btn-premium" href="scan_qr.php">🔍 Scanner QR</a>
</div>

<div class="panel cp-inspection-panel">
    <h3>Liste des documents révoqués</h3>

    <table class="table-premium cp-inspection-table">
        <tr>
            <th>Type</th>
            <th>Numéro document</th>
            <th>Montant</th>
            <th>Token</th>
            <th>Date création</th>
            <th>Date révocation</th>
            <th>Statut</th>
        </tr>

        <?php foreach ($docs as $d): ?>
            <tr>
                <td><?= htmlspecialchars($d['type_document']) ?></td>
                <td><strong><?= htmlspecialchars($d['numero_document']) ?></strong></td>
                <td><span class="amount"><?= number_format($d['montant'] ?? 0, 2, ',', ' ') ?> CDF</span></td>
                <td><?= htmlspecialchars(substr($d['token'], 0, 18)) ?>...</td>
                <td><?= htmlspecialchars(formatDateRevocation($d['created_at'])) ?></td>
                <td><?= htmlspecialchars(formatDateRevocation($d['date_revocation'])) ?></td>
                <td><span class="badge-red">RÉVOQUÉ</span></td>
            </tr>
        <?php endforeach; ?>

        <?php if (empty($docs)): ?>
            <tr>
                <td colspan="7">Aucun document révoqué pour le moment.</td>
            </tr>
        <?php endif; ?>
    </table>
</div>

</main>
</div>
</body>
</html>