<?php
require_once "../../config/database.php";
require_once "../../config/security.php";
require_once "../../core/functions.php";

checkAuth();
requireRole(['SUPER_ADMIN','ADMIN','PARAMETRAGE']);

$message = "";

$centres = $pdo->query("
    SELECT c.*, p.nom AS province
    FROM centres c
    JOIN provinces p ON c.province_id = p.id
    WHERE c.actif=1
    ORDER BY p.nom, c.nom
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $centre_id = $_POST['centre_id'];
    $nom_service = trim($_POST['nom_service']);
    $code_service = strtoupper(trim($_POST['code_service']));

    $stmt = $pdo->prepare("
        INSERT INTO services_assiette (centre_id, nom_service, code_service)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$centre_id, $nom_service, $code_service]);

    $message = "Service ajouté avec succès.";
}

$items = $pdo->query("
    SELECT s.*, c.nom AS centre, p.nom AS province
    FROM services_assiette s
    JOIN centres c ON s.centre_id = c.id
    JOIN provinces p ON c.province_id = p.id
    ORDER BY s.id DESC
")->fetchAll();

$page_title = "Services d’Assiette";
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
            <h3>Ajouter un Service</h3>

            <?php if ($message): ?>
                <p style="color:green;font-weight:bold;"><?= $message ?></p>
            <?php endif; ?>

            <form method="POST">
                <select name="centre_id" required>
                    <option value="">-- Centre --</option>
                    <?php foreach ($centres as $c): ?>
                        <option value="<?= $c['id'] ?>">
                            <?= htmlspecialchars($c['province']) ?> / <?= htmlspecialchars($c['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <input type="text" name="nom_service" placeholder="Nom service" required>
                <input type="text" name="code_service" placeholder="Code service ex: ENV" required>
                <button type="submit">Enregistrer</button>
            </form>
        </div>

        <div class="panel cp-parametrage-panel">
            <h3>Liste des Services</h3>

            <table class="table-premium cp-parametrage-table">
                <tr>
                    <th>ID</th>
                    <th>Province</th>
                    <th>Centre</th>
                    <th>Service</th>
                    <th>Code</th>
                </tr>

                <?php foreach ($items as $i): ?>
                    <tr>
                        <td><?= $i['id'] ?></td>
                        <td><?= htmlspecialchars($i['province']) ?></td>
                        <td><?= htmlspecialchars($i['centre']) ?></td>
                        <td><?= htmlspecialchars($i['nom_service']) ?></td>
                        <td><?= htmlspecialchars($i['code_service']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </main>
</div>
</body>
</html>