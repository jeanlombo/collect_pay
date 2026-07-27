<?php
require_once "../../config/database.php";
require_once "../../config/security.php";

checkAuth();

requireRole([
    'SUPER_ADMIN',
    'ADMIN',
    'CONTROLEUR',
    'DIRECTEUR_RECOUVREMENT'
]);

$page_title = "Rapports corrections";

function hReport($v)
{
    return htmlspecialchars((string)($v ?? '-'), ENT_QUOTES, 'UTF-8');
}

function countReport(PDO $pdo, $sql)
{
    try {
        $row = $pdo->query($sql)->fetch();
        return (int)($row['total'] ?? 0);
    } catch (Exception $e) {
        return 0;
    }
}

$totalCorrections = countReport($pdo, "
    SELECT COUNT(*) AS total
    FROM corrections_documents
");

$correctionsJour = countReport($pdo, "
    SELECT COUNT(*) AS total
    FROM corrections_documents
    WHERE DATE(date_modification) = CURDATE()
");

$correctionsMois = countReport($pdo, "
    SELECT COUNT(*) AS total
    FROM corrections_documents
    WHERE MONTH(date_modification) = MONTH(CURDATE())
    AND YEAR(date_modification) = YEAR(CURDATE())
");

$correctionsAnnee = countReport($pdo, "
    SELECT COUNT(*) AS total
    FROM corrections_documents
    WHERE YEAR(date_modification) = YEAR(CURDATE())
");

$stmt = $pdo->prepare("
    SELECT type_document, COUNT(*) AS total
    FROM corrections_documents
    GROUP BY type_document
    ORDER BY total DESC
");
$stmt->execute();
$parType = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT reference_table, COUNT(*) AS total
    FROM corrections_documents
    GROUP BY reference_table
    ORDER BY total DESC
");
$stmt->execute();
$parTable = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT u.nom AS utilisateur, COUNT(*) AS total
    FROM corrections_documents c
    LEFT JOIN users u ON c.user_id = u.id
    GROUP BY c.user_id, u.nom
    ORDER BY total DESC
    LIMIT 10
");
$stmt->execute();
$parUtilisateur = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT *
    FROM corrections_documents
    ORDER BY date_modification DESC
    LIMIT 10
");
$stmt->execute();
$recentes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?= hReport($page_title) ?> | cOllect_Pay</title>
<link rel="stylesheet" href="/collect_pay/assets/css/admin.css">
<style>
.hero-report{background:linear-gradient(135deg,#06152b,#0f3460,#1e3a8a);color:white;padding:26px;border-radius:24px;margin-bottom:22px}
.kpi-grid-report{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:22px}
.kpi-report{background:white;border:1px solid #e5e7eb;border-radius:22px;padding:20px;box-shadow:0 10px 28px rgba(15,23,42,.08)}
.kpi-report span{display:block;color:#64748b;font-weight:900;margin-bottom:8px}
.kpi-report h2{margin:0;color:#0f3460;font-size:30px;font-weight:950}
.grid-2-report{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:22px}
.badge-type{display:inline-block;padding:7px 12px;border-radius:999px;background:#dbeafe;color:#1e40af;font-weight:900;font-size:12px}
@media(max-width:1000px){.kpi-grid-report{grid-template-columns:repeat(2,1fr)}.grid-2-report{grid-template-columns:1fr}}
@media(max-width:650px){.kpi-grid-report{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="admin-layout">
<?php require_once "../../includes/sidebar.php"; ?>

<main class="main-content">
<?php require_once "../../includes/topbar.php"; ?>

<div class="hero-report">
    <h2>Rapports des corrections administratives</h2>
    <p>Statistiques et suivi des rectifications opérées sur les documents fiscaux.</p>
</div>

<div class="kpi-grid-report">
    <div class="kpi-report"><span>Total corrections</span><h2><?= (int)$totalCorrections ?></h2></div>
    <div class="kpi-report"><span>Aujourd'hui</span><h2><?= (int)$correctionsJour ?></h2></div>
    <div class="kpi-report"><span>Ce mois</span><h2><?= (int)$correctionsMois ?></h2></div>
    <div class="kpi-report"><span>Cette année</span><h2><?= (int)$correctionsAnnee ?></h2></div>
</div>

<div class="grid-2-report">
    <div class="panel">
        <h3>Corrections par type de document</h3>
        <table class="table-premium">
            <tr><th>Type document</th><th>Nombre</th></tr>
            <?php foreach ($parType as $r): ?>
                <tr><td><span class="badge-type"><?= hReport($r['type_document']) ?></span></td><td><strong><?= (int)$r['total'] ?></strong></td></tr>
            <?php endforeach; ?>
            <?php if (empty($parType)): ?><tr><td colspan="2">Aucune donnée disponible.</td></tr><?php endif; ?>
        </table>
    </div>

    <div class="panel">
        <h3>Corrections par table concernée</h3>
        <table class="table-premium">
            <tr><th>Table</th><th>Nombre</th></tr>
            <?php foreach ($parTable as $r): ?>
                <tr><td><?= hReport($r['reference_table']) ?></td><td><strong><?= (int)$r['total'] ?></strong></td></tr>
            <?php endforeach; ?>
            <?php if (empty($parTable)): ?><tr><td colspan="2">Aucune donnée disponible.</td></tr><?php endif; ?>
        </table>
    </div>
</div>

<div class="grid-2-report">
    <div class="panel">
        <h3>Top utilisateurs correcteurs</h3>
        <table class="table-premium">
            <tr><th>Utilisateur</th><th>Nombre</th></tr>
            <?php foreach ($parUtilisateur as $r): ?>
                <tr><td><?= hReport($r['utilisateur'] ?? 'Système') ?></td><td><strong><?= (int)$r['total'] ?></strong></td></tr>
            <?php endforeach; ?>
            <?php if (empty($parUtilisateur)): ?><tr><td colspan="2">Aucune donnée disponible.</td></tr><?php endif; ?>
        </table>
    </div>

    <div class="panel">
        <h3>Dernières corrections</h3>
        <table class="table-premium">
            <tr><th>Date</th><th>Document</th><th>Raison</th></tr>
            <?php foreach ($recentes as $r): ?>
                <tr>
                    <td><?= hReport(date('d/m/Y H:i:s', strtotime($r['date_modification']))) ?></td>
                    <td><strong><?= hReport($r['numero_document']) ?></strong><br><small><?= hReport($r['type_document']) ?></small></td>
                    <td><?= nl2br(hReport($r['raison_modification'])) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($recentes)): ?><tr><td colspan="3">Aucune correction récente.</td></tr><?php endif; ?>
        </table>
    </div>
</div>

</main>
</div>
</body>
</html>
