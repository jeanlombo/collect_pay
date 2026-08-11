<?php
require_once "../../config/database.php";
require_once "../../config/security.php";
require_once "../../core/penalite_engine.php";

checkAuth();
requirePermission('penalites', 'apply');

requireRole([
    'SUPER_ADMIN',
    'RECOUVREMENT',
    'CHEF_RECOUVREMENT',
    'CAISSIER'
]);

$page_title = "Appliquer pénalité";

$type_doc = $_GET['type'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$type_doc || $id <= 0) {
    die("Type document et ID obligatoires.");
}

$doc = null;
$montant = 0;
$numero = '-';

if ($type_doc === "NP" || $type_doc === "FRACTION") {

    $stmt = $pdo->prepare("
        SELECT 
            id,
            numero_np,
            type_np,
            montant_initial,
            solde_restant,
            date_echeance
        FROM notes_perception
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $doc = $stmt->fetch();

    if ($doc) {
        $numero = $doc['numero_np'];
        $montant = (float)($doc['solde_restant'] ?? $doc['montant_initial'] ?? 0);
    }
}

if (!$doc) {
    die("Document introuvable.");
}

$jours = floor((strtotime(date('Y-m-d')) - strtotime($doc['date_echeance'])) / 86400);

if ($jours <= 0) {
    die("Aucune pénalité applicable. Le document n'est pas encore en retard.");
}

$penalite = calculerPenaliteProgressive(
    $montant,
    $jours,
    'recouvrement',
    $pdo
);

enregistrerPenalite(
    'recouvrement',
    $type_doc,
    $id,
    $montant,
    0,
    $penalite,
    $jours,
    $pdo
);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($page_title) ?> | cOllect_Pay</title>
<link rel="stylesheet" href="../../assets/css/admin.css">

<style>
.result-box{
    max-width:720px;
    margin:auto;
    background:white;
    border-radius:24px;
    padding:28px;
    box-shadow:0 15px 35px rgba(0,0,0,.08);
}
.success{
    background:#dcfce7;
    color:#166534;
    padding:16px;
    border-radius:16px;
    font-weight:900;
    margin-bottom:20px;
}
.amount{
    font-size:28px;
    font-weight:900;
    color:#0f3460;
}
.actions{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    margin-top:20px;
}
.btn-yellow{
    background:#fbbf24;
    color:#111827;
}
</style>
<link rel="stylesheet" href="../../assets/css/penalites.css">
</head>

<body class="cp-penalites-page">
<div class="admin-layout">

<?php require_once "../../includes/sidebar.php"; ?>

<main class="main-content">
<?php require_once "../../includes/topbar.php"; ?>

<div class="result-box">
    <div class="success">
        Pénalité appliquée avec succès.
    </div>

    <table class="table-premium cp-penalites-table">
        <tr>
            <th>Document</th>
            <td><?= htmlspecialchars($numero) ?></td>
        </tr>
        <tr>
            <th>Type</th>
            <td><?= htmlspecialchars($type_doc) ?></td>
        </tr>
        <tr>
            <th>Montant base</th>
            <td><?= number_format($montant, 2, ',', ' ') ?> CDF</td>
        </tr>
        <tr>
            <th>Jours de retard</th>
            <td><?= (int)$jours ?> jour(s)</td>
        </tr>
        <tr>
            <th>Pénalité calculée</th>
            <td><span class="amount"><?= number_format($penalite, 2, ',', ' ') ?> CDF</span></td>
        </tr>
    </table>

    <div class="actions">
        <a class="btn" href="historique.php">
            Voir historique
        </a>

        <a class="btn btn-yellow" href="../ordonnancement/np_list.php">
            Retour NP / NPF
        </a>
    </div>
</div>

</main>
</div>
</body>
</html>