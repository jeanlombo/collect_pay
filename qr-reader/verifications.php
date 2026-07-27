<?php
require_once "../../config/database.php";
require_once "../../config/security.php";

checkAuth();

$page_title = "Journal des Vérifications QR";

$filtre = $_GET['resultat'] ?? '';

$where = '';
$params = [];

if ($filtre !== '') {
    $where = "WHERE resultat = ?";
    $params[] = $filtre;
}

$stmt = $pdo->prepare("
    SELECT *
    FROM qr_verifications
    $where
    ORDER BY date_verification DESC
");
$stmt->execute($params);
$logs = $stmt->fetchAll();

function badgeResultat($r)
{
    if ($r === 'authentique') {
        return "<span class='badge green'>AUTHENTIQUE</span>";
    }

    return "<span class='badge red'>CONTREFAIT</span>";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($page_title) ?> | cOllect_Pay</title>

<link rel="stylesheet" href="/collect_pay/assets/css/admin.css">

<style>

.filters{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    margin-bottom:20px;
}

.filters a{
    text-decoration:none;
    padding:10px 16px;
    border-radius:12px;
    background:#f1f5f9;
    color:#0f3460;
    font-weight:900;
}

.badge{
    padding:8px 12px;
    border-radius:999px;
    font-weight:900;
}

.green{
    background:#dcfce7;
    color:#166534;
}

.red{
    background:#fee2e2;
    color:#991b1b;
}

.stat-card{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:15px;
    margin-bottom:20px;
}

.stat{
    background:white;
    border-radius:20px;
    padding:20px;
    text-align:center;
    box-shadow:0 10px 25px rgba(0,0,0,.08);
}

.stat h3{
    margin:0;
    color:#0f3460;
    font-size:26px;
}

.stat span{
    color:#64748b;
    font-weight:800;
}

</style>
</head>

<body>

<div class="admin-layout">

<?php require_once "../../includes/sidebar.php"; ?>

<main class="main-content">

<?php require_once "../../includes/topbar.php"; ?>

<?php

$total = $pdo->query("
    SELECT COUNT(*) FROM qr_verifications
")->fetchColumn();

$auth = $pdo->query("
    SELECT COUNT(*)
    FROM qr_verifications
    WHERE resultat='authentique'
")->fetchColumn();

$faux = $pdo->query("
    SELECT COUNT(*)
    FROM qr_verifications
    WHERE resultat='contrefait'
")->fetchColumn();

$today = $pdo->query("
    SELECT COUNT(*)
    FROM qr_verifications
    WHERE DATE(date_verification)=CURDATE()
")->fetchColumn();

?>

<div class="stat-card">

    <div class="stat">
        <h3><?= $total ?></h3>
        <span>Contrôles</span>
    </div>

    <div class="stat">
        <h3><?= $auth ?></h3>
        <span>Authentiques</span>
    </div>

    <div class="stat">
        <h3><?= $faux ?></h3>
        <span>Contrefaits</span>
    </div>

    <div class="stat">
        <h3><?= $today ?></h3>
        <span>Aujourd'hui</span>
    </div>

</div>

<div class="panel">

    <h2>Historique des vérifications</h2>

    <div class="filters">
        <a href="verifications.php">Toutes</a>
        <a href="verifications.php?resultat=authentique">Authentiques</a>
        <a href="verifications.php?resultat=contrefait">Contrefaits</a>
    </div>

    <table class="table-premium">

        <tr>
            <th>Date</th>
            <th>Type</th>
            <th>Numéro</th>
            <th>Résultat</th>
            <th>IP</th>
            <th>Message</th>
        </tr>

        <?php foreach ($logs as $row): ?>

        <tr>

            <td>
                <?= date(
                    'd/m/Y H:i:s',
                    strtotime($row['date_verification'])
                ) ?>
            </td>

            <td>
                <?= htmlspecialchars(
                    $row['type_document'] ?? '-'
                ) ?>
            </td>

            <td>
                <?= htmlspecialchars(
                    $row['numero_document'] ?? '-'
                ) ?>
            </td>

            <td>
                <?= badgeResultat($row['resultat']) ?>
            </td>

            <td>
                <?= htmlspecialchars(
                    $row['adresse_ip'] ?? '-'
                ) ?>
            </td>

            <td>
                <?= htmlspecialchars(
                    $row['message'] ?? '-'
                ) ?>
            </td>

        </tr>

        <?php endforeach; ?>

        <?php if (empty($logs)): ?>

        <tr>
            <td colspan="6">
                Aucun contrôle enregistré.
            </td>
        </tr>

        <?php endif; ?>

    </table>

</div>

</main>
</div>
<a href="/collect_pay/modules/inspection/scan_qr.php">
    🔍 Lecteur QR
</a>

<a href="/collect_pay/modules/inspection/verifications.php">
    📋 Journal Vérifications
</a>
</body>
</html>