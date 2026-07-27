<?php
require_once "../../config/database.php";
require_once "../../config/security.php";
require_once "../../core/functions.php";

checkAuth();

$message = "";

$provinces = $pdo->query("SELECT * FROM provinces WHERE actif=1 ORDER BY nom ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $province_id = $_POST['province_id'];
    $nom = trim($_POST['nom']);
    $code_centre = strtoupper(trim($_POST['code_centre']));
    $code_short = strtoupper(trim($_POST['code_centre_short']));
    $adresse = trim($_POST['adresse']);

    $stmt = $pdo->prepare("
        INSERT INTO centres (province_id, nom, code_centre, code_centre_short, adresse)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$province_id, $nom, $code_centre, $code_short, $adresse]);

    $message = "Centre ajouté avec succès.";
}

$items = $pdo->query("
    SELECT c.*, p.nom AS province
    FROM centres c
    JOIN provinces p ON c.province_id = p.id
    ORDER BY c.id DESC
")->fetchAll();

$page_title = "Gestion des Centres";
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
            <h3>Ajouter un Centre</h3>

            <?php if ($message): ?>
                <p style="color:green;font-weight:bold;"><?= $message ?></p>
            <?php endif; ?>

            <form method="POST">
                <select name="province_id" required>
                    <option value="">-- Province --</option>
                    <?php foreach ($provinces as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nom']) ?></option>
                    <?php endforeach; ?>
                </select>

                <input type="text" name="nom" placeholder="Nom du centre" required>
                <input type="text" name="code_centre" placeholder="Code complet ex: CENTRE-BUTA" required>
                <input type="text" name="code_centre_short" placeholder="Code court ex: BUT" required>
                <input type="text" name="adresse" placeholder="Adresse">
                <button type="submit">Enregistrer</button>
            </form>
        </div>

        <div class="panel">
            <h3>Liste des Centres</h3>

            <table class="table-premium">
                <tr>
                    <th>ID</th>
                    <th>Province</th>
                    <th>Centre</th>
                    <th>Code</th>
                    <th>Code court</th>
                </tr>

                <?php foreach ($items as $i): ?>
                    <tr>
                        <td><?= $i['id'] ?></td>
                        <td><?= htmlspecialchars($i['province']) ?></td>
                        <td><?= htmlspecialchars($i['nom']) ?></td>
                        <td><?= htmlspecialchars($i['code_centre']) ?></td>
                        <td><?= htmlspecialchars($i['code_centre_short']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </main>
</div>
</body>
</html>