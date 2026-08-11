<?php
require_once "../../auth/check_auth.php";
checkAuth();
requirePermission('rapports','analytique');

$page_title = "Rapport analytique";

$axe = $_GET['axe'] ?? 'assujetti';
$date_debut = trim((string)($_GET['date_debut'] ?? ''));
$date_fin = trim((string)($_GET['date_fin'] ?? ''));
$province_id = (int)($_GET['province_id'] ?? 0);
$centre_id = (int)($_GET['centre_id'] ?? 0);

$axes = [
    'assujetti' => "Par assujetti",
    'service' => "Par service d’assiette",
    'direction' => "Par direction / ressort",
    'article' => "Par article budgétaire",
    'acte_taxable' => "Par acte taxable",
    'nature_acte' => "Par nature d’acte",
    'fait_generateur' => "Par fait générateur",
    'categorie' => "Par catégorie / secteur",
];

if (!isset($axes[$axe])) {
    $axe = 'assujetti';
}

$titre = $axes[$axe];

$provinces = $pdo->query("
    SELECT id, nom
    FROM provinces
    WHERE actif = 1
    ORDER BY nom
")->fetchAll(PDO::FETCH_ASSOC);

$centresSql = "
    SELECT id, nom_centre, province_id
    FROM centres
    WHERE actif = 1
";
$paramsCentres = [];
if ($province_id > 0) {
    $centresSql .= " AND province_id = ?";
    $paramsCentres[] = $province_id;
}
$centresSql .= " ORDER BY nom_centre";
$stmtCentres = $pdo->prepare($centresSql);
$stmtCentres->execute($paramsCentres);
$centres = $stmtCentres->fetchAll(PDO::FETCH_ASSOC);

$where = [];
$params = [];

if ($date_debut !== '') {
    $where[] = "DATE(np.date_emission) >= ?";
    $params[] = $date_debut;
}
if ($date_fin !== '') {
    $where[] = "DATE(np.date_emission) <= ?";
    $params[] = $date_fin;
}
if ($province_id > 0) {
    $where[] = "ce.province_id = ?";
    $params[] = $province_id;
}
if ($centre_id > 0) {
    $where[] = "nt.centre_id = ?";
    $params[] = $centre_id;
}

$whereSql = $where ? " WHERE " . implode(" AND ", $where) : "";

$paySub = "
    SELECT
        note_perception_id,
        SUM(CASE WHEN statut <> 'annule' THEN montant_converti_cdf ELSE 0 END) AS total_paye
    FROM paiements
    WHERE note_perception_id IS NOT NULL
    GROUP BY note_perception_id
";

$results = [];

if (in_array($axe, ['assujetti','service','direction'], true)) {

    if ($axe === 'assujetti') {
        $labelExpr = "COALESCE(NULLIF(c.raison_sociale,''), TRIM(CONCAT_WS(' ',c.nom,c.postnom,c.prenom)), 'Sans nom')";
        $groupExpr = "c.id";
    } elseif ($axe === 'service') {
        $labelExpr = "COALESCE(s.nom_service,'Service non défini')";
        $groupExpr = "s.id";
    } else {
        $labelExpr = "COALESCE(d.nom_direction,'Direction non définie')";
        $groupExpr = "d.id";
    }

    $sql = "
        SELECT
            {$labelExpr} AS libelle,
            COUNT(DISTINCT np.id) AS nombre_documents,
            SUM(COALESCE(NULLIF(np.montant_initial,0), np.montant_total, 0)) AS montant_du,
            SUM(COALESCE(pp.total_paye,0)) AS montant_paye
        FROM notes_perception np
        JOIN notes_debit nd ON np.note_debit_id = nd.id
        JOIN notes_taxation nt ON nd.note_taxation_id = nt.id
        JOIN contribuables c ON nt.contribuable_id = c.id
        JOIN centres ce ON nt.centre_id = ce.id
        LEFT JOIN services_assiette s ON nt.service_id = s.id
        LEFT JOIN directions d ON s.direction_id = d.id
        LEFT JOIN ({$paySub}) pp ON pp.note_perception_id = np.id
        {$whereSql}
        GROUP BY {$groupExpr}, {$labelExpr}
        ORDER BY montant_du DESC, libelle ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

} else {

    $axisMap = [
        'article' => ["CONCAT(COALESCE(ab.code_article,''),' - ',COALESCE(ab.nature_acte,'Article'))", "ab.id"],
        'acte_taxable' => ["COALESCE(NULLIF(ntd.libelle_acte,''), ab.acte_generateur, 'Acte non défini')", "COALESCE(ntd.libelle_acte, CONCAT('ARTICLE-',ab.id))"],
        'nature_acte' => ["COALESCE(ab.nature_acte,'Nature non définie')", "ab.nature_acte"],
        'fait_generateur' => ["COALESCE(ab.fait_generateur,'Fait générateur non défini')", "ab.fait_generateur"],
        'categorie' => ["COALESCE(ab.secteur,'Catégorie non définie')", "ab.secteur"],
    ];

    [$labelExpr, $groupExpr] = $axisMap[$axe];

    /*
     * Pour les axes basés sur les lignes de taxation, le paiement d'une NP
     * est réparti proportionnellement au poids de la ligne dans le total NT.
     * Cela évite de compter tout le paiement plusieurs fois lorsqu'une NT
     * contient plusieurs articles.
     */
    $sql = "
        SELECT
            {$labelExpr} AS libelle,
            COUNT(DISTINCT np.id) AS nombre_documents,
            SUM(COALESCE(ntd.total_ligne,0)) AS montant_du,
            SUM(
                CASE
                    WHEN COALESCE(nt.total_estime,0) > 0
                    THEN COALESCE(pp.total_paye,0) * (COALESCE(ntd.total_ligne,0) / nt.total_estime)
                    ELSE 0
                END
            ) AS montant_paye
        FROM notes_taxation_details ntd
        JOIN notes_taxation nt ON ntd.note_taxation_id = nt.id
        JOIN articles_budgetaires ab ON ntd.article_id = ab.id
        JOIN centres ce ON nt.centre_id = ce.id
        JOIN notes_debit nd ON nd.note_taxation_id = nt.id
        JOIN notes_perception np ON np.note_debit_id = nd.id
        LEFT JOIN ({$paySub}) pp ON pp.note_perception_id = np.id
        {$whereSql}
        GROUP BY {$groupExpr}, {$labelExpr}
        ORDER BY montant_du DESC, libelle ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$totalDocuments = 0;
$totalDu = 0.0;
$totalPaye = 0.0;
foreach ($results as $r) {
    $totalDocuments += (int)($r['nombre_documents'] ?? 0);
    $totalDu += (float)($r['montant_du'] ?? 0);
    $totalPaye += (float)($r['montant_paye'] ?? 0);
}
$totalSolde = max(0, $totalDu - $totalPaye);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($titre) ?> | cOllect_Pay</title>
<link rel="stylesheet" href="../../assets/css/admin.css">
<link rel="stylesheet" href="../../assets/css/rapports.css">
</head>
<body class="cp-rapports-page">
<div class="admin-layout">
<?php require_once "../../includes/sidebar.php"; ?>

<main class="main-content">
<?php require_once "../../includes/topbar.php"; ?>

<div class="report-hero">
    <div>
        <span class="report-kicker">Rapports & statistiques</span>
        <h2><?= htmlspecialchars($titre) ?></h2>
        <p>Analyse consolidée des émissions, paiements et soldes selon l’axe sélectionné.</p>
    </div>
</div>

<div class="panel">
    <form method="GET" class="report-filter-grid">
        <select name="axe">
            <?php foreach ($axes as $code => $label): ?>
                <option value="<?= htmlspecialchars($code) ?>" <?= $axe === $code ? 'selected' : '' ?>>
                    <?= htmlspecialchars($label) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <input type="date" name="date_debut" value="<?= htmlspecialchars($date_debut) ?>">
        <input type="date" name="date_fin" value="<?= htmlspecialchars($date_fin) ?>">

        <select name="province_id" id="province_id">
            <option value="0">Toutes les provinces</option>
            <?php foreach ($provinces as $p): ?>
                <option value="<?= (int)$p['id'] ?>" <?= $province_id === (int)$p['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($p['nom']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="centre_id">
            <option value="0">Tous les centres</option>
            <?php foreach ($centres as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= $centre_id === (int)$c['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['nom_centre']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit">Analyser</button>
    </form>
</div>

<div class="report-cards">
    <div class="report-card"><small>Documents</small><strong><?= number_format($totalDocuments,0,',',' ') ?></strong></div>
    <div class="report-card"><small>Montant dû</small><strong><?= number_format($totalDu,2,',',' ') ?> CDF</strong></div>
    <div class="report-card"><small>Montant payé</small><strong><?= number_format($totalPaye,2,',',' ') ?> CDF</strong></div>
    <div class="report-card"><small>Solde</small><strong><?= number_format($totalSolde,2,',',' ') ?> CDF</strong></div>
</div>

<div class="panel">
    <div class="report-panel-heading">
        <h3><?= htmlspecialchars($titre) ?></h3>
        <span><?= count($results) ?> ligne(s)</span>
    </div>

    <table class="table-premium cp-report-table">
        <thead>
            <tr>
                <th>Libellé</th>
                <th>Documents</th>
                <th>Montant dû</th>
                <th>Montant payé</th>
                <th>Solde</th>
                <th>Taux recouvrement</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($results as $r): ?>
            <?php
                $du = (float)($r['montant_du'] ?? 0);
                $paye = (float)($r['montant_paye'] ?? 0);
                $solde = max(0, $du - $paye);
                $taux = $du > 0 ? min(100, ($paye / $du) * 100) : 0;
            ?>
            <tr>
                <td><strong><?= htmlspecialchars($r['libelle'] ?? '-') ?></strong></td>
                <td><?= number_format((int)($r['nombre_documents'] ?? 0),0,',',' ') ?></td>
                <td><?= number_format($du,2,',',' ') ?> CDF</td>
                <td><?= number_format($paye,2,',',' ') ?> CDF</td>
                <td><?= number_format($solde,2,',',' ') ?> CDF</td>
                <td><span class="rate-badge"><?= number_format($taux,1,',',' ') ?> %</span></td>
            </tr>
        <?php endforeach; ?>

        <?php if (empty($results)): ?>
            <tr><td colspan="6" class="report-empty">Aucune donnée pour les critères sélectionnés.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

</main>
</div>
</body>
</html>
