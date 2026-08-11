<?php
require_once "../../config/database.php";
require_once "../../config/security.php";
require_once "../../core/functions.php";

checkAuth();
requireRole(['SUPER_ADMIN','ADMIN','PARAMETRAGE']);

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom']);
    $code = strtoupper(trim($_POST['code_province']));

    if ($nom && $code) {
        $stmt = $pdo->prepare("INSERT INTO provinces (nom, code_province) VALUES (?, ?)");
        $stmt->execute([$nom, $code]);
        $message = "Province ajoutée avec succès.";
    }
}

$items = $pdo->query("SELECT * FROM provinces ORDER BY id DESC")->fetchAll();

$page_title = "Gestion des Provinces";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= $page_title ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../../assets/css/admin.css">
<link rel="stylesheet" href="../../assets/css/parametrage.css">
</head>
<body class="cp-parametrage-page">
<div class="admin-layout">
    <?php require_once "../../includes/sidebar.php"; ?>

    <main class="main-content">
        <?php require_once "../../includes/topbar.php"; ?>

        <div class="panel cp-parametrage-panel">
            <h3>Ajouter une Province</h3>

            <?php if ($message): ?>
                <p style="color:green;font-weight:bold;"><?= $message ?></p>
            <?php endif; ?>

            <form method="POST">
                <input type="text" name="nom" placeholder="Nom province" required>
                <input type="text" name="code_province" placeholder="Code ex: BU" required>
                <button type="submit">Enregistrer</button>
            </form>
        </div>

        <div class="panel cp-parametrage-panel">
            <h3>Liste des Provinces</h3>

            <table class="table-premium cp-parametrage-table">
                <tr>
                    <th>ID</th>
                    <th>Province</th>
                    <th>Code</th>
                    <th>Statut</th>
                </tr>

                <?php foreach ($items as $i): ?>
                    <tr>
                        <td><?= $i['id'] ?></td>
                        <td><?= htmlspecialchars($i['nom']) ?></td>
                        <td><?= htmlspecialchars($i['code_province']) ?></td>
                        <td><?= $i['actif'] ? 'Actif' : 'Inactif' ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </main>
</div>
</body>
</html>