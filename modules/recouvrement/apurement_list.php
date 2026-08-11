<?php
/*
|--------------------------------------------------------------------------
| cOllect_Pay - Liste des apurements
|--------------------------------------------------------------------------
| Correction :
| - Ne dépend plus de q.created_at
| - Détecte automatiquement la colonne date disponible dans quittances
|--------------------------------------------------------------------------
*/

require_once "../../auth/check_auth.php";

checkAuth();
requirePermission('apurement', 'view');

$page_title = "Liste des apurements";

$search = trim($_GET['search'] ?? '');
$statut = trim($_GET['statut'] ?? '');

/*
|--------------------------------------------------------------------------
| Détection colonne date quittance
|--------------------------------------------------------------------------
*/
$dateQuittanceExpr = "NULL";

try {
    $colsQ = array_column(
        $pdo->query("SHOW COLUMNS FROM quittances")->fetchAll(PDO::FETCH_ASSOC),
        'Field'
    );

    foreach (['created_at', 'date_emission', 'date_quittance', 'date_creation', 'date_created'] as $col) {
        if (in_array($col, $colsQ, true)) {
            $dateQuittanceExpr = "q.`$col`";
            break;
        }
    }
} catch (Throwable $e) {
    $dateQuittanceExpr = "NULL";
}

$sql = "
    SELECT
        ap.*,
        np.numero_np,
        np.type_np,
        np.statut AS statut_np,
        nd.numero_nd,
        nt.numero_nt,
        c.raison_sociale,
        c.nom,
        c.postnom,
        c.prenom,
        c.nif,
        q.numero_quittance,
        $dateQuittanceExpr AS date_quittance,
        ua.nom AS nom_apureur,
        uc.nom AS nom_comptable
    FROM apurements ap
    JOIN notes_perception np ON ap.reference_id = np.id
    JOIN notes_debit nd ON np.note_debit_id = nd.id
    JOIN notes_taxation nt ON nd.note_taxation_id = nt.id
    JOIN contribuables c ON nt.contribuable_id = c.id
    LEFT JOIN quittances q ON q.apurement_id = ap.id
    LEFT JOIN users ua ON ap.user_apurement_id = ua.id
    LEFT JOIN users uc ON q.user_comptable_id = uc.id
    WHERE 1=1
";

$params = [];

if ($statut !== '') {
    $sql .= " AND ap.statut = ?";
    $params[] = $statut;
}

if ($search !== '') {
    $sql .= "
        AND (
            np.numero_np LIKE ?
            OR nd.numero_nd LIKE ?
            OR nt.numero_nt LIKE ?
            OR q.numero_quittance LIKE ?
            OR c.raison_sociale LIKE ?
            OR c.nom LIKE ?
            OR c.nif LIKE ?
        )
    ";
    $like = "%$search%";
    $params = array_merge($params, [$like,$like,$like,$like,$like,$like,$like]);
}

$sql .= " ORDER BY ap.date_apurement DESC, ap.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

function nomApList(array $c): string {
    return !empty($c['raison_sociale'])
        ? $c['raison_sociale']
        : trim(($c['nom'] ?? '') . ' ' . ($c['postnom'] ?? '') . ' ' . ($c['prenom'] ?? ''));
}

function moneyApList($v): string {
    return number_format((float)$v, 2, ',', ' ') . ' CDF';
}

