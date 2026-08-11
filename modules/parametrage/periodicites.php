<?php
require_once "../../config/security.php";
checkAuth();
requireRole(['SUPER_ADMIN','ADMIN','PARAMETRAGE']);

$page_title = "Périodicités fiscales";

$periodicites = [
    "ponctuelle" => "Taxe payée une seule fois lors de l’acte",
    "mensuelle" => "Taxe renouvelée chaque mois",
    "trimestrielle" => "Taxe renouvelée chaque trimestre",
    "semestrielle" => "Taxe renouvelée chaque semestre",
    "annuelle" => "Taxe renouvelée chaque année",
    "non_renouvelable" => "Taxe payée une seule fois et non renouvelable"
];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= $page_title ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../../assets/css/admin.css">
<link rel="stylesheet" href="../../assets/css/parametrage.css">
</head>
<body class="cp-parametrage-page">
<div class="admin-layout">
    <?php require_once "../../includes/sidebar.php"; ?>
    <main class="main-content">
        <?php require_once "../../includes/topbar.php"; ?>

        <div class="panel cp-parametrage-panel">
            <h3>Périodicités fiscales</h3>

            <table class="table-premium cp-parametrage-table">
                <tr>
                    <th>Code</th>
                    <th>Signification</th>
                </tr>

                <?php foreach($periodicites as $code => $description): ?>
                    <tr>
                        <td><strong><?= $code ?></strong></td>
                        <td><?= $description ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </main>
</div>
</body>
</html>