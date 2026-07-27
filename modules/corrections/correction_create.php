<?php
/*
|--------------------------------------------------------------------------
| cOllect_Pay - Recherche document à corriger
|--------------------------------------------------------------------------
*/
require_once "../../auth/check_auth.php";

checkAuth();
requirePermission('corrections','create');

$page_title = "Nouvelle correction";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $numero = trim($_POST['numero_document'] ?? '');

    if ($numero === '') {
        $error = "Veuillez saisir le numéro du document.";
    } else {
        header("Location: correction_view.php?numero=" . urlencode($numero));
        exit;
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($page_title) ?> | cOllect_Pay</title>
<link rel="stylesheet" href="/collect_pay/assets/css/admin.css">
<style>
.hero{background:linear-gradient(135deg,#7c2d12,#f59e0b);color:#111827;padding:26px;border-radius:24px;margin-bottom:22px}
.hero h2{margin:0;font-weight:1000}.hero p{margin:8px 0 0;font-weight:800}
.search-box{max-width:850px}
label{display:block;font-weight:900;margin-top:10px}
input{width:100%;padding:14px;border:1px solid #d1d5db;border-radius:12px;font-weight:900}
.btn{display:inline-block;background:#0f3460;color:white;padding:11px 16px;border-radius:12px;text-decoration:none;font-weight:900;border:0;margin-top:15px;cursor:pointer}
.btn-gray{background:#e5e7eb;color:#111827}
.alert{background:#fee2e2;color:#991b1b;padding:12px;border-radius:12px;font-weight:900;margin-bottom:12px}
</style>
</head>
<body>
<div class="admin-layout">
<?php require_once "../../includes/sidebar.php"; ?>

<main class="main-content">
<?php require_once "../../includes/topbar.php"; ?>

<div class="hero">
    <h2>Nouvelle correction</h2>
    <p>Rechercher le document, afficher l’aperçu, puis effectuer la rectification administrative.</p>
</div>

<div class="panel search-box">
    <?php if($error): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="POST">
        <label>Numéro du document à corriger</label>
        <input name="numero_document" placeholder="Ex : NT-BU-CPR-26-000001, ND-BU-CPR-26-000001, NP-BU-CPR-26-000001, QT-BU-CPR-26-000001" required>

        <button type="submit" class="btn">Afficher le document</button>
        <a href="corrections_list.php" class="btn btn-gray">Retour</a>
    </form>
</div>

</main>
</div>
</body>
</html>
