<?php
require_once "../auth/check_auth.php";
require_once "../config/database.php";
require_once "../auth/permissions.php";

requirePermission('roles', 'add');

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom_role = trim($_POST['nom_role'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $statut = $_POST['statut'] ?? 'actif';

    if ($nom_role === '') {
        $error = "Le nom du rôle est obligatoire.";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO roles (nom_role, description, statut)
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param("sss", $nom_role, $description, $statut);

        if ($stmt->execute()) {
            logAction('roles', 'add', "Création du rôle : ".$nom_role);
            header("Location: index.php?success=1");
            exit;
        }

        $error = "Erreur : ".$conn->error;
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Nouveau rôle</title>
<link rel="stylesheet" href="../assets/css/style.css">
<style>
.page{padding:25px}.card{max-width:760px;background:white;padding:22px;border-radius:18px;box-shadow:0 8px 25px rgba(0,0,0,.08)}
label{display:block;margin-top:12px;font-weight:800}input,textarea,select{width:100%;padding:12px;border:1px solid #d1d5db;border-radius:10px}
.btn{display:inline-block;margin-top:16px;padding:11px 16px;border-radius:10px;text-decoration:none;font-weight:800;border:none;cursor:pointer}
.btn-primary{background:#0f3460;color:white}.btn-secondary{background:#e5e7eb;color:#111827}.error{background:#fee2e2;color:#991b1b;padding:12px;border-radius:10px}
</style>
</head>
<body>
<div class="page">
    <div class="card">
        <h2>Nouveau rôle</h2>

        <?php if($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <label>Nom du rôle</label>
            <input type="text" name="nom_role" required>

            <label>Description</label>
            <textarea name="description" rows="4"></textarea>

            <label>Statut</label>
            <select name="statut">
                <option value="actif">Actif</option>
                <option value="inactif">Inactif</option>
            </select>

            <button class="btn btn-primary" type="submit">Enregistrer</button>
            <a class="btn btn-secondary" href="index.php">Annuler</a>
        </form>
    </div>
</div>
</body>
</html>
