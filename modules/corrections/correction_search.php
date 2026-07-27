<?php
require_once "../../config/database.php";
require_once "../../config/security.php";

checkAuth();
requireRole(['SUPER_ADMIN','ADMIN','CONTROLEUR','DIRECTEUR_RECOUVREMENT']);

$page_title = "Recherche document à corriger";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $numero = trim($_POST['numero_document'] ?? '');

    if ($numero === '') {
        $error = "Veuillez saisir le numéro du document.";
    } else {
        header("Location: /collect_pay/modules/corrections/correction_view.php?numero=" . urlencode($numero));
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($page_title) ?> | cOllect_Pay</title>
<link rel="stylesheet" href="/collect_pay/assets/css/admin.css">
<style>
.hero-correction{background:linear-gradient(135deg,#06152b,#0f3460);color:white;padding:28px;border-radius:24px;margin-bottom:22px}
.box{max-width:760px;margin:auto}
.err{background:#fee2e2;color:#991b1b;padding:14px;border-radius:14px;font-weight:800;margin-bottom:14px}
.hint{background:#f8fafc;border:1px solid #e5e7eb;border-radius:18px;padding:18px;margin-top:18px}
</style>
</head>
<body>
<div class="admin-layout">
<?php require_once "../../includes/sidebar.php"; ?>

<main class="main-content">
<?php require_once "../../includes/topbar.php"; ?>

<div class="hero-correction">
    <h2>Corrections Administratives</h2>
    <p>Rechercher un document fiscal par numéro pour rectification contrôlée.</p>
</div>

<div class="panel box">
    <h3>Rechercher un document</h3>

    <?php if($error): ?>
        <div class="err"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="/collect_pay/modules/corrections/correction_search.php">
        <label>Numéro du document</label>
        <input type="text"
               name="numero_document"
               placeholder="Ex : NT-BU-CPR-26-000055, NP-BU-CPR-26-000012, QT-BU-CPR-26-000006"
               required>
        <br><br>
        <button type="submit">🔎 Rechercher</button>
    </form>

    <div class="hint">
        <strong>Documents acceptés :</strong> NT, ND, NP / NPF, QT, AMR, AVF.
        <br><br>
        <strong>Important :</strong> les montants, soldes et pénalités ne sont jamais modifiables.
    </div>
</div>

</main>
</div>
</body>
</html>
