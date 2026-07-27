<?php
require_once "../../auth/check_auth.php";
checkAuth();
requirePermission('rapports','analytique');

$page_title = "Rapport analytique";
$axe = $_GET['axe'] ?? 'assujetti';

$labels = [
    'service' => "Par service d’assiette",
    'direction' => "Par direction / ressort",
    'article' => "Par article budgétaire",
    'acte_taxable' => "Par acte taxable",
    'nature_acte' => "Par nature d’acte",
    'fait_generateur' => "Par fait générateur",
    'categorie' => "Par catégorie",
    'assujetti' => "Par assujetti",
];

$titre = $labels[$axe] ?? "Rapport analytique";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($titre) ?> | cOllect_Pay</title>
<link rel="stylesheet" href="/collect_pay/assets/css/admin.css">
</head>
<body>
<div class="admin-layout">
<?php require_once "../../includes/sidebar.php"; ?>

<main class="main-content">
<?php require_once "../../includes/topbar.php"; ?>

<div class="panel">
    <h2><?= htmlspecialchars($titre) ?></h2>
    <p>Module de rapport analytique avec filtres Province, Centre, Direction/Ressort et période.</p>
</div>

<div class="panel">
    <form method="GET" style="display:grid;grid-template-columns:repeat(5,1fr);gap:12px;">
        <input type="hidden" name="axe" value="<?= htmlspecialchars($axe) ?>">

        <input type="date" name="date_debut">
        <input type="date" name="date_fin">

        <input type="text" name="province" placeholder="Province">
        <input type="text" name="centre" placeholder="Centre">
        <button type="submit">Filtrer</button>
    </form>
</div>

<div class="panel">
    <table class="table-premium">
        <tr>
            <th>Axe</th>
            <th>Libellé</th>
            <th>Nombre documents</th>
            <th>Montant estimé</th>
            <th>Montant payé</th>
            <th>Solde</th>
        </tr>
        <tr>
            <td><?= htmlspecialchars($axe) ?></td>
            <td>Rapport en cours de consolidation</td>
            <td>0</td>
            <td>0,00 CDF</td>
            <td>0,00 CDF</td>
            <td>0,00 CDF</td>
        </tr>
    </table>
</div>

</main>
</div>
</body>
</html>