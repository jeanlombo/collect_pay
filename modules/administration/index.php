<?php
require_once "../../config/database.php";
require_once "../../config/security.php";

checkAuth();
requireRole([
    'SUPER_ADMIN'
]);

$page_title = "Administration";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Administration | cOllect_Pay</title>
<link rel="stylesheet" href="/collect_pay/assets/css/admin.css">
</head>

<body>

<div class="admin-layout">

<?php require_once "../../includes/sidebar.php"; ?>

<main class="main-content">

<?php require_once "../../includes/topbar.php"; ?>

<div class="hero-luxoria">
    <h2>Administration du système</h2>
    <p>Gestion des utilisateurs, rôles, permissions et audit.</p>
</div>

<div class="dashboard-grid">

    <a class="dashboard-card" href="users.php">
        <h3>👤 Utilisateurs</h3>
        <p>Gestion complète des comptes utilisateurs.</p>
    </a>

    <a class="dashboard-card" href="roles.php">
        <h3>🛡️ Rôles</h3>
        <p>Gestion des rôles du système.</p>
    </a>

    <a class="dashboard-card" href="permissions.php">
        <h3>🔑 Permissions</h3>
        <p>Gestion des accès et privilèges.</p>
    </a>

    <a class="dashboard-card" href="audit.php">
        <h3>📋 Audit</h3>
        <p>Suivi des opérations sensibles.</p>
    </a>

    <a class="dashboard-card" href="journaux.php">
        <h3>📚 Journaux</h3>
        <p>Historique détaillé du système.</p>
    </a>

</div>

</main>

</div>

<style>

.hero-luxoria{
    background:linear-gradient(135deg,#06152b,#0f3460);
    color:white;
    padding:25px;
    border-radius:20px;
    margin-bottom:20px;
}

.dashboard-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(260px,1fr));
    gap:20px;
}

.dashboard-card{
    display:block;
    background:white;
    border-radius:18px;
    padding:25px;
    text-decoration:none;
    color:#111827;
    box-shadow:0 10px 25px rgba(0,0,0,.08);
    transition:.2s;
}

.dashboard-card:hover{
    transform:translateY(-4px);
}

.dashboard-card h3{
    color:#0f3460;
    margin-bottom:10px;
}

</style>

</body>
</html>