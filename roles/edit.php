<?php
require_once "../auth/check_auth.php";
require_once "../auth/permissions.php";

requirePermission('roles', 'edit');

$db = cpDb();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $db->prepare("SELECT * FROM roles WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$role = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$role) {
    die("Rôle introuvable.");
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom_role = trim($_POST['nom_role'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $statut = $_POST['statut'] ?? 'actif';

    if ($nom_role === '') {
        $error = "Le nom du rôle est obligatoire.";
    } else {
        $stmt = $db->prepare("
            UPDATE roles
            SET nom_role = ?,
                description = ?,
                statut = ?,
                updated_at = NOW()
            WHERE id = ?
        ");

        if ($stmt->execute([$nom_role, $description, $statut, $id])) {
            logAction('roles', 'edit', "Modification du rôle : ".$nom_role);
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
<title>Modifier rôle</title>
<style>
body{margin:0;background:#f8fafc;font-family:Arial,sans-serif}
.content{padding:25px}
.card{max-width:760px;background:white;padding:22px;border-radius:18px;box-shadow:0 8px 25px rgba(0,0,0,.08)}
label{display:block;margin-top:12px;font-weight:800}
input,textarea,select{width:100%;padding:12px;border:1px solid #d1d5db;border-radius:10px}
.btn{display:inline-block;margin-top:16px;padding:11px 16px;border-radius:10px;text-decoration:none;font-weight:800;border:none;cursor:pointer}
.btn-primary{background:#0f3460;color:white}
.btn-secondary{background:#e5e7eb;color:#111827}
.error{background:#fee2e2;color:#991b1b;padding:12px;border-radius:10px}
</style>
</head>
<body>
<?php require_once "../includes/sidebar.php"; ?>

<main class="cp-main">
<div class="content">
    <div class="card">
        <h2>Modifier rôle</h2>

        <?php if($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <label>Nom du rôle</label>
            <input type="text" name="nom_role" value="<?= htmlspecialchars($role['nom_role'] ?? '') ?>" required>

            <label>Description</label>
            <textarea name="description" rows="4"><?= htmlspecialchars($role['description'] ?? '') ?></textarea>

            <label>Statut</label>
            <select name="statut">
                <option value="actif" <?= ($role['statut'] ?? '') === 'actif' ? 'selected' : '' ?>>Actif</option>
                <option value="inactif" <?= ($role['statut'] ?? '') === 'inactif' ? 'selected' : '' ?>>Inactif</option>
            </select>

            <button class="btn btn-primary" type="submit">Mettre à jour</button>
            <a class="btn btn-secondary" href="index.php">Retour</a>
        </form>
    </div>
</div>
</main>
</body>
</html>
