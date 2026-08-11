<?php
require_once "../../config/security.php";

checkAuth();
requireRole(['SUPER_ADMIN']);

$page_title = "Sauvegardes";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($page_title) ?> | cOllect_Pay</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../../assets/css/admin.css">
<link rel="stylesheet" href="../../assets/css/administration.css">
</head>
<body class="cp-administration-page">

<div class="admin-layout">
<?php require_once "../../includes/sidebar.php"; ?>

<main class="main-content">
<?php require_once "../../includes/topbar.php"; ?>

<div class="panel cp-administration-panel">
    <h2>Sauvegardes</h2>
    <p>
        Module réservé à la gestion des sauvegardes du système cOllect_Pay.
    </p>

    <div style="background:#eff6ff;color:#1e3a8a;padding:14px;border-radius:14px;font-weight:900;margin-top:15px;">
        ✅ Accès autorisé au Directeur de Recouvrement avec la permission :
        <br>administration / backup
    </div>

    <br>

    <a href="../../dashboard/index.php"
       style="display:inline-block;background:#0f3460;color:white;padding:12px 16px;border-radius:12px;text-decoration:none;font-weight:900;">
        Retour au tableau de bord
    </a>
</div>

</main>
</div>

</body>
</html>