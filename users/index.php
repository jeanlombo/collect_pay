<?php
require_once "../auth/check_auth.php";
require_once "../auth/permissions.php";

requirePermission('users', 'view');

$db = cpDb();

$q = trim($_GET['q'] ?? '');
$role_id = isset($_GET['role_id']) ? (int)$_GET['role_id'] : 0;
$statut = trim($_GET['statut'] ?? '');

$where = "WHERE 1=1";
$params = [];

if ($q !== '') {
    $where .= " AND (u.nom LIKE ? OR u.email LIKE ? OR u.telephone LIKE ?)";
    $like = "%".$q."%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($role_id > 0) {
    $where .= " AND u.role_id = ?";
    $params[] = $role_id;
}

if ($statut !== '') {
    $where .= " AND u.statut = ?";
    $params[] = $statut;
}

$sql = "
    SELECT u.*, r.nom_role
    FROM users u
    LEFT JOIN roles r ON r.id = u.role_id
    $where
    ORDER BY u.id DESC
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$roles = $db->query("
    SELECT id, nom_role
    FROM roles
    ORDER BY nom_role ASC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Utilisateurs | cOllect_Pay</title>
<style>
body{margin:0;background:#f8fafc;font-family:Arial,sans-serif;color:#0f172a}
.content{padding:25px}
.card{background:white;border-radius:18px;padding:20px;box-shadow:0 8px 25px rgba(0,0,0,.08)}
.top{display:flex;justify-content:space-between;gap:15px;align-items:center;margin-bottom:18px}
.btn{display:inline-block;padding:10px 14px;border-radius:10px;text-decoration:none;font-weight:800;border:none}
.btn-primary{background:#0f3460;color:white}
.btn-gold{background:#fbbf24;color:#111827}
.btn-gray{background:#e5e7eb;color:#111827}
.filters{display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:10px;margin-bottom:16px}
input,select{padding:11px;border:1px solid #d1d5db;border-radius:10px}
table{width:100%;border-collapse:collapse}
th,td{padding:10px;border-bottom:1px solid #e5e7eb;text-align:left;vertical-align:middle}
th{background:#f8fafc;color:#0f3460}
.avatar{width:42px;height:42px;border-radius:50%;object-fit:cover;background:#e5e7eb}
.badge{padding:5px 10px;border-radius:999px;font-weight:800;font-size:12px}
.actif{background:#dcfce7;color:#166534}
.inactif{background:#fee2e2;color:#991b1b}
@media(max-width:900px){.filters{grid-template-columns:1fr}.top{display:block}table{font-size:12px}}
</style>
</head>
<body>
<?php require_once "../includes/sidebar.php"; ?>

<main class="cp-main">
<div class="content">
    <div class="card">
        <div class="top">
            <div>
                <h2>Gestion des utilisateurs</h2>
                <p>Utilisateurs, rôles, affectations et statuts.</p>
            </div>

            <?php if(hasPermission('users','add')): ?>
                <a class="btn btn-primary" href="add.php">+ Nouvel utilisateur</a>
            <?php endif; ?>
        </div>

        <form class="filters" method="get">
            <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Recherche nom, email, téléphone">

            <select name="role_id">
                <option value="0">Tous les rôles</option>
                <?php foreach($roles as $r): ?>
                    <option value="<?= (int)$r['id'] ?>" <?= $role_id === (int)$r['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($r['nom_role']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="statut">
                <option value="">Tous statuts</option>
                <option value="actif" <?= $statut === 'actif' ? 'selected' : '' ?>>Actif</option>
                <option value="inactif" <?= $statut === 'inactif' ? 'selected' : '' ?>>Inactif</option>
            </select>

            <button class="btn btn-gray" type="submit">Filtrer</button>
        </form>

        <table>
            <thead>
                <tr>
                    <th>Photo</th>
                    <th>Utilisateur</th>
                    <th>Contact</th>
                    <th>Rôle</th>
                    <th>Affectation</th>
                    <th>Statut</th>
                    <th>Dernière connexion</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($users as $u): ?>
                <tr>
                    <td>
                        <?php if(!empty($u['photo'])): ?>
                            <img class="avatar" src="../uploads/users/<?= htmlspecialchars($u['photo']) ?>">
                        <?php else: ?>
                            <div class="avatar"></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong><?= htmlspecialchars($u['nom'] ?? '-') ?></strong><br>
                        <small>ID #<?= (int)$u['id'] ?></small>
                    </td>
                    <td>
                        <?= htmlspecialchars($u['email'] ?? '-') ?><br>
                        <small><?= htmlspecialchars($u['telephone'] ?? '-') ?></small>
                    </td>
                    <td><?= htmlspecialchars($u['nom_role'] ?? '-') ?></td>
                    <td>
                        Province: <?= htmlspecialchars($u['province_id'] ?? '-') ?><br>
                        Centre: <?= htmlspecialchars($u['centre_id'] ?? '-') ?><br>
                        Service: <?= htmlspecialchars($u['service_id'] ?? '-') ?>
                    </td>
                    <td>
                        <span class="badge <?= htmlspecialchars($u['statut'] ?? 'actif') ?>">
                            <?= htmlspecialchars($u['statut'] ?? 'actif') ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($u['derniere_connexion'] ?? '-') ?></td>
                    <td>
                        <a class="btn btn-gray" href="view.php?id=<?= (int)$u['id'] ?>">Voir</a>

                        <?php if(hasPermission('users','edit')): ?>
                            <a class="btn btn-gold" href="edit.php?id=<?= (int)$u['id'] ?>">Modifier</a>
                            <a class="btn btn-gray" href="reset_password.php?id=<?= (int)$u['id'] ?>">Mot de passe</a>
                            <a class="btn btn-gray" href="toggle_status.php?id=<?= (int)$u['id'] ?>">Statut</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if(empty($users)): ?>
                <tr>
                    <td colspan="8">Aucun utilisateur trouvé.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</main>
</body>
</html>
