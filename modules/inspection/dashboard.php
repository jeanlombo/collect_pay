<?php
require_once "../../config/database.php";
require_once "../../config/security.php";

checkAuth();
requireRole([
    'SUPER_ADMIN',
    'INSPECTEUR',
    'AUDITEUR'
]);

$page_title = "Dashboard Inspection";

$total = $pdo->query("
    SELECT COUNT(*)
    FROM qr_verifications
")->fetchColumn();

$today = $pdo->query("
    SELECT COUNT(*)
    FROM qr_verifications
    WHERE DATE(date_verification) = CURDATE()
")->fetchColumn();

$auth = $pdo->query("
    SELECT COUNT(*)
    FROM qr_verifications
    WHERE resultat = 'valide'
")->fetchColumn();

$faux = $pdo->query("
    SELECT COUNT(*)
    FROM qr_verifications
    WHERE resultat IN ('invalide','suspect')
")->fetchColumn();

$fauxToday = $pdo->query("
    SELECT COUNT(*)
    FROM qr_verifications
    WHERE resultat IN ('invalide','suspect')
    AND DATE(date_verification) = CURDATE()
")->fetchColumn();

$stmt = $pdo->query("
    SELECT *
    FROM qr_verifications
    ORDER BY date_verification DESC
    LIMIT 10
");
$derniers = $stmt->fetchAll();

$stmt = $pdo->query("
    SELECT 
        adresse_ip,
        COUNT(*) AS total
    FROM qr_verifications
    WHERE resultat IN ('invalide','suspect')
    GROUP BY adresse_ip
    HAVING COUNT(*) >= 3
    ORDER BY total DESC
    LIMIT 5
");
$alertes = $stmt->fetchAll();

function badgeInspection($resultat)
{
    if ($resultat === 'valide') {
        return "<span class='badge green'>VALIDE</span>";
    }

    return "<span class='badge red'>INVALIDE / SUSPECT</span>";
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
    padding:25px;
    border-radius:24px;
    margin-bottom:20px;
}

.hero h2{
    margin:0;
    font-size:28px;
    font-weight:900;
}

.hero p{
    margin-top:8px;
    color:#cbd5e1;
}

.stats{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:15px;
    margin-bottom:20px;
}

.card-stat{
    background:white;
    border-radius:20px;
    padding:20px;
    box-shadow:0 10px 25px rgba(0,0,0,.08);
}

.card-stat h3{
    margin:0;
    font-size:30px;
    color:#0f3460;
}

.card-stat span{
    color:#64748b;
    font-weight:800;
}

.badge{
    padding:7px 12px;
    border-radius:999px;
    font-weight:900;
}

.badge.green{
    background:#dcfce7;
    color:#166534;
}

.badge.red{
    background:#fee2e2;
    color:#991b1b;
}

.alert-box{
    background:#fff7ed;
    border:1px solid #fdba74;
    border-radius:18px;
    padding:15px;
    margin-bottom:20px;
}

.alert-box h3{
    margin-top:0;
    color:#9a3412;
}

.alert-item{
    padding:8px 0;
    border-bottom:1px dashed #fdba74;
}

.alert-item:last-child{
    border-bottom:none;
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
    <h2>Centre de Contrôle & Inspection</h2>
    <p>Surveillance des documents fiscaux, QR Codes et tentatives de fraude.</p>
</div>

<div class="stats">

    <div class="card-stat">
        <h3><?= $total ?></h3>
        <span>Contrôles Totaux</span>
    </div>

    <div class="card-stat">
        <h3><?= $today ?></h3>
        <span>Contrôles Aujourd'hui</span>
    </div>

    <div class="card-stat">
        <h3><?= $auth ?></h3>
        <span>Documents Authentiques</span>
    </div>

    <div class="card-stat">
        <h3><?= $faux ?></h3>
        <span>Documents Contrefaits</span>
    </div>

    <div class="card-stat">
        <h3><?= $fauxToday ?></h3>
        <span>Fraudes Aujourd'hui</span>
    </div>

</div>

<?php if (!empty($alertes)): ?>

<div class="alert-box">

    <h3>🚨 Alertes Fraude Suspecte</h3>

    <?php foreach ($alertes as $a): ?>

        <div class="alert-item">
            IP :
            <strong><?= htmlspecialchars($a['adresse_ip']) ?></strong>
            —
            <?= (int)$a['total'] ?> tentatives de documents contrefaits
        </div>

    <?php endforeach; ?>

</div>

<?php endif; ?>

<div class="panel cp-inspection-panel">

    <h3>Derniers Contrôles QR</h3>

    <table class="table-premium cp-inspection-table">

        <tr>
            <th>Date</th>
            <th>Type</th>
            <th>Numéro</th>
            <th>Résultat</th>
            <th>Adresse IP</th>
        </tr>

        <?php foreach ($derniers as $row): ?>

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
                <?= badgeInspection(
                    $row['resultat']
                ) ?>
            </td>

            <td>
                <?= htmlspecialchars(
                    $row['adresse_ip'] ?? '-'
                ) ?>
            </td>

        </tr>

        <?php endforeach; ?>

        <?php if (empty($derniers)): ?>

        <tr>
            <td colspan="5">
                Aucun contrôle enregistré.
            </td>
        </tr>

        <?php endif; ?>

    </table>

</div>

<div class="panel cp-inspection-panel">

    <h3>Accès Rapides</h3>

    <div style="display:flex;gap:12px;flex-wrap:wrap;">

        <a class="btn-premium"
           href="scan_qr.php">
            🔍 Lecteur QR
        </a>

        <a class="btn-premium"
           href="verifications.php">
            📋 Journal Vérifications
        </a>

    </div>

</div>

</main>
</div>

</body>
</html>