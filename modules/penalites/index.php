<?php
require_once "../../auth/check_auth.php";
checkAuth();
requirePermission('penalites', 'view');
$page_title = "Pénalités";
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><title><?= htmlspecialchars($page_title) ?> | cOllect_Pay</title><link rel="stylesheet" href="../../assets/css/admin.css"><style>.actions{display:flex;gap:12px;flex-wrap:wrap}.actions a{background:#0f3460;color:white;text-decoration:none;padding:12px 16px;border-radius:12px;font-weight:900}</style><link rel="stylesheet" href="../../assets/css/penalites.css">
</head>
<body class="cp-penalites-page"><div class="admin-layout"><?php require_once "../../includes/sidebar.php"; ?><main class="main-content"><?php require_once "../../includes/topbar.php"; ?><div class="panel cp-penalites-panel"><h2>Pénalités</h2><p>Consultation et gestion des pénalités de recouvrement.</p><div class="actions"><a href="parametres.php">Barème pénalités</a><a href="historique.php">Historique pénalités</a><a href="../recouvrement/amr_list.php">Liste AMR</a></div></div></main></div></body></html>