function badgeApList($statut): string {
    $s = strtolower((string)$statut);
    if ($s === 'total') return "<span class='badge badge-green'>TOTAL</span>";
    if ($s === 'partiel') return "<span class='badge badge-orange'>PARTIEL</span>";
    return "<span class='badge badge-gray'>".htmlspecialchars(strtoupper($s ?: '-'))."</span>";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($page_title) ?> | cOllect_Pay</title>
<link rel="stylesheet" href="/collect_pay/assets/css/admin.css">
<style>
.hero-ap{background:linear-gradient(135deg,#064e3b,#0f766e);color:white;padding:24px;border-radius:24px;margin-bottom:20px}
.hero-ap h2{margin:0;font-weight:1000}.hero-ap p{margin:7px 0 0;color:#ccfbf1;font-weight:800}
.filter-form{display:grid;grid-template-columns:2fr 1fr auto;gap:12px;margin-top:15px}
.filter-form input,.filter-form select{padding:12px;border:1px solid #d1d5db;border-radius:12px;font-weight:800}
.filter-form button{background:#0f3460;color:white;border:none;border-radius:12px;padding:12px 16px;font-weight:900}
.badge{display:inline-block;padding:6px 10px;border-radius:999px;font-weight:900;font-size:12px}
.badge-green{background:#dcfce7;color:#166534}.badge-orange{background:#ffedd5;color:#9a3412}.badge-gray{background:#e5e7eb;color:#111827}
.btn-action{display:inline-block;padding:8px 11px;border-radius:11px;text-decoration:none;font-weight:900;font-size:12px;margin:2px}
.btn-view{background:#e5e7eb;color:#111827}.btn-qt{background:#0f766e;color:white}.btn-pdf{background:#fbbf24;color:#111827}
@media(max-width:900px){.filter-form{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="admin-layout">
<?php require_once "../../includes/sidebar.php"; ?>
<main class="main-content">
<?php require_once "../../includes/topbar.php"; ?>

<div class="hero-ap">
    <h2>Liste des apurements</h2>
    <p>Le comptable public génère la quittance uniquement après apurement total.</p>
</div>

<div class="panel">
    <form method="GET" class="filter-form">
        <input type="text" name="search" placeholder="Rechercher NP, ND, NT, quittance, contribuable, NIF" value="<?= htmlspecialchars($search) ?>">
        <select name="statut">
            <option value="">Tous statuts</option>
            <option value="total" <?= $statut==='total'?'selected':'' ?>>Total</option>
            <option value="partiel" <?= $statut==='partiel'?'selected':'' ?>>Partiel</option>
        </select>
        <button type="submit">Filtrer</button>
    </form>
</div>

<div class="panel">
<table class="table-premium">
<tr>
    <th>Date apurement</th>
    <th>NP / NPF</th>
    <th>Contribuable</th>
    <th>Montant dû</th>
    <th>Montant payé</th>
    <th>Solde</th>
    <th>Statut</th>
    <th>Quittance</th>
    <th>Action</th>
</tr>

<?php foreach($rows as $r): ?>
<tr>
    <td><?= htmlspecialchars($r['date_apurement'] ?? '-') ?></td>
    <td>
        <strong><?= htmlspecialchars($r['numero_np'] ?? '-') ?></strong><br>
        <small><?= htmlspecialchars($r['numero_nd'] ?? '-') ?> / <?= htmlspecialchars($r['numero_nt'] ?? '-') ?></small>
    </td>
    <td><?= htmlspecialchars(nomApList($r)) ?><br><small>NIF : <?= htmlspecialchars($r['nif'] ?? '-') ?></small></td>
    <td><?= moneyApList($r['montant_du'] ?? 0) ?></td>
    <td><?= moneyApList($r['montant_paye'] ?? 0) ?></td>
    <td><?= moneyApList($r['solde_restant'] ?? 0) ?></td>
    <td><?= badgeApList($r['statut'] ?? '-') ?></td>
    <td>
        <?php if (!empty($r['numero_quittance'])): ?>
            <strong><?= htmlspecialchars($r['numero_quittance']) ?></strong><br>
            <small><?= htmlspecialchars($r['date_quittance'] ?? '-') ?></small>
        <?php else: ?>
            <span style="color:#991b1b;font-weight:900;">Non générée</span>
        <?php endif; ?>
    </td>
    <td>
        <?php if (!empty($r['numero_quittance'])): ?>
            <a class="btn-action btn-view" href="quittance_view.php?numero=<?= urlencode($r['numero_quittance']) ?>">Voir</a>
            <a class="btn-action btn-pdf" target="_blank" href="../rapports/quittance_pdf.php?numero=<?= urlencode($r['numero_quittance']) ?>">PDF</a>
        <?php elseif (($r['statut'] ?? '') === 'total' && function_exists('canDo') && canDo('quittances','create')): ?>
            <a class="btn-action btn-qt" href="quittance_generate.php?apurement_id=<?= (int)$r['id'] ?>">Générer quittance</a>
        <?php else: ?>
            -
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>

<?php if(empty($rows)): ?>
<tr><td colspan="9">Aucun apurement trouvé.</td></tr>
<?php endif; ?>
</table>
</div>

</main>
</div>
</body>
</html>
