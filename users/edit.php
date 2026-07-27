<?php
require_once "../auth/check_auth.php";
require_once "../auth/permissions.php";

requirePermission('users', 'edit');

$db = cpDb();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("Utilisateur introuvable.");
}

$roles = $db->query("
    SELECT id, nom_role
    FROM roles
    ORDER BY nom_role ASC
")->fetchAll(PDO::FETCH_ASSOC);

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $role_id = (int)($_POST['role_id'] ?? 0);
    $province_id = !empty($_POST['province_id']) ? (int)$_POST['province_id'] : null;
    $centre_id = !empty($_POST['centre_id']) ? (int)$_POST['centre_id'] : null;
    $service_id = !empty($_POST['service_id']) ? (int)$_POST['service_id'] : null;
    $statut = $_POST['statut'] ?? 'actif';

    $photoName = $user['photo'] ?? null;

    if (!empty($_FILES['photo']['name'])) {
        $dir = __DIR__ . "/../uploads/users/";

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $photoName = "user_" . time() . "_" . rand(1000,9999) . "." . strtolower($ext);
        move_uploaded_file($_FILES['photo']['tmp_name'], $dir . $photoName);
    }

    if ($nom === '' || $email === '' || $role_id <= 0) {
        $error = "Veuillez remplir les champs obligatoires.";
    } else {
        $stmt = $db->prepare("
            UPDATE users
            SET nom = ?,
                email = ?,
                telephone = ?,
                role_id = ?,
                province_id = ?,
                centre_id = ?,
                service_id = ?,
                photo = ?,
                statut = ?,
                updated_at = NOW()
            WHERE id = ?
        ");

        if ($stmt->execute([
            $nom,
            $email,
            $telephone,
            $role_id,
            $province_id,
            $centre_id,
            $service_id,
            $photoName,
            $statut,
            $id
        ])) {
            logAction('users', 'edit', "Modification utilisateur : ".$nom);
            header("Location: index.php?updated=1");
            exit;
        }

        $error = "Erreur lors de la mise à jour.";
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Modifier utilisateur</title>
<style>
body{margin:0;background:#f8fafc;font-family:Arial,sans-serif}
.content{padding:25px}
.card{max-width:850px;background:white;border-radius:18px;padding:22px;box-shadow:0 8px 25px rgba(0,0,0,.08)}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
label{font-weight:800;margin-top:10px;display:block}
input,select{width:100%;padding:12px;border:1px solid #d1d5db;border-radius:10px}
.btn{display:inline-block;margin-top:16px;padding:11px 16px;border-radius:10px;text-decoration:none;font-weight:800;border:none;cursor:pointer}
.btn-primary{background:#0f3460;color:white}
.btn-gray{background:#e5e7eb;color:#111827}
.error{background:#fee2e2;color:#991b1b;padding:12px;border-radius:10px;margin-bottom:12px}
@media(max-width:800px){.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<?php require_once "../includes/sidebar.php"; ?>

<main class="cp-main">
<div class="content">
<div class="card">
<h2>Modifier utilisateur</h2>

<?php if($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">
<div class="grid">
    <div>
        <label>Nom complet *</label>
        <input name="nom" value="<?= htmlspecialchars($user['nom'] ?? '') ?>" required>
    </div>

    <div>
        <label>Email *</label>
        <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
    </div>

    <div>
        <label>Téléphone</label>
        <input name="telephone" value="<?= htmlspecialchars($user['telephone'] ?? '') ?>">
    </div>

    <div>
        <label>Rôle *</label>
        <select name="role_id" required>
            <option value="">-- Choisir --</option>
            <?php foreach($roles as $r): ?>
                <option value="<?= (int)$r['id'] ?>" <?= (int)($user['role_id'] ?? 0) === (int)$r['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($r['nom_role']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div>
        <label>Province ID</label>
        <input type="number" name="province_id" value="<?= htmlspecialchars($user['province_id'] ?? '') ?>">
    </div>

    <div>
        <label>Centre ID</label>
        <input type="number" name="centre_id" value="<?= htmlspecialchars($user['centre_id'] ?? '') ?>">
    </div>

    <div>
        <label>Service ID</label>
        <input type="number" name="service_id" value="<?= htmlspecialchars($user['service_id'] ?? '') ?>">
    </div>

    <div>
        <label>Statut</label>
        <select name="statut">
            <option value="actif" <?= ($user['statut'] ?? '') === 'actif' ? 'selected' : '' ?>>Actif</option>
            <option value="inactif" <?= ($user['statut'] ?? '') === 'inactif' ? 'selected' : '' ?>>Inactif</option>
        </select>
    </div>

    <div>
        <label>Photo</label>
        <input type="file" name="photo" accept="image/*">
    </div>
</div>

<button class="btn btn-primary" type="submit">Mettre à jour</button>
<a class="btn btn-gray" href="index.php">Retour</a>
</form>
</div>
</div>
</main>
</body>
</html>
