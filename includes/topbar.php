<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$nom  = $_SESSION['nom'] ?? 'Utilisateur';
$role = $_SESSION['role'] ?? ($_SESSION['nom_role'] ?? '');
?>

<div class="topbar">
    <div class="topbar-left">
        <div>
            <h4 class="page-title"><?= htmlspecialchars($page_title ?? 'Tableau de bord', ENT_QUOTES, 'UTF-8') ?></h4>
            <p class="welcome-text">
                Bienvenue <strong><?= htmlspecialchars($nom, ENT_QUOTES, 'UTF-8') ?></strong>
                — Rôle : <strong><?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?></strong>
            </p>
        </div>
    </div>
</div>
