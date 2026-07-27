<?php
require_once "../auth/check_auth.php";
require_once "../auth/permissions.php";

requirePermission('users', 'edit');

$db = cpDb();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = null;

$stmt = $db->prepare("SELECT id, nom FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("Utilisateur introuvable.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $p1 = $_POST['password'] ?? '';
    $p2 = $_POST['password2'] ?? '';

    if ($p1 === '' || $p1 !== $p2) {
        $error = "Les mots de passe ne correspondent pas.";
    } else {
        $hash = password_hash($p1, PASSWORD_DEFAULT);

        $stmt = $db->prepare("
            UPDATE users
            SET password = ?,
                updated_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([$hash, $id]);

        logAction('users', 'reset_password', "Réinitialisation mot de passe utilisateur ID ".$id);

        header("Location: view.php?id=".$id."&password=1");
        exit;
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Réinitialiser mot de passe</title>
<style>
body{margin:0;background:#f8fafc;font-family:Arial}
.content{padding:25px}
.card{max-width:600px;background:white;padding:22px;border-radius:18px;box-shadow:0 8px 25px rgba(0,0,0,.08)}
label{font-weight:800;display:block;margin-top:12px}
input{width:100%;padding:12px;border:1px solid #d1d5db;border-radius:10px}
.btn{display:inline-block;margin-top:16px;padding:11px 16px;border-radius:10px;text-decoration:none;font-weight:800;border:none}
.btn-primary{background:#0f3460;color:white}
.btn-gray{background:#e5e7eb;color:#111827}
.error{background:#fee2e2;color:#991b1b;padding:12px;border-radius:10px}
</style>
</head>
<body>
<?php require_once "../includes/sidebar.php"; ?>

<main class="cp-main">
<div class="content">
<div class="card">
<h2>Réinitialiser mot de passe</h2>
<p>Utilisateur : <strong><?= htmlspecialchars($user['nom'] ?? '-') ?></strong></p>

<?php if($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="post">
    <label>Nouveau mot de passe</label>
    <input type="password" name="password" required>

    <label>Confirmer</label>
    <input type="password" name="password2" required>

    <button class="btn btn-primary" type="submit">Mettre à jour</button>
    <a class="btn btn-gray" href="view.php?id=<?= (int)$id ?>">Annuler</a>
</form>
</div>
</div>
</main>
</body>
</html>
