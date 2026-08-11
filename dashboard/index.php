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
/* =========================================================
   DASHBOARD EXECUTIF — MISE EN PAGE UNIQUEMENT
   Aucun calcul, aucune requête SQL modifiée.
========================================================= */

.main-content{
    background:linear-gradient(180deg,#f7fafc 0%,#eef3f7 100%);
    min-height:100vh;
}

/* Largeur cohérente de tout le contenu */
.exec-hero,
.exec-kpis,
.exec-panels,
.exec-panels-3{
    width:min(1480px,calc(100% - 28px));
    margin-left:auto;
    margin-right:auto;
    box-sizing:border-box;
}

/* HERO */
.exec-hero{
    background:linear-gradient(135deg,#07192d 0%,#0f3b67 45%,#1768b0 100%);
    color:#fff;
    padding:24px 28px;
    border-radius:22px;
    margin-top:18px;
    margin-bottom:16px;
    box-shadow:0 14px 34px rgba(15,52,96,.18);
}
.exec-hero h2{
    margin:0;
    font-size:27px;
    font-weight:950;
    letter-spacing:-.3px;
}
.exec-hero p{
    margin:7px 0 0;
    color:#dbeafe;
    font-weight:700;
    font-size:13px;
}

/* KPI */
.exec-kpis{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:13px;
    margin-bottom:16px;
}
.exec-card{
    min-width:0;
    min-height:112px;
    background:#fff;
    border:1px solid #e1e8ef;
    border-radius:17px;
    padding:16px 17px;
    box-shadow:0 7px 20px rgba(15,23,42,.055);
}
.exec-card span{
    color:#66798d;
    font-weight:900;
    display:block;
    margin-bottom:7px;
    font-size:11.5px;
    line-height:1.3;
}
.exec-card h2{
    margin:0 0 3px;
    color:#102b43;
    font-size:20px;
    line-height:1.18;
    font-weight:950;
    overflow-wrap:anywhere;
}
.exec-card small{
    color:#8191a0;
    font-weight:750;
    font-size:10.5px;
}
.b1{border-left:5px solid #2563eb}
.b2{border-left:5px solid #16a34a}
.b3{border-left:5px solid #f59e0b}
.b4{border-left:5px solid #dc2626}
.b5{border-left:5px solid #7c3aed}
.b6{border-left:5px solid #0f172a}

/* PANELS
   align-items:start empêche qu'un panneau s'étire à la hauteur de son voisin. */
.exec-panels{
    display:grid;
    grid-template-columns:minmax(0,1.35fr) minmax(340px,.65fr);
    gap:14px;
    margin-bottom:14px;
    align-items:start;
}
.exec-panels-3{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:14px;
    margin-bottom:14px;
    align-items:start;
}

/* On neutralise les marges/hauteurs héritées de .panel sans toucher admin.css */
.exec-panels > .panel,
.exec-panels-3 > .panel{
    width:100%;
    min-width:0;
    height:auto !important;
    min-height:0 !important;
    margin:0 !important;
    padding:18px !important;
    box-sizing:border-box;
    border:1px solid #e1e8ef;
    border-radius:18px;
    background:#fff;
    box-shadow:0 7px 22px rgba(15,23,42,.05);
}
.exec-panels > .panel h3,
.exec-panels-3 > .panel h3{
    margin:0 0 13px;
    color:#17334b;
    font-size:16px;
    font-weight:950;
}

/* CONTENEURS DE GRAPHIQUES
   C'est la correction principale des grands espaces vides. */
.chart-box{
    position:relative;
    width:100%;
    height:285px;
    min-height:285px;
}
.chart-box.chart-small{
    height:245px;
    min-height:245px;
}
.chart-box canvas{
    display:block !important;
    width:100% !important;
    height:100% !important;
    max-height:100% !important;
}

/* Si aucune donnée, on ne dessine pas un graphique vide énorme */
.chart-empty{
    height:100%;
    min-height:150px;
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    text-align:center;
    border:1px dashed #cbd7e2;
    border-radius:14px;
    background:#f8fbfd;
    color:#708397;
    padding:20px;
}
.chart-empty strong{
    color:#294a65;
    margin-bottom:5px;
}
.chart-empty span{
    font-size:12px;
}

/* ALERTES */
.alert-exec{
    background:#fff7ed;
    border:1px solid #fed7aa;
    color:#9a3412;
    padding:11px 12px;
    border-radius:12px;
    font-weight:850;
    font-size:12px;
    line-height:1.4;
    margin-bottom:8px;
}
.alert-green{
    background:#dcfce7;
    border-color:#bbf7d0;
    color:#166534;
}

.badge{
    padding:5px 9px;
    border-radius:999px;
    font-weight:900;
    font-size:10px;
}
.badge-red{background:#fee2e2;color:#991b1b}
.badge-blue{background:#dbeafe;color:#1e40af}
.amount{
    font-weight:950;
    color:#0f4c7a;
    white-space:nowrap;
}

/* CARTE SYNTHETIQUE */
.map-tshopo{
    margin-top:12px;
    background:linear-gradient(135deg,#f8fafc,#edf7fc);
    border:1px solid #dce9f2;
    border-radius:15px;
    padding:14px;
}
.map-tshopo h3{
    margin:0 0 10px !important;
    font-size:14px !important;
}
.map-grid{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:8px;
}
.map-item{
    min-width:0;
    background:#fff;
    border-radius:12px;
    padding:11px;
    border:1px solid #e3eaf0;
    font-weight:850;
    font-size:11.5px;
    overflow-wrap:anywhere;
}
.map-item small{
    display:block;
    color:#64748b;
    margin-top:5px;
    font-size:10px;
}

/* TABLEAUX */
.exec-panels .table-premium,
.exec-panels-3 .table-premium{
    width:100%;
    margin:0;
    border-collapse:separate;
    border-spacing:0;
    border:1px solid #e1e8ef;
    border-radius:13px;
    overflow:hidden;
}
.exec-panels .table-premium th,
.exec-panels-3 .table-premium th{
    background:#edf4f8;
    color:#35536b;
    padding:10px 9px;
    font-size:10px;
    text-transform:uppercase;
    letter-spacing:.02em;
    white-space:nowrap;
}
.exec-panels .table-premium td,
.exec-panels-3 .table-premium td{
    padding:10px 9px;
    border-top:1px solid #edf1f4;
    vertical-align:middle;
    font-size:11.5px;
}
.exec-panels .table-premium tr:hover td,
.exec-panels-3 .table-premium tr:hover td{
    background:#fafcfd;
}
.table-scroll{
    width:100%;
    overflow-x:auto;
}

/* Deux tableaux du bas : proportions plus naturelles */
.exec-panels.exec-bottom{
    grid-template-columns:minmax(0,1fr) minmax(0,1fr);
}

/* RESPONSIVE */
@media(max-width:1250px){
    .exec-kpis{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }
    .exec-panels{
        grid-template-columns:minmax(0,1fr);
    }
    .exec-panels-3{
        grid-template-columns:minmax(0,1fr);
    }
    .map-grid{
        grid-template-columns:repeat(3,minmax(0,1fr));
    }
}
@media(max-width:760px){
    .exec-hero,
    .exec-kpis,
    .exec-panels,
    .exec-panels-3{
        width:calc(100% - 16px);
    }
    .exec-hero{
        padding:19px;
        border-radius:17px;
    }
    .exec-hero h2{
        font-size:22px;
    }
    .exec-kpis{
        grid-template-columns:1fr;
        gap:9px;
    }
    .exec-card{
        min-height:96px;
    }
    .chart-box,
    .chart-box.chart-small{
        height:230px;
        min-height:230px;
    }
    .map-grid{
        grid-template-columns:1fr;
    }
    .exec-panels > .panel,
    .exec-panels-3 > .panel{
        padding:13px !important;
        border-radius:15px;
    }
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
        <?php if(!empty($dataMois)): ?>
            <div class="chart-box">
                <canvas id="chartMois"></canvas>
            </div>
        <?php else: ?>
            <div class="chart-box">
                <div class="chart-empty">
                    <strong>Aucune recette mensuelle disponible</strong>
                    <span>Le graphique apparaîtra automatiquement dès qu’il y aura des paiements.</span>
                </div>
            </div>
        <?php endif; ?>
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
        <?php if(!empty($centresData)): ?>
            <div class="chart-box chart-small">
                <canvas id="chartCentres"></canvas>
            </div>
        <?php else: ?>
            <div class="chart-box chart-small">
                <div class="chart-empty">
                    <strong>Aucune recette par centre</strong>
                    <span>Aucune donnée de centre n’est disponible pour le moment.</span>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="panel">
        <h3>Recettes par direction</h3>
        <?php if(!empty($directionsData)): ?>
            <div class="chart-box chart-small">
                <canvas id="chartDirections"></canvas>
            </div>
        <?php else: ?>
            <div class="chart-box chart-small">
                <div class="chart-empty">
                    <strong>Aucune recette par direction</strong>
                    <span>Aucune donnée de direction n’est disponible pour le moment.</span>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="exec-panels exec-bottom">
    <div class="panel">
        <h3>Top 10 contribuables</h3>
        <div class="table-scroll">
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
    </div>

    <div class="panel">
        <h3>NP / NPF échues</h3>
        <div class="table-scroll">
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

const formatCDF = (value) => {
    return new Intl.NumberFormat('fr-FR', {
        maximumFractionDigits: 0
    }).format(value) + ' CDF';
};

const chartMoisEl = document.getElementById('chartMois');
if (chartMoisEl && moisData.length > 0) {
    new Chart(chartMoisEl, {
        type: 'line',
        data: {
            labels: moisLabels,
            datasets: [{
                label: 'Recettes CDF',
                data: moisData,
                tension: 0.32,
                fill: true,
                pointRadius: 3,
                pointHoverRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                intersect: false,
                mode: 'index'
            },
            plugins: {
                legend: {
                    display: true,
                    labels: {
                        boxWidth: 14,
                        usePointStyle: true
                    }
                },
                tooltip: {
                    callbacks: {
                        label: (ctx) => ' ' + formatCDF(ctx.parsed.y || 0)
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false }
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: (value) => new Intl.NumberFormat('fr-FR', {
                            notation: 'compact',
                            maximumFractionDigits: 1
                        }).format(value)
                    }
                }
            }
        }
    });
}

const chartCentresEl = document.getElementById('chartCentres');
if (chartCentresEl && centresData.length > 0) {
    new Chart(chartCentresEl, {
        type: 'bar',
        data: {
            labels: centresLabels,
            datasets: [{
                label: 'Recettes par centre',
                data: centresData,
                borderRadius: 7,
                maxBarThickness: 42
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: (ctx) => ' ' + formatCDF(ctx.parsed.y || 0)
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: {
                        maxRotation: 35,
                        minRotation: 0
                    }
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: (value) => new Intl.NumberFormat('fr-FR', {
                            notation: 'compact',
                            maximumFractionDigits: 1
                        }).format(value)
                    }
                }
            }
        }
    });
}

const chartDirectionsEl = document.getElementById('chartDirections');
if (chartDirectionsEl && directionsData.length > 0) {
    new Chart(chartDirectionsEl, {
        type: 'doughnut',
        data: {
            labels: directionsLabels,
            datasets: [{
                label: 'Recettes par direction',
                data: directionsData,
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '60%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 12,
                        usePointStyle: true,
                        padding: 12
                    }
                },
                tooltip: {
                    callbacks: {
                        label: (ctx) => ' ' + ctx.label + ' : ' + formatCDF(ctx.parsed || 0)
                    }
                }
            }
        }
    });
}
</script>

</body>
</html>
