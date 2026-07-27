<?php
require_once "../../config/database.php";
require_once "../../config/security.php";
require_once "../../core/functions.php";

checkAuth();
requireRole([
    'SUPER_ADMIN'
]);

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom_role = strtoupper(trim($_POST['nom_role']));

    if ($nom_role) {
        $stmt = $pdo->prepare("INSERT INTO roles (nom_role) VALUES (?)");
        $stmt->execute([$nom_role]);
        $message = "Rôle ajouté avec succès.";
    }
}

$roles = $pdo->query("SELECT * FROM roles ORDER BY id ASC")->fetchAll();

$page_title = "Gestion des Rôles";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= $page_title ?></title>
    <link rel="stylesheet" href="/collect_pay/assets/css/admin.css">
</head>
<body>
<div class="admin-layout">
    <?php require_once "../../includes/sidebar.php"; ?>

    <main class="main-content">
        <?php require_once "../../includes/topbar.php"; ?>

        <div class="panel">
            <h3>Ajouter un Rôle</h3>

            <?php if ($message): ?>
                <p style="color:green;font-weight:bold;"><?= $message ?></p>
            <?php endif; ?>

            <form method="POST">
                <input type="text" name="nom_role" placeholder="Ex: ORDONNATEUR" required>
                <button type="submit">Enregistrer</button>
            </form>
        </div>

        <div class="panel">
            <h3>Liste des Rôles</h3>

            <table class="table-premium">
                <tr>
                    <th>ID</th>
                    <th>Rôle</th>
                </tr>

                <?php foreach ($roles as $r): ?>
                    <tr>
                        <td><?= $r['id'] ?></td>
                        <td><?= htmlspecialchars($r['nom_role']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </main>
</div>
</body>
</html>