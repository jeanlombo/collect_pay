<?php
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/security.php";
require_once __DIR__ . "/../core/functions.php";

checkAuth();

$page_title = "Dashboard Exécutif Provincial";

function moneyProv($n) {
    return number_format((float)$n, 0, ',', ' ') . ' CDF';
}

function percentProv($n) {
    return number_format((float)$n, 2, ',', ' ') . ' %';
}

function safeProv($v) {
    return htmlspecialchars((string)($v ?? '-'), ENT_QUOTES, 'UTF-8');
}

function fetchOneProv($pdo, $sql, $default = 0) {
    try {
        $row = $pdo->query($sql)->fetch();
        if (!$row) return $default;
        return array_values($row)[0] ?? $default;
    } catch (Exception $e) {
        return $default;
    }
}

$recettesJour = fetchOneProv($pdo, "
    SELECT IFNULL(SUM(montant_converti_cdf),0)
    FROM paiements
    WHERE DATE(created_at)=CURDATE()
");

$recettesMois = fetchOneProv($pdo, "
    SELECT IFNULL(SUM(montant_converti_cdf),0)
    FROM paiements
    WHERE MONTH(created_at)=MONTH(CURDATE())
    AND YEAR(created_at)=YEAR(CURDATE())
");

$recettesAnnee = fetchOneProv($pdo, "
    SELECT IFNULL(SUM(montant_converti_cdf),0)
    FROM paiements
    WHERE YEAR(created_at)=YEAR(CURDATE())
");

$totalConstatation = fetchOneProv($pdo, "
    SELECT IFNULL(SUM(total_estime),0)
    FROM notes_taxation
");

$totalLiquidation = fetchOneProv($pdo, "
    SELECT IFNULL(SUM(montant_total),0)
    FROM notes_debit
");

$totalOrdonnance = fetchOneProv($pdo, "
    SELECT IFNULL(SUM(montant_initial),0)
    FROM notes_perception
");

$totalRecouvre = fetchOneProv($pdo, "
    SELECT IFNULL(SUM(montant_converti_cdf),0)
    FROM paiements
");

$soldeRecouvrer = fetchOneProv($pdo, "
    SELECT IFNULL(SUM(solde_restant),0)
    FROM notes_perception
    WHERE statut <> 'payee'
");

$totalQuittances = fetchOneProv($pdo, "SELECT COUNT(*) FROM quittances");
$totalAMR = fetchOneProv($pdo, "SELECT COUNT(*) FROM amr");
$totalNP = fetchOneProv($pdo, "SELECT COUNT(*) FROM notes_perception");
$totalNT = fetchOneProv($pdo, "SELECT COUNT(*) FROM notes_taxation");

$npEchues = fetchOneProv($pdo, "
    SELECT COUNT(*)
    FROM notes_perception
    WHERE statut <> 'payee'
    AND date_echeance IS NOT NULL
    AND date_echeance < CURDATE()
");

$amrNonApures = fetchOneProv($pdo, "
    SELECT COUNT(*)
    FROM amr
    WHERE statut <> 'apuree'
");

$tauxRecouvrement = 0;
if ((float)$totalOrdonnance > 0) {
    $tauxRecouvrement = ((float)$totalRecouvre / (float)$totalOrdonnance) * 100;
}

/*
|--------------------------------------------------------------------------
| Recettes mensuelles
|--------------------------------------------------------------------------
*/
$labelsMois = [];
$dataMois = [];

try {
    $stmt = $pdo->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') AS mois,
            IFNULL(SUM(montant_converti_cdf),0) AS total
        FROM paiements
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY mois ASC
    ");
    foreach ($stmt->fetchAll() as $r) {
        $labelsMois[] = $r['mois'];
        $dataMois[] = (float)$r['total'];
    }
} catch (Exception $e) {}

/*
|--------------------------------------------------------------------------
| Recettes par centre
|--------------------------------------------------------------------------
*/
$centresLabels = [];
$centresData = [];
$centresRows = [];

try {
    $stmt = $pdo->query("
        SELECT 
            COALESCE(c.nom_centre, c.nom, CONCAT('Centre #', c.id)) AS centre,
            IFNULL(SUM(p.montant_converti_cdf),0) AS total
        FROM paiements p
        LEFT JOIN users u ON p.user_comptable_id = u.id
        LEFT JOIN centres c ON u.centre_id = c.id
        GROUP BY c.id, centre
        ORDER BY total DESC
        LIMIT 10
    ");
    $centresRows = $stmt->fetchAll();

    foreach ($centresRows as $r) {
        $centresLabels[] = $r['centre'];
        $centresData[] = (float)$r['total'];
    }
} catch (Exception $e) {
    $centresRows = [];
}

/*
|--------------------------------------------------------------------------
| Recettes par direction
|--------------------------------------------------------------------------
*/
$directionsLabels = [];
$directionsData = [];
$directionsRows = [];

try {
    $stmt = $pdo->query("
        SELECT 
            COALESCE(d.nom_direction, d.nom, CONCAT('Direction #', d.id)) AS direction,
            IFNULL(SUM(p.montant_converti_cdf),0) AS total
        FROM paiements p
        JOIN notes_perception np ON p.note_perception_id = np.id
        JOIN notes_debit nd ON np.note_debit_id = nd.id
        JOIN notes_taxation nt ON nd.note_taxation_id = nt.id
        LEFT JOIN notes_taxation_details det ON det.note_taxation_id = nt.id
        LEFT JOIN directions d ON det.direction_id = d.id
        GROUP BY d.id, direction
        ORDER BY total DESC
        LIMIT 10
    ");
    $directionsRows = $stmt->fetchAll();

    foreach ($directionsRows as $r) {
        $directionsLabels[] = $r['direction'];
        $directionsData[] = (float)$r['total'];
    }
} catch (Exception $e) {
    $directionsRows = [];
}

/*
|--------------------------------------------------------------------------
| Top contribuables
|--------------------------------------------------------------------------
*/
try {
    $stmt = $pdo->query("
        SELECT
            COALESCE(c.raison_sociale, TRIM(CONCAT(c.nom,' ',c.postnom,' ',c.prenom))) AS contribuable,
            c.nif,
            IFNULL(SUM(p.montant_converti_cdf),0) AS total
        FROM paiements p
        JOIN notes_perception np ON p.note_perception_id=np.id
        JOIN notes_debit nd ON np.note_debit_id=nd.id
        JOIN notes_taxation nt ON nd.note_taxation_id=nt.id
        JOIN contribuables c ON nt.contribuable_id=c.id
        GROUP BY c.id, contribuable, c.nif
        ORDER BY total DESC
        LIMIT 10
    ");
    $topContribuables = $stmt->fetchAll();
} catch (Exception $e) {
    $topContribuables = [];
}

/*
|--------------------------------------------------------------------------
| NP échues
|--------------------------------------------------------------------------
*/
try {
    $stmt = $pdo->query("
        SELECT
            np.numero_np,
            np.type_np,
            np.solde_restant,
            np.date_echeance,
            np.statut,
            COALESCE(c.raison_sociale, TRIM(CONCAT(c.nom,' ',c.postnom,' ',c.prenom))) AS contribuable
        FROM notes_perception np
        JOIN notes_debit nd ON np.note_debit_id=nd.id
        JOIN notes_taxation nt ON nd.note_taxation_id=nt.id
        JOIN contribuables c ON nt.contribuable_id=c.id
        WHERE np.statut <> 'payee'
        AND np.date_echeance IS NOT NULL
        AND np.date_echeance < CURDATE()
        ORDER BY np.date_echeance ASC
        LIMIT 10
    ");
    $npEchuesRows = $stmt->fetchAll();
} catch (Exception $e) {
    $npEchuesRows = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?= safeProv($page_title) ?> | cOllect_Pay</title>
<link rel="stylesheet" href="/collect_pay/assets/css/admin.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
.exec-hero{
    background:linear-gradient(135deg,#020617,#0f3460,#1d4ed8);
    color:#fff;
    padding:28px;
    border-radius:28px;
    margin-bottom:22px;
    box-shadow:0 20px 50px rgba(15,52,96,.25);
}
.exec-hero h2{margin:0;font-size:28px;font-weight:950}
.exec-hero p{margin:8px 0 0;color:#dbeafe;font-weight:700}
.exec-kpis{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:16px;
    margin-bottom:22px;
}
.exec-card{
    background:#fff;
    border:1px solid #e5e7eb;
    border-radius:22px;
    padding:20px;
    box-shadow:0 12px 28px rgba(15,23,42,.08);
}
.exec-card span{
    color:#64748b;
    font-weight:900;
    display:block;
    margin-bottom:8px;
    font-size:13px;
}
.exec-card h2{
    margin:0;
    color:#06152b;
    font-size:22px;
    font-weight:950;
}
.exec-card small{color:#64748b;font-weight:800}
.b1{border-left:6px solid #2563eb}
.b2{border-left:6px solid #16a34a}
.b3{border-left:6px solid #f59e0b}
.b4{border-left:6px solid #dc2626}
.b5{border-left:6px solid #7c3aed}
.b6{border-left:6px solid #0f172a}
.exec-panels{
    display:grid;
    grid-template-columns:1.2fr .8fr;
    gap:18px;
    margin-bottom:22px;
}
.exec-panels-3{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:18px;
    margin-bottom:22px;
}
.alert-exec{
    background:#fff7ed;
    border:1px solid #fed7aa;
    color:#9a3412;
    padding:14px;
    border-radius:16px;
    font-weight:900;
    margin-bottom:10px;
}
.alert-green{
    background:#dcfce7;
    border-color:#bbf7d0;
    color:#166534;
}
.badge{
    padding:7px 12px;
    border-radius:999px;
    font-weight:900;
    font-size:12px;
}
.badge-red{background:#fee2e2;color:#991b1b}
.badge-blue{background:#dbeafe;color:#1e40af}
.amount{font-weight:950;color:#0f3460}
.map-tshopo{
    background:linear-gradient(135deg,#f8fafc,#e0f2fe);
    border:1px solid #dbeafe;
    border-radius:22px;
    padding:20px;
}
.map-grid{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:10px;
}
.map-item{
    background:#fff;
    border-radius:16px;
    padding:14px;
    border:1px solid #e5e7eb;
    font-weight:900;
}
.map-item small{display:block;color:#64748b;margin-top:6px}
@media(max-width:1100px){
    .exec-kpis{grid-template-columns:repeat(2,1fr)}
    .exec-panels,.exec-panels-3{grid-template-columns:1fr}
}
@media(max-width:700px){
    .exec-kpis{grid-template-columns:1fr}
    .map-grid{grid-template-columns:1fr}
}
</style>
</head>

<body>
<div class="admin-layout">

<?php require_once __DIR__ . "/../includes/sidebar.php"; ?>

<main class="main-content">
<?php require_once __DIR__ . "/../includes/topbar.php"; ?>

<div class="exec-hero">
    <h2>Dashboard Exécutif Provincial</h2>
    <p>Province de la Tshopo — pilotage des recettes publiques, du recouvrement et des risques fiscaux.</p>
</div>

<div class="exec-kpis">
    <div class="exec-card b2">
        <span>Recettes encaissées aujourd’hui</span>
        <h2><?= moneyProv($recettesJour) ?></h2>
        <small>Paiements validés du jour</small>
    </div>

    <div class="exec-card b1">
        <span>Recettes du mois</span>
        <h2><?= moneyProv($recettesMois) ?></h2>
        <small>Mois en cours</small>
    </div>

    <div class="exec-card b5">
        <span>Recettes de l’année</span>
        <h2><?= moneyProv($recettesAnnee) ?></h2>
        <small>Exercice <?= date('Y') ?></small>
    </div>

    <div class="exec-card b6">
        <span>Taux de recouvrement</span>
        <h2><?= percentProv($tauxRecouvrement) ?></h2>
        <small>Recouvré / ordonnancé</small>
    </div>

    <div class="exec-card b1">
        <span>Constaté</span>
        <h2><?= moneyProv($totalConstatation) ?></h2>
        <small><?= (int)$totalNT ?> NT</small>
    </div>

    <div class="exec-card b3">
        <span>Ordonnancé</span>
        <h2><?= moneyProv($totalOrdonnance) ?></h2>
        <small><?= (int)$totalNP ?> NP / NPF</small>
    </div>

    <div class="exec-card b4">
        <span>Solde à recouvrer</span>
        <h2><?= moneyProv($soldeRecouvrer) ?></h2>
        <small>Créances non soldées</small>
    </div>

    <div class="exec-card b2">
        <span>Quittances émises</span>
        <h2><?= (int)$totalQuittances ?></h2>
        <small>Acquits libératoires</small>
    </div>
</div>

<div class="exec-panels">
    <div class="panel">
        <h3>Évolution mensuelle des recettes</h3>
        <canvas id="chartMois" height="120"></canvas>
    </div>

    <div class="panel">
        <h3>Alertes exécutives</h3>

        <?php if((int)$npEchues > 0): ?>
            <div class="alert-exec">🚨 <?= (int)$npEchues ?> NP / NPF échue(s) nécessitent un suivi.</div>
        <?php endif; ?>

        <?php if((int)$amrNonApures > 0): ?>
            <div class="alert-exec">⚠️ <?= (int)$amrNonApures ?> AMR non apuré(s).</div>
        <?php endif; ?>

        <?php if((float)$tauxRecouvrement < 50 && (float)$totalOrdonnance > 0): ?>
            <div class="alert-exec">📉 Taux de recouvrement faible : <?= percentProv($tauxRecouvrement) ?>.</div>
        <?php endif; ?>

        <?php if((int)$npEchues === 0 && (int)$amrNonApures === 0): ?>
            <div class="alert-exec alert-green">✅ Aucune alerte critique détectée.</div>
        <?php endif; ?>

        <div class="map-tshopo">
            <h3>Carte synthétique des centres</h3>
            <div class="map-grid">
                <?php if(!empty($centresRows)): ?>
                    <?php foreach(array_slice($centresRows,0,6) as $c): ?>
                        <div class="map-item">
                            <?= safeProv($c['centre']) ?>
                            <small><?= moneyProv($c['total']) ?></small>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="map-item">Aucun centre disponible<small>0 CDF</small></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="exec-panels-3">
    <div class="panel">
        <h3>Recettes par centre</h3>
        <canvas id="chartCentres" height="150"></canvas>
    </div>

    <div class="panel">
        <h3>Recettes par direction</h3>
        <canvas id="chartDirections" height="150"></canvas>
    </div>
</div>

<div class="exec-panels">
    <div class="panel">
        <h3>Top 10 contribuables</h3>
        <table class="table-premium">
            <tr>
                <th>Contribuable</th>
                <th>NIF</th>
                <th>Total payé</th>
            </tr>

            <?php foreach($topContribuables as $c): ?>
                <tr>
                    <td><strong><?= safeProv($c['contribuable']) ?></strong></td>
                    <td><?= safeProv($c['nif']) ?></td>
                    <td><span class="amount"><?= moneyProv($c['total']) ?></span></td>
                </tr>
            <?php endforeach; ?>

            <?php if(empty($topContribuables)): ?>
                <tr><td colspan="3">Aucun paiement enregistré.</td></tr>
            <?php endif; ?>
        </table>
    </div>

    <div class="panel">
        <h3>NP / NPF échues</h3>
        <table class="table-premium">
            <tr>
                <th>NP</th>
                <th>Contribuable</th>
                <th>Solde</th>
                <th>Action</th>
            </tr>

            <?php foreach($npEchuesRows as $n): ?>
                <tr>
                    <td><strong><?= safeProv($n['numero_np']) ?></strong><br><span class="badge badge-red"><?= strtoupper(safeProv($n['statut'])) ?></span></td>
                    <td><?= safeProv($n['contribuable']) ?></td>
                    <td><span class="amount"><?= moneyProv($n['solde_restant']) ?></span></td>
                    <td><a href="/collect_pay/modules/ordonnancement/np_view.php?numero=<?= urlencode($n['numero_np']) ?>">Voir</a></td>
                </tr>
            <?php endforeach; ?>

            <?php if(empty($npEchuesRows)): ?>
                <tr><td colspan="4">Aucune NP échue.</td></tr>
            <?php endif; ?>
        </table>
    </div>
</div>

</main>
</div>

<script>
const moisLabels = <?= json_encode($labelsMois, JSON_UNESCAPED_UNICODE) ?>;
const moisData = <?= json_encode($dataMois, JSON_UNESCAPED_UNICODE) ?>;

const centresLabels = <?= json_encode($centresLabels, JSON_UNESCAPED_UNICODE) ?>;
const centresData = <?= json_encode($centresData, JSON_UNESCAPED_UNICODE) ?>;

const directionsLabels = <?= json_encode($directionsLabels, JSON_UNESCAPED_UNICODE) ?>;
const directionsData = <?= json_encode($directionsData, JSON_UNESCAPED_UNICODE) ?>;

new Chart(document.getElementById('chartMois'), {
    type: 'line',
    data: {
        labels: moisLabels,
        datasets: [{
            label: 'Recettes CDF',
            data: moisData,
            tension: 0.35,
            fill: true
        }]
    },
    options: { responsive:true, scales:{ y:{ beginAtZero:true } } }
});

new Chart(document.getElementById('chartCentres'), {
    type: 'bar',
    data: {
        labels: centresLabels,
        datasets: [{
            label: 'Recettes par centre',
            data: centresData
        }]
    },
    options: { responsive:true, scales:{ y:{ beginAtZero:true } } }
});

new Chart(document.getElementById('chartDirections'), {
    type: 'doughnut',
    data: {
        labels: directionsLabels,
        datasets: [{
            label: 'Recettes par direction',
            data: directionsData
        }]
    },
    options: { responsive:true }
});
</script>

</body>
</html>
