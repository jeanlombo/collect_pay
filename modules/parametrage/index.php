<?php
require_once "../../config/database.php";
require_once "../../config/security.php";
require_once "../../core/functions.php";

checkAuth();
requireRole(['SUPER_ADMIN','ADMIN','PARAMETRAGE']);

$page_title = "Paramétrage du système";

function countTable($pdo, $sql)
{
    try {
        $row = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
        return (int)($row['total'] ?? 0);
    } catch (Exception $e) {
        return 0;
    }
}

$totalProvinces = countTable($pdo, "SELECT COUNT(*) total FROM provinces");
$totalCentres = countTable($pdo, "SELECT COUNT(*) total FROM centres");
$totalDirections = countTable($pdo, "SELECT COUNT(*) total FROM directions");
$totalServices = countTable($pdo, "SELECT COUNT(*) total FROM services_assiette");
$totalArticles = countTable($pdo, "SELECT COUNT(*) total FROM articles_budgetaires");
$totalTauxProvince = countTable($pdo, "SELECT COUNT(*) total FROM article_taux_province WHERE actif=1");
$totalTauxChange = countTable($pdo, "SELECT COUNT(*) total FROM taux_change_officiel WHERE actif=1");
$totalComptes = countTable($pdo, "SELECT COUNT(*) total FROM comptes_bancaires");
$totalModesPaiement = countTable($pdo, "SELECT COUNT(*) total FROM modes_paiement");

$ready = (
    $totalProvinces > 0 &&
    $totalCentres > 0 &&
    $totalDirections > 0 &&
    $totalServices > 0 &&
    $totalArticles > 0 &&
    $totalTauxChange > 0 &&
    $totalComptes > 0 &&
    $totalModesPaiement > 0
);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($page_title) ?> | cOllect_Pay</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../../assets/css/admin.css">

<style>
.param-grid{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:22px;
}
.param-card{
    background:white;
    border-radius:24px;
    padding:24px;
    box-shadow:0 15px 35px rgba(0,0,0,.08);
    border-left:6px solid #0f3460;
}
.param-card h3{
    margin:0 0 8px;
    color:#06152b;
    font-weight:900;
}
.param-card p{
    color:#6b7280;
    min-height:46px;
}
.param-card a{
    display:inline-block;
    margin-top:12px;
    background:#0f3460;
    color:white;
    text-decoration:none;
    padding:11px 16px;
    border-radius:14px;
    font-weight:900;
}
.status-ready{
    background:#ecfdf5;
    color:#047857;
    border:1px solid #bbf7d0;
    padding:15px;
    border-radius:16px;
    font-weight:900;
    margin-bottom:24px;
}
.status-warning{
    background:#fff7ed;
    color:#9a3412;
    border:1px solid #fed7aa;
    padding:15px;
    border-radius:16px;
    font-weight:900;
    margin-bottom:24px;
}
.mini-stat{
    font-size:28px;
    font-weight:900;
    color:#0f3460;
}
.note-small{
    color:#64748b;
    font-size:12px;
    font-weight:800;
    margin-top:8px;
}
@media(max-width:1000px){
    .param-grid{
        grid-template-columns:1fr;
    }
}
</style>
<link rel="stylesheet" href="../../assets/css/parametrage.css">
</head>

<body class="cp-parametrage-page">
<div class="admin-layout">

<?php require_once "../../includes/sidebar.php"; ?>

<main class="main-content">

<?php require_once "../../includes/topbar.php"; ?>

<?php if ($ready): ?>
    <div class="status-ready">
        ✅ Le paramétrage minimum est prêt. Le système peut commencer les opérations de taxation.
    </div>
<?php else: ?>
    <div class="status-warning">
        ⚠️ Paramétrage incomplet. Configure les provinces, centres, directions, services,
        nomenclature fiscale, taux de change, comptes bancaires et modes de paiement.
        <br>
        <span class="note-small">
            Les taux particuliers par province sont optionnels pour les impôts IRL/RL.
        </span>
    </div>
<?php endif; ?>

<div class="param-grid">

    <div class="param-card">
        <h3>Provinces</h3>
        <div class="mini-stat"><?= $totalProvinces ?></div>
        <p>Configurer les provinces et leurs codes officiels.</p>
        <a href="provinces.php">Configurer</a>
    </div>

    <div class="param-card">
        <h3>Centres fiscaux</h3>
        <div class="mini-stat"><?= $totalCentres ?></div>
        <p>Créer les centres rattachés aux provinces.</p>
        <a href="centres.php">Configurer</a>
    </div>

    <div class="param-card">
        <h3>Directions</h3>
        <div class="mini-stat"><?= $totalDirections ?></div>
        <p>Créer les directions responsables des recettes.</p>
        <a href="directions.php">Configurer</a>
    </div>

    <div class="param-card">
        <h3>Services d’assiette</h3>
        <div class="mini-stat"><?= $totalServices ?></div>
        <p>Associer chaque service à une direction et à un centre.</p>
        <a href="services.php">Configurer</a>
    </div>

    <div class="param-card">
        <h3>Nomenclature fiscale</h3>
        <div class="mini-stat"><?= $totalArticles ?></div>
        <p>Configurer les articles budgétaires, actes générateurs, taux, périodicités et modes de calcul.</p>
        <a href="nomenclature.php">Configurer</a>
    </div>

    <div class="param-card">
        <h3>Taux particuliers par Province</h3>
        <div class="mini-stat"><?= $totalTauxProvince ?></div>
        <p>Optionnel pour IRL/RL. Utilisé seulement lorsqu’un acte varie selon la province.</p>
        <a href="nomenclature.php">Configurer</a>
    </div>

    <div class="param-card">
        <h3>Périodicités</h3>
        <div class="mini-stat">6</div>
        <p>Voir les types de périodicités utilisés dans la nomenclature.</p>
        <a href="periodicites.php">Voir</a>
    </div>

    <div class="param-card">
        <h3>Taux de change</h3>
        <div class="mini-stat"><?= $totalTauxChange ?></div>
        <p>Verrouiller le taux officiel USD/CDF utilisé dans les calculs.</p>
        <a href="taux_change.php">Configurer</a>
    </div>

    <div class="param-card">
        <h3>Comptes bancaires</h3>
        <div class="mini-stat"><?= $totalComptes ?></div>
        <p>Configurer les banques, comptes CDF/USD et affectations.</p>
        <a href="comptes_bancaires.php">Configurer</a>
    </div>

    <div class="param-card">
        <h3>Modes de paiement</h3>
        <div class="mini-stat"><?= $totalModesPaiement ?></div>
        <p>Mobile Money, carte bancaire, virement bancaire, caisse.</p>
        <a href="modes_paiement.php">Configurer</a>
    </div>

</div>

</main>
</div>
</body>
</html>