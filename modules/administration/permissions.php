<?php
require_once "../../config/database.php";
require_once "../../config/security.php";
require_once "../../core/functions.php";

checkAuth();
requireRole([
    'SUPER_ADMIN'
]);

$page_title = "Permissions";
$message = "";

$roles = $pdo->query("SELECT * FROM roles ORDER BY nom_role")->fetchAll();

$modules = $pdo->query("
    SELECT DISTINCT LOWER(module) AS module
    FROM permissions
    WHERE module IS NOT NULL AND module <> ''
    ORDER BY module
")->fetchAll(PDO::FETCH_COLUMN);

$actions = $pdo->query("
    SELECT DISTINCT action
    FROM permissions
    WHERE action IS NOT NULL AND action <> ''
    ORDER BY action
")->fetchAll(PDO::FETCH_COLUMN);

if (!$modules) {
    $modules = ['administration','parametrage','contribuables','constatation','liquidation','controle','ordonnancement','recouvrement','penalites','inspection','corrections','rapports'];
}
if (!$actions) {
    $actions = ['view','add','edit','delete','validate','print','export'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role_id = (int)($_POST['role_id'] ?? 0);
    $module = trim((string)($_POST['module'] ?? ''));
    $action = trim((string)($_POST['action'] ?? ''));
    $autorise = isset($_POST['autorise']) ? 1 : 0;

    $stmt = $pdo->prepare("
        INSERT INTO permissions (role_id, module, action, autorise)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$role_id, $module, $action, $autorise]);

    $message = "Permission enregistrée.";
}

$permissions = $pdo->query("
    SELECT p.*, r.nom_role
    FROM permissions p
    JOIN roles r ON p.role_id=r.id
    ORDER BY p.id DESC
")->fetchAll();
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
            <h3>Ajouter une permission</h3>

            <?php if ($message): ?>
                <p style="color:green;font-weight:bold;"><?= htmlspecialchars($message) ?></p>
            <?php endif; ?>

            <form method="POST">
                <select name="role_id" required>
                    <option value="">-- Rôle --</option>
                    <?php foreach($roles as $r): ?>
                        <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['nom_role']) ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="module" required>
                    <?php foreach($modules as $m): ?>
                        <option value="<?= $m ?>"><?= ucfirst($m) ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="action" required>
                    <?php foreach($actions as $a): ?>
                        <option value="<?= $a ?>"><?= ucfirst($a) ?></option>
                    <?php endforeach; ?>
                </select>

                <label>
                    <input type="checkbox" name="autorise" checked>
                    Autorisé
                </label>

                <button type="submit">Enregistrer</button>
            </form>
        </div>

        <div class="panel cp-administration-panel">
            <h3>Liste des permissions</h3>
            <table class="table-premium cp-administration-table">
                <tr>
                    <th>Rôle</th>
                    <th>Module</th>
                    <th>Action</th>
                    <th>Autorisé</th>
                </tr>
                <?php foreach($permissions as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['nom_role']) ?></td>
                        <td><?= htmlspecialchars($p['module']) ?></td>
                        <td><?= htmlspecialchars($p['action']) ?></td>
                        <td><?= $p['autorise'] ? 'Oui' : 'Non' ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php if(empty($permissions)): ?>
                    <tr><td colspan="4">Aucune permission configurée.</td></tr>
                <?php endif; ?>
            </table>
        </div>
    </main>
</div>
</body>
</html>