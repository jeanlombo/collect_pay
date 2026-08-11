<?php
require_once "../../config/database.php";
require_once "../../config/security.php";
require_once "../../core/functions.php";

checkAuth();
requireRole([
    'SUPER_ADMIN'
]);

$page_title = "Journaux système";

$search = $_GET['search'] ?? '';

$sql = "
    SELECT l.*, u.nom AS utilisateur
    FROM audit_logs l
    LEFT JOIN users u ON l.user_id = u.id
    WHERE 1=1
";

$params = [];

if (!empty($search)) {
    $sql .= "
        AND (
            l.action LIKE ?
            OR l.table_modifiee LIKE ?
            OR u.nom LIKE ?
            OR l.ip_address LIKE ?
        )
    ";
    $params = ["%$search%", "%$search%", "%$search%", "%$search%"];
}

$sql .= " ORDER BY l.date_action DESC LIMIT 200";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($page_title) ?> | cOllect_Pay</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../../assets/css/admin.css">
<link rel="stylesheet" href="../../assets/css/administration.css">
</head>
<body class="cp-administration-page">
<div class="admin-layout">
    <?php require_once "../../includes/sidebar.php"; ?>

    <main class="main-content">
        <?php require_once "../../includes/topbar.php"; ?>

        <div class="panel cp-administration-panel">
            <h3>Journaux système</h3>

            <form method="GET" style="display:grid;grid-template-columns:1fr auto;gap:12px;">
                <input type="text" name="search" placeholder="Recherche action, table, utilisateur, IP"
                       value="<?= htmlspecialchars($search) ?>">
                <button type="submit">Filtrer</button>
            </form>
        </div>

        <div class="panel cp-administration-panel">
            <table class="table-premium cp-administration-table">
                <tr>
                    <th>Date</th>
                    <th>Utilisateur</th>
                    <th>Action</th>
                    <th>Table</th>
                    <th>Référence</th>
                    <th>IP</th>
                </tr>

                <?php foreach($logs as $l): ?>
                    <tr>
                        <td><?= htmlspecialchars($l['date_action']) ?></td>
                        <td><?= htmlspecialchars($l['utilisateur'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($l['action']) ?></td>
                        <td><?= htmlspecialchars($l['table_modifiee'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($l['reference_id'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($l['ip_address'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php if(empty($logs)): ?>
                    <tr><td colspan="6">Aucun journal trouvé.</td></tr>
                <?php endif; ?>
            </table>
        </div>
    </main>
</div>
</body>
</html>