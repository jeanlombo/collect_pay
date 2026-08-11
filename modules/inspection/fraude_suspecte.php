<?php
require_once "../../config/database.php";
require_once "../../config/security.php";

checkAuth();
$page_title = "Fraudes suspectes";

$stmt = $pdo->prepare("
    SELECT 
        adresse_ip,
        COUNT(*) AS total_tentatives,
        MAX(date_verification) AS derniere_tentative
    FROM qr_verifications
    WHERE resultat IN ('invalide','suspect')
    GROUP BY adresse_ip
    ORDER BY total_tentatives DESC, derniere_tentative DESC
");
$stmt->execute();
$alertes = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT *
    FROM qr_verifications
    WHERE resultat IN ('invalide','suspect')
    ORDER BY date_verification DESC
    LIMIT 100
");
$stmt->execute();
$details = $stmt->fetchAll();

function formatDateFraude($date)
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
    background:linear-gradient(135deg,#7f1d1d,#991b1b);
    color:white;
    padding:24px;
    border-radius:24px;
    margin-bottom:20px;
}
.hero h2{margin:0;font-weight:900}
.hero p{color:#fee2e2;margin:8px 0 0}
.badge-red{
    background:#fee2e2;
    color:#991b1b;
    padding:7px 12px;
    border-radius:999px;
    font-weight:900;
}
.warning-card{
    background:white;
    border-left:6px solid #dc2626;
    padding:18px;
    border-radius:18px;
    box-shadow:0 10px 25px rgba(0,0,0,.08);
    margin-bottom:14px;
}
.warning-card h3{
    margin:0;
    color:#991b1b;
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
    <h2>Fraudes suspectes</h2>
    <p>Analyse des QR Codes contrefaits, inconnus ou non reconnus par Collect_Pay.</p>
</div>

<div class="actions">
    <a class="btn-premium" href="dashboard.php">🛡️ Tableau Inspection</a>
    <a class="btn-premium" href="verifications.php">📋 Journal vérifications</a>
    <a class="btn-premium" href="documents_revoques.php">🚫 Documents révoqués</a>
</div>

<div class="panel cp-inspection-panel">
    <h3>Adresses IP suspectes</h3>

    <?php foreach ($alertes as $a): ?>
        <div class="warning-card">
            <h3>IP : <?= htmlspecialchars($a['adresse_ip'] ?? 'Inconnue') ?></h3>
            <p>
                Tentatives contrefaites :
                <strong><?= (int)$a['total_tentatives'] ?></strong>
            </p>
            <p>
                Dernière tentative :
                <strong><?= htmlspecialchars(formatDateFraude($a['derniere_tentative'])) ?></strong>
            </p>
        </div>
    <?php endforeach; ?>

    <?php if (empty($alertes)): ?>
        <p>Aucune fraude suspecte détectée pour le moment.</p>
    <?php endif; ?>
</div>

<div class="panel cp-inspection-panel">
    <h3>Détails des dernières tentatives frauduleuses</h3>

    <table class="table-premium cp-inspection-table">
        <tr>
            <th>Date</th>
            <th>Type</th>
            <th>Numéro</th>
            <th>IP</th>
            <th>Message</th>
        </tr>

        <?php foreach ($details as $d): ?>
            <tr>
                <td><?= htmlspecialchars(formatDateFraude($d['date_verification'])) ?></td>
                <td><?= htmlspecialchars($d['type_document'] ?? '-') ?></td>
                <td><?= htmlspecialchars($d['numero_document'] ?? '-') ?></td>
                <td><?= htmlspecialchars($d['adresse_ip'] ?? '-') ?></td>
                <td><span class="badge-red"><?= htmlspecialchars(($d['resultat'] ?? '') === 'suspect' ? 'QR suspect' : 'QR invalide') ?></span></td>
            </tr>
        <?php endforeach; ?>

        <?php if (empty($details)): ?>
            <tr>
                <td colspan="5">Aucune tentative frauduleuse enregistrée.</td>
            </tr>
        <?php endif; ?>
    </table>
</div>

</main>
</div>
</body>
</html>