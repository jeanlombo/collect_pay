<?php
require_once "../auth/check_auth.php";
require_once "../auth/permissions.php";

requirePermission('roles', 'view');

$db = cpDb();

if (!$db) {
    die("Connexion PDO introuvable.");
}

$roles = $db->query("
    SELECT r.*,
           COUNT(p.id) AS total_permissions
    FROM roles r
    LEFT JOIN permissions p
        ON p.role_id = r.id
       AND p.autorise = 1
    GROUP BY r.id
    ORDER BY r.id ASC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Rôles | cOllect_Pay</title>
<style>
body{margin:0;background:#f8fafc;font-family:Arial,sans-serif;color:#0f172a}
.content{padding:25px}
.card{background:#fff;border-radius:18px;padding:20px;box-shadow:0 8px 25px rgba(0,0,0,.08)}
.top{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px}
.btn{display:inline-block;padding:10px 14px;border-radius:10px;text-decoration:none;font-weight:800}
.btn-primary{background:#0f3460;color:white}
.btn-gold{background:#fbbf24;color:#111827}
table{width:100%;border-collapse:collapse}
th,td{padding:10px;border-bottom:1px solid #e5e7eb;text-align:left}
th{background:#f8fafc;color:#0f3460}
.badge{padding:5px 10px;border-radius:999px;font-weight:800;font-size:12px}
.actif{background:#dcfce7;color:#166534}
.inactif{background:#fee2e2;color:#991b1b}
</style>
</head>
<body>
<?php require_once "../includes/sidebar.php"; ?>

<main class="cp-main">
<div class="content">
    <div class="card">
        <div class="top">
            <div>
                <h2>Gestion des rôles</h2>
                <p>Administration professionnelle des profils utilisateurs.</p>
            </div>

            <?php if(hasPermission('roles','add')): ?>
                <a class="btn btn-primary" href="add.php">+ Nouveau rôle</a>
            <?php endif; ?>
        </div>

        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Rôle</th>
                    <th>Description</th>
                    <th>Permissions</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($roles as $r): ?>
                <tr>
                    <td><?= (int)$r['id'] ?></td>
                    <td><strong><?= htmlspecialchars($r['nom_role']) ?></strong></td>
                    <td><?= htmlspecialchars($r['description'] ?? '-') ?></td>
                    <td><?= (int)$r['total_permissions'] ?></td>
                    <td>
                        <span class="badge <?= htmlspecialchars($r['statut'] ?? 'actif') ?>">
                            <?= htmlspecialchars($r['statut'] ?? 'actif') ?>
                        </span>
                    </td>
                    <td>
                        <?php if(hasPermission('roles','edit')): ?>
                            <a class="btn btn-gold" href="edit.php?id=<?= (int)$r['id'] ?>">Modifier</a>
                        <?php endif; ?>

                        <?php if(hasPermission('roles','permissions')): ?>
                            <a class="btn btn-primary" href="permissions.php?id=<?= (int)$r['id'] ?>">Permissions</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</main>
</body>
</html>
