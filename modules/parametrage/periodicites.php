<?php
require_once "../../config/security.php";
checkAuth();

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
    <link rel="stylesheet" href="/collect_pay/assets/css/admin.css">
</head>
<body>
<div class="admin-layout">
    <?php require_once "../../includes/sidebar.php"; ?>
    <main class="main-content">
        <?php require_once "../../includes/topbar.php"; ?>

        <div class="panel">
            <h3>Périodicités fiscales</h3>

            <table class="table-premium">
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