<?php
require_once "../auth/check_auth.php";
require_once "../auth/permissions.php";

requirePermission('users', 'view');

$db = cpDb();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $db->prepare("
    SELECT u.*, r.nom_role
    FROM users u
    LEFT JOIN roles r ON r.id = u.role_id
    WHERE u.id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("Utilisateur introuvable.");
}

$perms = getRolePermissions((int)($user['role_id'] ?? 0));
$matrix = collectPayPermissionMatrix();

$stmtLogs = $db->prepare("
    SELECT *
    FROM audit_logs
    WHERE user_id = ?
    ORDER BY id DESC
    LIMIT 20
");
$stmtLogs->execute([$id]);
$logs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Fiche utilisateur</title>
<style>
body{margin:0;background:#f8fafc;font-family:Arial,sans-serif}
.content{padding:25px}
.card{background:white;border-radius:18px;padding:22px;margin-bottom:15px;box-shadow:0 8px 25px rgba(0,0,0,.08)}
.grid{display:grid;grid-template-columns:1fr 2fr;gap:16px}
.avatar{width:130px;height:130px;border-radius:50%;object-fit:cover;background:#e5e7eb}
.perm{display:inline-block;background:#dcfce7;color:#166534;padding:6px 10px;border-radius:999px;margin:3px;font-weight:800;font-size:12px}
table{width:100%;border-collapse:collapse}
td,th{padding:9px;border-bottom:1px solid #e5e7eb;text-align:left}
.btn{display:inline-block;padding:10px 14px;border-radius:10px;background:#0f3460;color:white;text-decoration:none;font-weight:800}
</style>
</head>
<body>
<?php require_once "../includes/sidebar.php"; ?>

<main class="cp-main">
<div class="content">

<div class="card">
    <a class="btn" href="index.php">Retour</a>
    <h2>Fiche utilisateur</h2>

    <div class="grid">
        <div>
            <?php if(!empty($user['photo'])): ?>
                <img class="avatar" src="../uploads/users/<?= htmlspecialchars($user['photo']) ?>">
            <?php else: ?>
                <div class="avatar"></div>
            <?php endif; ?>
        </div>

        <div>
            <h3><?= htmlspecialchars($user['nom'] ?? '-') ?></h3>
            <p>Email : <?= htmlspecialchars($user['email'] ?? '-') ?></p>
            <p>Téléphone : <?= htmlspecialchars($user['telephone'] ?? '-') ?></p>
            <p>Rôle : <strong><?= htmlspecialchars($user['nom_role'] ?? '-') ?></strong></p>
            <p>Statut : <?= htmlspecialchars($user['statut'] ?? '-') ?></p>
            <p>Dernière connexion : <?= htmlspecialchars($user['derniere_connexion'] ?? '-') ?></p>
        </div>
    </div>
</div>

<div class="card">
<h3>Permissions héritées du rôle</h3>

<?php foreach($matrix as $module => $info): ?>
    <h4><?= htmlspecialchars($info['label']) ?></h4>

    <?php foreach($info['actions'] as $action => $label): ?>
        <?php if(isset($perms[$module][$action]) && (int)$perms[$module][$action] === 1): ?>
            <span class="perm"><?= htmlspecialchars($label) ?></span>
        <?php endif; ?>
    <?php endforeach; ?>
<?php endforeach; ?>
</div>

<div class="card">
<h3>Dernières activités</h3>

<table>
<tr>
    <th>Date</th>
    <th>Module</th>
    <th>Action</th>
    <th>Description</th>
</tr>

<?php foreach($logs as $l): ?>
<tr>
    <td><?= htmlspecialchars($l['created_at']) ?></td>
    <td><?= htmlspecialchars($l['module']) ?></td>
    <td><?= htmlspecialchars($l['action']) ?></td>
    <td><?= htmlspecialchars($l['description']) ?></td>
</tr>
<?php endforeach; ?>

<?php if(empty($logs)): ?>
<tr>
    <td colspan="4">Aucune activité enregistrée.</td>
</tr>
<?php endif; ?>
</table>
</div>

</div>
</main>
</body>
</html>
