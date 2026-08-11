<?php
require_once "../../config/database.php";
require_once "../../config/security.php";

checkAuth();
requirePermission('inspection', 'alerts');

$page_title = "Alertes Inspection";

$fraudesToday = $pdo->query("
    SELECT COUNT(*)
    FROM qr_verifications
    WHERE resultat IN ('invalide','suspect')
    AND DATE(date_verification) = CURDATE()
")->fetchColumn();

$ipsSuspectes = $pdo->query("
    SELECT adresse_ip, COUNT(*) AS total
    FROM qr_verifications
    WHERE resultat IN ('invalide','suspect')
    GROUP BY adresse_ip
    HAVING COUNT(*) >= 3
    ORDER BY total DESC
")->fetchAll();

$docsRevoques = $pdo->query("
    SELECT *
    FROM document_tokens
    WHERE statut = 'revoque'
    ORDER BY date_revocation DESC
    LIMIT 20
")->fetchAll();

$dernieresFraudes = $pdo->query("
    SELECT *
    FROM qr_verifications
    WHERE resultat IN ('invalide','suspect')
    ORDER BY date_verification DESC
    LIMIT 20
")->fetchAll();

function fmtDateAlerte($date) {
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
.alert-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(230px,1fr));
    gap:15px;
    margin-bottom:20px;
}
.alert-card{
    background:white;
    border-radius:20px;
    padding:20px;
    box-shadow:0 10px 25px rgba(0,0,0,.08);
    border-left:6px solid #dc2626;
}
.alert-card h3{
    margin:0;
    font-size:30px;
    color:#991b1b;
}
.alert-card span{
    color:#64748b;
    font-weight:900;
}
.badge-red{
    background:#fee2e2;
    color:#991b1b;
    padding:7px 12px;
    border-radius:999px;
    font-weight:900;
}
.badge-orange{
    background:#ffedd5;
    color:#9a3412;
    padding:7px 12px;
    border-radius:999px;
    font-weight:900;
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
    <h2>Alertes Inspection</h2>
    <p>Surveillance rapide des fraudes, QR suspects et documents révoqués.</p>
</div>

<div class="actions">
    <a class="btn-premium" href="dashboard.php">🛡️ Tableau Inspection</a>
    <a class="btn-premium" href="scan_qr.php">🔍 Scanner QR</a>
    <a class="btn-premium" href="verifications.php">📋 Vérifications</a>
    <a class="btn-premium" href="fraude_suspecte.php">🚨 Fraudes suspectes</a>
</div>

<div class="alert-grid">
    <div class="alert-card">
        <h3><?= (int)$fraudesToday ?></h3>
        <span>QR contrefaits aujourd'hui</span>
    </div>

    <div class="alert-card">
        <h3><?= count($ipsSuspectes) ?></h3>
        <span>Adresses IP suspectes</span>
    </div>

    <div class="alert-card">
        <h3><?= count($docsRevoques) ?></h3>
        <span>Documents révoqués récents</span>
    </div>
</div>

<div class="panel cp-inspection-panel">
    <h3>IP suspectes</h3>

    <table class="table-premium cp-inspection-table">
        <tr>
            <th>Adresse IP</th>
            <th>Nombre de tentatives</th>
            <th>Niveau</th>
        </tr>

        <?php foreach ($ipsSuspectes as $ip): ?>
            <tr>
                <td><strong><?= htmlspecialchars($ip['adresse_ip'] ?? 'Inconnue') ?></strong></td>
                <td><?= (int)$ip['total'] ?></td>
                <td>
                    <?php if ((int)$ip['total'] >= 10): ?>
                        <span class="badge-red">CRITIQUE</span>
                    <?php else: ?>
                        <span class="badge-orange">SURVEILLANCE</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>

        <?php if (empty($ipsSuspectes)): ?>
            <tr><td colspan="3">Aucune IP suspecte détectée.</td></tr>
        <?php endif; ?>
    </table>
</div>

<div class="panel cp-inspection-panel">
    <h3>Dernières tentatives contrefaites</h3>

    <table class="table-premium cp-inspection-table">
        <tr>
            <th>Date</th>
            <th>Type</th>
            <th>Numéro</th>
            <th>IP</th>
            <th>Message</th>
        </tr>

        <?php foreach ($dernieresFraudes as $f): ?>
            <tr>
                <td><?= htmlspecialchars(fmtDateAlerte($f['date_verification'])) ?></td>
                <td><?= htmlspecialchars($f['type_document'] ?? '-') ?></td>
                <td><?= htmlspecialchars($f['numero_document'] ?? '-') ?></td>
                <td><?= htmlspecialchars($f['adresse_ip'] ?? '-') ?></td>
                <td><span class="badge-red"><?= htmlspecialchars(($f['resultat'] ?? '') === 'suspect' ? 'QR suspect' : 'QR invalide') ?></span></td>
            </tr>
        <?php endforeach; ?>

        <?php if (empty($dernieresFraudes)): ?>
            <tr><td colspan="5">Aucune fraude enregistrée.</td></tr>
        <?php endif; ?>
    </table>
</div>

<div class="panel cp-inspection-panel">
    <h3>Documents révoqués récents</h3>

    <table class="table-premium cp-inspection-table">
        <tr>
            <th>Date révocation</th>
            <th>Type</th>
            <th>Numéro</th>
            <th>Montant</th>
            <th>Statut</th>
        </tr>

        <?php foreach ($docsRevoques as $d): ?>
            <tr>
                <td><?= htmlspecialchars(fmtDateAlerte($d['date_revocation'])) ?></td>
                <td><?= htmlspecialchars($d['type_document']) ?></td>
                <td><strong><?= htmlspecialchars($d['numero_document']) ?></strong></td>
                <td><?= number_format($d['montant'] ?? 0, 2, ',', ' ') ?> CDF</td>
                <td><span class="badge-red">RÉVOQUÉ</span></td>
            </tr>
        <?php endforeach; ?>

        <?php if (empty($docsRevoques)): ?>
            <tr><td colspan="5">Aucun document révoqué récent.</td></tr>
        <?php endif; ?>
    </table>
</div>

</main>
</div>
</body>
</html>