<?php
require_once "../../config/database.php";
require_once "../../config/security.php";
require_once "../../core/functions.php";

checkAuth();    

$page_title = "Audit système";

$totalLogs = $pdo->query("SELECT COUNT(*) total FROM audit_logs")->fetch()['total'];
$totalAlertes = $pdo->query("SELECT COUNT(*) total FROM alertes_systeme")->fetch()['total'];
$totalAlertesOuvertes = $pdo->query("SELECT COUNT(*) total FROM alertes_systeme WHERE statut='ouverte'")->fetch()['total'];
$totalQR = $pdo->query("SELECT COUNT(*) total FROM qr_verifications")->fetch()['total'];

$alertes = $pdo->query("
    SELECT *
    FROM alertes_systeme
    ORDER BY date_detection DESC
    LIMIT 50
")->fetchAll();

$qrLogs = $pdo->query("
    SELECT q.*, u.nom AS inspecteur
    FROM qr_verifications q
    LEFT JOIN users u ON q.user_inspecteur_id = u.id
    ORDER BY q.date_verification DESC
    LIMIT 50
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($page_title) ?> | cOllect_Pay</title>
    <link rel="stylesheet" href="/collect_pay/assets/css/admin.css">
</head>
<body>
<div class="admin-layout">
    <?php require_once "../../includes/sidebar.php"; ?>

    <main class="main-content">
        <?php require_once "../../includes/topbar.php"; ?>

        <div class="kpi-grid">
            <div class="kpi-card">
                <span>Journaux</span>
                <h2><?= $totalLogs ?></h2>
            </div>

            <div class="kpi-card">
                <span>Alertes</span>
                <h2><?= $totalAlertes ?></h2>
            </div>

            <div class="kpi-card">
                <span>Alertes ouvertes</span>
                <h2><?= $totalAlertesOuvertes ?></h2>
            </div>

            <div class="kpi-card">
                <span>Vérifications QR</span>
                <h2><?= $totalQR ?></h2>
            </div>
        </div>

        <div class="panel">
            <h3>Alertes système</h3>

            <table class="table-premium">
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Description</th>
                    <th>Niveau</th>
                    <th>Statut</th>
                </tr>

                <?php foreach($alertes as $a): ?>
                    <tr>
                        <td><?= htmlspecialchars($a['date_detection']) ?></td>
                        <td><?= htmlspecialchars($a['type_alerte']) ?></td>
                        <td><?= htmlspecialchars($a['description'] ?? '-') ?></td>
                        <td><?= strtoupper(htmlspecialchars($a['niveau'])) ?></td>
                        <td><?= strtoupper(htmlspecialchars($a['statut'])) ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php if(empty($alertes)): ?>
                    <tr><td colspan="5">Aucune alerte trouvée.</td></tr>
                <?php endif; ?>
            </table>
        </div>

        <div class="panel">
            <h3>Historique vérifications QR</h3>

            <table class="table-premium">
                <tr>
                    <th>Date</th>
                    <th>Document</th>
                    <th>Numéro</th>
                    <th>Résultat</th>
                    <th>Inspecteur</th>
                    <th>IP</th>
                </tr>

                <?php foreach($qrLogs as $q): ?>
                    <tr>
                        <td><?= htmlspecialchars($q['date_verification']) ?></td>
                        <td><?= htmlspecialchars($q['type_document'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($q['numero_document'] ?? '-') ?></td>
                        <td><?= strtoupper(htmlspecialchars($q['resultat'])) ?></td>
                        <td><?= htmlspecialchars($q['inspecteur'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($q['ip_inspecteur'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php if(empty($qrLogs)): ?>
                    <tr><td colspan="6">Aucune vérification QR trouvée.</td></tr>
                <?php endif; ?>
            </table>
        </div>
    </main>
</div>
</body>
</html>