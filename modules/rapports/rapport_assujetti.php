<?php
require_once "../../auth/check_auth.php";

checkAuth();
requirePermission('rapports','analytique');

$page_title = "Rapport par Assujetti";

$search = trim($_GET['search'] ?? '');
$date_debut = $_GET['date_debut'] ?? '';
$date_fin = $_GET['date_fin'] ?? '';
$statut_note = $_GET['statut_note'] ?? 'toutes';

$notes = [];

if ($search !== '') {

    $sql = "
        SELECT
            np.id AS np_id,
            np.numero_np,
            np.type_np,
            np.statut AS statut_np,
            np.date_emission,
            np.date_echeance,
            np.montant_initial,
            np.montant_paye,
            np.solde_restant,
            np.montant_total,
            np.penalite_recouvrement,
            np.compte_bancaire,

            nd.numero_nd,
            nt.numero_nt,

            c.raison_sociale,
            c.nom,
            c.postnom,
            c.prenom,
            c.nif,
            c.telephone,

            IFNULL(SUM(p.montant_paye),0) AS total_paye_reel,
            IFNULL(SUM(p.montant_converti_cdf),0) AS total_converti_cdf,
            COUNT(p.id) AS nombre_paiements,
            GROUP_CONCAT(
                CONCAT(
                    DATE_FORMAT(p.date_paiement,'%d/%m/%Y'),
                    ' - ',
                    FORMAT(p.montant_paye,2),
                    ' ',
                    p.devise,
                    ' - Réf: ',
                    IFNULL(p.reference_transaction,'-')
                )
                SEPARATOR ' | '
            ) AS details_paiements

        FROM notes_perception np
        LEFT JOIN paiements p ON p.note_perception_id = np.id
        LEFT JOIN notes_debit nd ON np.note_debit_id = nd.id
        LEFT JOIN notes_taxation nt ON nd.note_taxation_id = nt.id
        LEFT JOIN contribuables c ON nt.contribuable_id = c.id

        WHERE (
            c.raison_sociale LIKE ?
            OR c.nom LIKE ?
            OR c.postnom LIKE ?
            OR c.prenom LIKE ?
            OR c.nif LIKE ?
            OR c.telephone LIKE ?
        )
    ";

    $params = [];
    $like = "%".$search."%";

    for ($i = 0; $i < 6; $i++) {
        $params[] = $like;
    }

    if ($date_debut !== '') {
        $sql .= " AND DATE(np.date_emission) >= ?";
        $params[] = $date_debut;
    }

    if ($date_fin !== '') {
        $sql .= " AND DATE(np.date_emission) <= ?";
        $params[] = $date_fin;
    }

    if ($statut_note === 'payees') {
        $sql .= " AND np.statut = 'payee'";
    } elseif ($statut_note === 'non_payees') {
        $sql .= " AND np.statut IN ('en_attente','non_payee','defaillante')";
    } elseif ($statut_note === 'partielles') {
        $sql .= " AND np.statut = 'partiellement_payee'";
    }

    $sql .= "
        GROUP BY np.id
        ORDER BY np.date_emission DESC, np.id DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function nomAssujettiRapport($r) {
    if (!empty($r['raison_sociale'])) {
        return $r['raison_sociale'];
    }

    return trim(($r['nom'] ?? '') . ' ' . ($r['postnom'] ?? '') . ' ' . ($r['prenom'] ?? ''));
}

$totalDu = 0;
$totalPaye = 0;
$totalSolde = 0;
$totalConvertiCDF = 0;
$nbPayees = 0;
$nbNonPayees = 0;
$nbPartielles = 0;

foreach ($notes as $n) {
    $montantDu = (float)($n['montant_initial'] ?: $n['montant_total']);
    $paye = (float)($n['montant_paye'] ?: $n['total_paye_reel']);
    $solde = (float)($n['solde_restant'] ?? 0);

    $totalDu += $montantDu;
    $totalPaye += $paye;
    $totalSolde += $solde;
    $totalConvertiCDF += (float)($n['total_converti_cdf'] ?? 0);

    if (($n['statut_np'] ?? '') === 'payee') $nbPayees++;
    elseif (($n['statut_np'] ?? '') === 'partiellement_payee') $nbPartielles++;
    else $nbNonPayees++;
}

function badgeStatutNote($statut) {
    $statut = strtolower((string)$statut);

    if ($statut === 'payee') {
        return "<span class='badge badge-green'>PAYÉE</span>";
    }

    if ($statut === 'partiellement_payee') {
        return "<span class='badge badge-orange'>PARTIELLE</span>";
    }

    if ($statut === 'defaillante') {
        return "<span class='badge badge-red'>DÉFAILLANTE</span>";
    }

    return "<span class='badge badge-gray'>" . htmlspecialchars(strtoupper($statut ?: 'NON PAYÉE')) . "</span>";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($page_title) ?> | cOllect_Pay</title>
<link rel="stylesheet" href="/collect_pay/assets/css/admin.css">

<style>
.report-hero{
    background:linear-gradient(135deg,#06152b,#0f3460);
    color:white;
    padding:24px;
    border-radius:24px;
    margin-bottom:20px;
}
.report-hero h2{margin:0;font-weight:1000}
.report-hero p{margin:8px 0 0;color:#dbeafe;font-weight:800}

.filter-grid{
    display:grid;
    grid-template-columns:2fr 1fr 1fr 1fr auto;
    gap:12px;
}
.filter-grid input,
.filter-grid select{
    padding:12px;
    border:1px solid #d1d5db;
    border-radius:12px;
    font-weight:800;
}
.filter-grid button{
    background:#0f3460;
    color:white;
    border:none;
    border-radius:12px;
    padding:12px 18px;
    font-weight:900;
}

.cards{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:14px;
    margin-bottom:20px;
}
.cardx{
    background:white;
    border:1px solid #e5e7eb;
    border-radius:18px;
    padding:18px;
    box-shadow:0 8px 20px rgba(0,0,0,.06);
}
.cardx small{
    display:block;
    color:#64748b;
    font-weight:900;
}
.cardx strong{
    display:block;
    margin-top:8px;
    color:#0f3460;
    font-size:20px;
}

.badge{
    display:inline-block;
    padding:6px 10px;
    border-radius:999px;
    font-weight:900;
    font-size:12px;
}
.badge-green{background:#dcfce7;color:#166534}
.badge-orange{background:#ffedd5;color:#9a3412}
.badge-red{background:#fee2e2;color:#991b1b}
.badge-gray{background:#e5e7eb;color:#374151}

.payment-details{
    font-size:12px;
    color:#475569;
    max-width:280px;
    line-height:1.5;
}

@media(max-width:1000px){
    .filter-grid,.cards{grid-template-columns:1fr}
}
</style>
</head>

<body>
<div class="admin-layout">

<?php require_once "../../includes/sidebar.php"; ?>

<main class="main-content">

<?php require_once "../../includes/topbar.php"; ?>

<div class="report-hero">
    <h2>Rapport complet par Assujetti</h2>
    <p>Recherche toutes les notes d’un assujetti : payées, non payées, partielles ou défaillantes.</p>
</div>

<div class="panel">
    <form method="GET" class="filter-grid">
        <input type="text" name="search"
               placeholder="Nom, postnom, prénom, société, NIF ou téléphone"
               value="<?= htmlspecialchars($search) ?>"
               required>

        <input type="date" name="date_debut" value="<?= htmlspecialchars($date_debut) ?>">
        <input type="date" name="date_fin" value="<?= htmlspecialchars($date_fin) ?>">

        <select name="statut_note">
            <option value="toutes" <?= $statut_note==='toutes'?'selected':'' ?>>Toutes les notes</option>
            <option value="payees" <?= $statut_note==='payees'?'selected':'' ?>>Notes payées</option>
            <option value="non_payees" <?= $statut_note==='non_payees'?'selected':'' ?>>Notes non payées</option>
            <option value="partielles" <?= $statut_note==='partielles'?'selected':'' ?>>Notes partiellement payées</option>
        </select>

        <button type="submit">Rechercher</button>
    </form>
</div>

<?php if ($search !== ''): ?>

<div class="cards">
    <div class="cardx">
        <small>Total dû</small>
        <strong><?= number_format($totalDu,2,',',' ') ?> CDF</strong>
    </div>

    <div class="cardx">
        <small>Total payé</small>
        <strong><?= number_format($totalPaye,2,',',' ') ?> CDF</strong>
    </div>

    <div class="cardx">
        <small>Solde restant</small>
        <strong><?= number_format($totalSolde,2,',',' ') ?> CDF</strong>
    </div>

    <div class="cardx">
        <small>Notes trouvées</small>
        <strong><?= count($notes) ?></strong>
    </div>
</div>

<div class="cards">
    <div class="cardx">
        <small>Notes payées</small>
        <strong><?= $nbPayees ?></strong>
    </div>

    <div class="cardx">
        <small>Notes partielles</small>
        <strong><?= $nbPartielles ?></strong>
    </div>

    <div class="cardx">
        <small>Notes non payées / défaillantes</small>
        <strong><?= $nbNonPayees ?></strong>
    </div>

    <div class="cardx">
        <small>Total converti CDF</small>
        <strong><?= number_format($totalConvertiCDF,2,',',' ') ?> CDF</strong>
    </div>
</div>

<div class="panel">
    <h3>Résultat pour : <?= htmlspecialchars($search) ?></h3>

    <table class="table-premium">
        <tr>
            <th>Date émission</th>
            <th>Assujetti</th>
            <th>NIF</th>
            <th>NT</th>
            <th>ND</th>
            <th>NP / NPF</th>
            <th>Montant dû</th>
            <th>Montant payé</th>
            <th>Solde</th>
            <th>Statut</th>
            <th>Paiements</th>
            <th>Compte bancaire</th>
        </tr>

        <?php foreach($notes as $n): ?>
            <?php
                $montantDu = (float)($n['montant_initial'] ?: $n['montant_total']);
                $paye = (float)($n['montant_paye'] ?: $n['total_paye_reel']);
                $solde = (float)($n['solde_restant'] ?? 0);
            ?>
            <tr>
                <td><?= htmlspecialchars($n['date_emission'] ?? '-') ?></td>
                <td><strong><?= htmlspecialchars(nomAssujettiRapport($n)) ?></strong></td>
                <td><?= htmlspecialchars($n['nif'] ?? '-') ?></td>
                <td><?= htmlspecialchars($n['numero_nt'] ?? '-') ?></td>
                <td><?= htmlspecialchars($n['numero_nd'] ?? '-') ?></td>
                <td>
                    <strong><?= htmlspecialchars($n['numero_np'] ?? '-') ?></strong>
                    <?php if (!empty($n['type_np'])): ?>
                        <br><small><?= strtoupper(htmlspecialchars($n['type_np'])) ?></small>
                    <?php endif; ?>
                </td>
                <td><?= number_format($montantDu,2,',',' ') ?> CDF</td>
                <td><strong><?= number_format($paye,2,',',' ') ?> CDF</strong></td>
                <td><?= number_format($solde,2,',',' ') ?> CDF</td>
                <td><?= badgeStatutNote($n['statut_np'] ?? '') ?></td>
                <td class="payment-details">
                    <?php if (!empty($n['details_paiements'])): ?>
                        <?= htmlspecialchars($n['details_paiements']) ?>
                    <?php else: ?>
                        Aucun paiement enregistré
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($n['compte_bancaire'] ?? '-') ?></td>
            </tr>
        <?php endforeach; ?>

        <?php if(empty($notes)): ?>
            <tr>
                <td colspan="12">Aucune note trouvée pour cet assujetti.</td>
            </tr>
        <?php endif; ?>
    </table>
</div>

<?php endif; ?>

</main>
</div>
</body>
</html>