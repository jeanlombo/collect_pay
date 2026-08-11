<?php
require_once "../../auth/check_auth.php";
checkAuth();

$page_title = "Rapport mensuel";

$annee = (int)($_GET['annee'] ?? date('Y'));
$mois = (int)($_GET['mois'] ?? date('n'));
$province_id = (int)($_GET['province_id'] ?? 0);
$centre_id = (int)($_GET['centre_id'] ?? 0);

if ($mois < 1 || $mois > 12) $mois = (int)date('n');
if ($annee < 2000 || $annee > 2100) $annee = (int)date('Y');

$debut = sprintf('%04d-%02d-01', $annee, $mois);
$fin = date('Y-m-t', strtotime($debut));

$provinces = $pdo->query("
    SELECT id, nom
    FROM provinces
    WHERE actif = 1
    ORDER BY nom
")->fetchAll(PDO::FETCH_ASSOC);

$sqlCentres = "SELECT id, nom_centre FROM centres WHERE actif = 1";
$paramsCentres = [];
if ($province_id > 0) {
    $sqlCentres .= " AND province_id = ?";
    $paramsCentres[] = $province_id;
}
$sqlCentres .= " ORDER BY nom_centre";
$stmtCentres = $pdo->prepare($sqlCentres);
$stmtCentres->execute($paramsCentres);
$centres = $stmtCentres->fetchAll(PDO::FETCH_ASSOC);

$where = ["DATE(np.date_emission) BETWEEN ? AND ?"];
$params = [$debut, $fin];

if ($province_id > 0) {
    $where[] = "ce.province_id = ?";
    $params[] = $province_id;
}
if ($centre_id > 0) {
    $where[] = "nt.centre_id = ?";
    $params[] = $centre_id;
}

$whereSql = implode(" AND ", $where);

$paymentSub = "
    SELECT
        note_perception_id,
        SUM(CASE WHEN statut <> 'annule' THEN montant_converti_cdf ELSE 0 END) AS total_paye,
        COUNT(CASE WHEN statut <> 'annule' THEN 1 END) AS nombre_paiements
    FROM paiements
    WHERE note_perception_id IS NOT NULL
    GROUP BY note_perception_id
";

$sql = "
    SELECT
        np.id,
        np.numero_np,
        np.type_np,
        np.date_emission,
        np.date_echeance,
        np.statut,
        COALESCE(NULLIF(np.montant_initial,0), np.montant_total,0) AS montant_du,
        COALESCE(pp.total_paye,0) AS total_paye,
        COALESCE(pp.nombre_paiements,0) AS nombre_paiements,
        c.nif,
        COALESCE(NULLIF(c.raison_sociale,''), TRIM(CONCAT_WS(' ',c.nom,c.postnom,c.prenom)), '-') AS assujetti,
        ce.nom_centre,
        pr.nom AS province
    FROM notes_perception np
    JOIN notes_debit nd ON np.note_debit_id = nd.id
    JOIN notes_taxation nt ON nd.note_taxation_id = nt.id
    JOIN contribuables c ON nt.contribuable_id = c.id
    JOIN centres ce ON nt.centre_id = ce.id
    JOIN provinces pr ON ce.province_id = pr.id
    LEFT JOIN ({$paymentSub}) pp ON pp.note_perception_id = np.id
    WHERE {$whereSql}
    ORDER BY np.date_emission DESC, np.id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalDu = 0.0;
$totalPaye = 0.0;
$nbPayees = 0;
$nbPartielles = 0;
$nbNonPayees = 0;
$nbPaiements = 0;

foreach ($notes as $n) {
    $du = (float)$n['montant_du'];
    $paye = (float)$n['total_paye'];
    $totalDu += $du;
    $totalPaye += $paye;
    $nbPaiements += (int)$n['nombre_paiements'];

    if ($n['statut'] === 'payee') $nbPayees++;
    elseif ($n['statut'] === 'partiellement_payee') $nbPartielles++;
    else $nbNonPayees++;
}

$totalSolde = max(0, $totalDu - $totalPaye);
$tauxRecouvrement = $totalDu > 0 ? min(100, ($totalPaye / $totalDu) * 100) : 0;

$moisLabels = [
    1=>'Janvier',2=>'Février',3=>'Mars',4=>'Avril',5=>'Mai',6=>'Juin',
    7=>'Juillet',8=>'Août',9=>'Septembre',10=>'Octobre',11=>'Novembre',12=>'Décembre'
];

function badgeRapportMensuel(string $statut): string {
    $map = [
        'payee' => ['PAYÉE','badge-green'],
        'partiellement_payee' => ['PARTIELLE','badge-orange'],
        'defaillante' => ['DÉFAILLANTE','badge-red'],
        'annulee' => ['ANNULÉE','badge-gray'],
        'non_payee' => ['NON PAYÉE','badge-gray'],
        'en_attente' => ['EN ATTENTE','badge-blue'],
    ];
    [$label,$class] = $map[$statut] ?? [strtoupper($statut ?: '-'),'badge-gray'];
    return '<span class="badge '.$class.'">'.htmlspecialchars($label).'</span>';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($page_title) ?> | cOllect_Pay</title>
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
        <span class="report-kicker">Rapport périodique</span>
        <h2>Rapport mensuel — <?= htmlspecialchars($moisLabels[$mois].' '.$annee) ?></h2>
        <p>Émissions, paiements, soldes et niveau de recouvrement du mois.</p>
    </div>
</div>

<div class="panel">
    <form method="GET" class="report-filter-grid monthly-filter">
        <select name="mois">
            <?php foreach ($moisLabels as $num => $label): ?>
                <option value="<?= $num ?>" <?= $mois === $num ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
        </select>

        <input type="number" name="annee" min="2000" max="2100" value="<?= $annee ?>">

        <select name="province_id">
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

        <button type="submit">Afficher</button>
    </form>
</div>

<div class="report-cards">
    <div class="report-card"><small>Notes émises</small><strong><?= count($notes) ?></strong></div>
    <div class="report-card"><small>Total dû</small><strong><?= number_format($totalDu,2,',',' ') ?> CDF</strong></div>
    <div class="report-card"><small>Total payé</small><strong><?= number_format($totalPaye,2,',',' ') ?> CDF</strong></div>
    <div class="report-card"><small>Solde</small><strong><?= number_format($totalSolde,2,',',' ') ?> CDF</strong></div>
</div>

<div class="report-cards secondary">
    <div class="report-card"><small>Notes payées</small><strong><?= $nbPayees ?></strong></div>
    <div class="report-card"><small>Notes partielles</small><strong><?= $nbPartielles ?></strong></div>
    <div class="report-card"><small>Non payées / autres</small><strong><?= $nbNonPayees ?></strong></div>
    <div class="report-card"><small>Taux de recouvrement</small><strong><?= number_format($tauxRecouvrement,1,',',' ') ?> %</strong></div>
</div>

<div class="panel">
    <div class="report-panel-heading">
        <h3>Détail des Notes de Perception</h3>
        <span><?= $nbPaiements ?> paiement(s) enregistré(s)</span>
    </div>

    <table class="table-premium cp-report-table">
        <thead>
        <tr>
            <th>Date</th>
            <th>NP / NPF</th>
            <th>Assujetti</th>
            <th>NIF</th>
            <th>Province / Centre</th>
            <th>Montant dû</th>
            <th>Payé</th>
            <th>Solde</th>
            <th>Statut</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($notes as $n): ?>
            <?php
                $du = (float)$n['montant_du'];
                $paye = (float)$n['total_paye'];
                $solde = max(0, $du - $paye);
            ?>
            <tr>
                <td><?= htmlspecialchars(date('d/m/Y',strtotime($n['date_emission']))) ?></td>
                <td>
                    <strong><?= htmlspecialchars($n['numero_np']) ?></strong>
                    <br><small><?= htmlspecialchars(strtoupper($n['type_np'] ?? 'NP')) ?></small>
                </td>
                <td><?= htmlspecialchars($n['assujetti']) ?></td>
                <td><?= htmlspecialchars($n['nif'] ?? '-') ?></td>
                <td><?= htmlspecialchars($n['province'].' / '.$n['nom_centre']) ?></td>
                <td><?= number_format($du,2,',',' ') ?> CDF</td>
                <td><strong><?= number_format($paye,2,',',' ') ?> CDF</strong></td>
                <td><?= number_format($solde,2,',',' ') ?> CDF</td>
                <td><?= badgeRapportMensuel($n['statut'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>

        <?php if (empty($notes)): ?>
            <tr><td colspan="9" class="report-empty">Aucune Note de Perception émise pendant cette période.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

</main>
</div>
</body>
</html>
