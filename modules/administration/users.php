<?php
require_once "../../config/database.php";
require_once "../../config/security.php";
require_once "../../core/functions.php";

checkAuth();
requireRole([
    'SUPER_ADMIN'
]);

$message = "";

$roles = $pdo->query("SELECT * FROM roles ORDER BY nom_role ASC")->fetchAll();
$provinces = $pdo->query("SELECT * FROM provinces WHERE actif=1 ORDER BY nom ASC")->fetchAll();
$centres = $pdo->query("
    SELECT c.*, p.nom AS province
    FROM centres c
    JOIN provinces p ON c.province_id = p.id
    WHERE c.actif=1
    ORDER BY p.nom, c.nom
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom']);
    $email = trim($_POST['email']);
    $telephone = trim($_POST['telephone']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role_id = $_POST['role_id'];
    $province_id = $_POST['province_id'];
    $centre_id = $_POST['centre_id'] ?: null;
    $niveau = $_POST['niveau'];

    $stmt = $pdo->prepare("
        INSERT INTO users
        (nom, email, telephone, password, role_id, province_id, centre_id, niveau)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $nom,
        $email,
        $telephone,
        $password,
        $role_id,
        $province_id,
        $centre_id,
        $niveau
    ]);

    $message = "Utilisateur créé avec succès.";
}

$users = $pdo->query("
    SELECT u.*, r.nom_role, p.nom AS province, c.nom AS centre
    FROM users u
    LEFT JOIN roles r ON u.role_id = r.id
    LEFT JOIN provinces p ON u.province_id = p.id
    LEFT JOIN centres c ON u.centre_id = c.id
    ORDER BY u.id DESC
")->fetchAll();

$page_title = "Gestion des Utilisateurs";
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
            <h3>Créer un Utilisateur</h3>

            <?php if ($message): ?>
                <p style="color:green;font-weight:bold;"><?= $message ?></p>
            <?php endif; ?>

            <form method="POST">
                <input type="text" name="nom" placeholder="Nom complet" required>
                <input type="email" name="email" placeholder="Email" required>
                <input type="text" name="telephone" placeholder="Téléphone">
                <input type="password" name="password" placeholder="Mot de passe" required>

                <select name="role_id" required>
                    <option value="">-- Rôle --</option>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['nom_role']) ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="province_id" required>
                    <option value="">-- Province --</option>
                    <?php foreach ($provinces as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nom']) ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="centre_id">
                    <option value="">-- Centre optionnel --</option>
                    <?php foreach ($centres as $c): ?>
                        <option value="<?= $c['id'] ?>">
                            <?= htmlspecialchars($c['province']) ?> / <?= htmlspecialchars($c['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="niveau" required>
                    <option value="centre">Centre</option>
                    <option value="province">Province</option>
                    <option value="national">National</option>
                </select>

                <button type="submit">Créer l'utilisateur</button>
            </form>
        </div>

        <div class="panel">
            <h3>Liste des Utilisateurs</h3>

            <table class="table-premium">
                <tr>
                    <th>Nom</th>
                    <th>Email</th>
                    <th>Rôle</th>
                    <th>Province</th>
                    <th>Centre</th>
                    <th>Niveau</th>
                    <th>Statut</th>
                </tr>

                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= htmlspecialchars($u['nom']) ?></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td><?= htmlspecialchars($u['nom_role']) ?></td>
                        <td><?= htmlspecialchars($u['province'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($u['centre'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($u['niveau']) ?></td>
                        <td><?= $u['actif'] ? 'Actif' : 'Inactif' ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </main>
</div>
</body>
</html>