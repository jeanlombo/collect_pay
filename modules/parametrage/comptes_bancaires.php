<?php
require_once "../../config/database.php";
require_once "../../config/security.php";
require_once "../../core/functions.php";

checkAuth();

$page_title = "Comptes bancaires";
$message = "";

$provinces = $pdo->query("SELECT * FROM provinces WHERE actif=1 ORDER BY nom")->fetchAll();
$centres = $pdo->query("
    SELECT c.*, p.nom AS province
    FROM centres c
    JOIN provinces p ON c.province_id=p.id
    WHERE c.actif=1
    ORDER BY p.nom, c.nom
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $province_id = $_POST['province_id'] ?: null;
    $centre_id = $_POST['centre_id'] ?: null;
    $banque = trim($_POST['banque']);
    $numero_compte = trim($_POST['numero_compte']);
    $devise = $_POST['devise'];
    $intitule_compte = trim($_POST['intitule_compte']);

    $stmt = $pdo->prepare("
        INSERT INTO comptes_bancaires
        (province_id, centre_id, banque, numero_compte, devise, intitule_compte, actif)
        VALUES (?, ?, ?, ?, ?, ?, 1)
    ");
    $stmt->execute([$province_id, $centre_id, $banque, $numero_compte, $devise, $intitule_compte]);

    $message = "Compte bancaire enregistré avec succès.";
}

$comptes = $pdo->query("
    SELECT cb.*, p.nom AS province, c.nom AS centre
    FROM comptes_bancaires cb
    LEFT JOIN provinces p ON cb.province_id=p.id
    LEFT JOIN centres c ON cb.centre_id=c.id
    ORDER BY cb.id DESC
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

        <div class="panel">
            <h3>Ajouter un compte bancaire</h3>

            <?php if ($message): ?>
                <p style="color:green;font-weight:bold;"><?= htmlspecialchars($message) ?></p>
            <?php endif; ?>

            <form method="POST">
                <select name="province_id">
                    <option value="">-- Province --</option>
                    <?php foreach($provinces as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nom']) ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="centre_id">
                    <option value="">-- Centre optionnel --</option>
                    <?php foreach($centres as $c): ?>
                        <option value="<?= $c['id'] ?>">
                            <?= htmlspecialchars($c['province']) ?> / <?= htmlspecialchars($c['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <input type="text" name="banque" placeholder="Banque ex: FirstBANK" required>
                <input type="text" name="numero_compte" placeholder="Numéro compte" required>

                <select name="devise" required>
                    <option value="CDF">CDF</option>
                    <option value="USD">USD</option>
                </select>

                <input type="text" name="intitule_compte" placeholder="Intitulé du compte">

                <button type="submit">Enregistrer</button>
            </form>
        </div>

        <div class="panel">
            <h3>Liste des comptes bancaires</h3>

            <table class="table-premium">
                <tr>
                    <th>Banque</th>
                    <th>Compte</th>
                    <th>Devise</th>
                    <th>Province</th>
                    <th>Centre</th>
                    <th>Intitulé</th>
                    <th>Statut</th>
                </tr>

                <?php foreach($comptes as $c): ?>
                    <tr>
                        <td><?= htmlspecialchars($c['banque']) ?></td>
                        <td><strong><?= htmlspecialchars($c['numero_compte']) ?></strong></td>
                        <td><?= htmlspecialchars($c['devise']) ?></td>
                        <td><?= htmlspecialchars($c['province'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($c['centre'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($c['intitule_compte'] ?? '-') ?></td>
                        <td><?= $c['actif'] ? 'Actif' : 'Inactif' ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php if(empty($comptes)): ?>
                    <tr><td colspan="7">Aucun compte bancaire configuré.</td></tr>
                <?php endif; ?>
            </table>
        </div>
    </main>
</div>
</body>
</html>