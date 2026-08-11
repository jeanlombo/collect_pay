<?php
require_once "../../config/database.php";
require_once "../../config/security.php";
require_once "../../auth/check_auth.php";

checkAuth();

$mode = $_GET['mode'] ?? '';

if ($mode === 'apurement') {
    requirePermission('apurement', 'create');
} else {
    requirePermission('ordonnancement', 'view');
}

$page_title = "Notes de Perception";

$statut = $_GET['statut'] ?? '';
$type = $_GET['type'] ?? '';

$where = [];
$params = [];

if ($statut !== '') {
    $where[] = "np.statut = ?";
    $params[] = $statut;
}

if ($type !== '') {
    $where[] = "np.type_np = ?";
    $params[] = $type;
}

$whereSql = "";
if (!empty($where)) {
    $whereSql = "WHERE " . implode(" AND ", $where);
}

$stmt = $pdo->prepare("
    SELECT 
        np.*,
        nd.numero_nd,
        nt.numero_nt,
        c.type_personne,
        c.raison_sociale,
        c.nom,
        c.postnom,
        c.prenom,
        c.nif
    FROM notes_perception np
    JOIN notes_debit nd ON np.note_debit_id = nd.id
    JOIN notes_taxation nt ON nd.note_taxation_id = nt.id
    JOIN contribuables c ON nt.contribuable_id = c.id
    $whereSql
    ORDER BY np.date_emission DESC
");
$stmt->execute($params);
$notes = $stmt->fetchAll();

function nomContribuableNPList($c)
{
    if (!empty($c['raison_sociale'])) return $c['raison_sociale'];
    return trim(($c['nom'] ?? '') . ' ' . ($c['postnom'] ?? '') . ' ' . ($c['prenom'] ?? ''));
}

function formatDateNPList($date)
{
    if (!$date) return '-';
    return date('d/m/Y H:i:s', strtotime($date));
}

function badgeNP($statut)
{
    $statut = $statut ?? '';
    $label = strtoupper(str_replace('_', ' ', $statut));

    if ($statut === 'payee') {
        return "<span class='badge green'>$label</span>";
    }

    if ($statut === 'defaillante') {
        return "<span class='badge red'>$label</span>";
    }

    if ($statut === 'partiellement_payee') {
        return "<span class='badge orange'>$label</span>";
    }

    return "<span class='badge blue'>$label</span>";
}

function peutPayerNP($n)
{
    return in_array(($n['statut'] ?? ''), [
        'en_attente',
        'non_payee',
        'partiellement_payee',
        'defaillante'
    ]) && (float)($n['solde_restant'] ?? 0) > 0;
}

function getAMRNote($pdo, $noteId)
{
    $stmt = $pdo->prepare("
        SELECT id, statut
        FROM amr
        WHERE note_perception_id = ?
        LIMIT 1
    ");
    $stmt->execute([$noteId]);
    return $stmt->fetch();
}

function noteEstEchue($n)
{
    return (
        !empty($n['date_echeance']) &&
        strtotime(date('Y-m-d')) > strtotime(date('Y-m-d', strtotime($n['date_echeance'])))
    );
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($page_title) ?> | cOllect_Pay</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../../assets/css/admin.css">

<style>
.filters{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    margin-bottom:18px;
}
.filters a{
    text-decoration:none;
    padding:10px 14px;
    border-radius:12px;
    font-weight:900;
    background:#f1f5f9;
    color:#0f3460;
}
.badge{
    padding:6px 10px;
    border-radius:999px;
    font-weight:900;
    display:inline-block;
    font-size:12px;
}
.badge.green{background:#dcfce7;color:#166534}
.badge.red{background:#fee2e2;color:#991b1b}
.badge.orange{background:#ffedd5;color:#9a3412}
.badge.blue{background:#dbeafe;color:#1e40af}
.badge.dark{background:#111827;color:#fff}
.badge.purple{background:#ede9fe;color:#5b21b6}

.action-buttons{
    display:flex;
    gap:7px;
    flex-wrap:wrap;
}
.btn-view,
.btn-pay,
.btn-pdf,
.btn-amr{
    display:inline-block;
    padding:8px 11px;
    border-radius:10px;
    text-decoration:none;
    font-weight:900;
    font-size:12px;
    white-space:nowrap;
}
.btn-view{background:#0f3460;color:white}
.btn-pay{background:#fbbf24;color:#111827}
.btn-pdf{background:#f8fafc;color:#0f3460;border:1px solid #0f3460}
.btn-amr{background:#991b1b;color:white}
.small-muted{
    color:#64748b;
    font-size:12px;
}
.warning-text{
    color:#991b1b;
    font-weight:900;
    font-size:12px;
    margin-top:4px;
}
</style>
<link rel="stylesheet" href="../../assets/css/ordonnancement.css">
</head>

<body class="cp-ordonnancement-page">
<div class="admin-layout">

<?php require_once "../../includes/sidebar.php"; ?>

<main class="main-content">
<?php require_once "../../includes/topbar.php"; ?>

<div class="panel cp-panel cp-list-shell">
    <h2>Notes de Perception</h2>

    <div class="filters">
        <a href="np_list.php">Toutes</a>
        <a href="np_list.php?type=globale">NP Globales</a>
        <a href="np_list.php?type=fractionnee">NP Fractionnées</a>
        <a href="np_list.php?statut=en_attente">En attente</a>
        <a href="np_list.php?statut=non_payee">Non payées</a>
        <a href="np_list.php?statut=partiellement_payee">Partiellement payées</a>
        <a href="np_list.php?statut=payee">Payées</a>
        <a href="np_list.php?statut=defaillante">Défaillantes</a>
    </div>

    <table class="table-premium cp-ord-table">
        <tr>
            <th>N° NP / NPF</th>
            <th>Type</th>
            <th>Contribuable</th>
            <th>NIF</th>
            <th>ND</th>
            <th>NT</th>
            <th>Montant</th>
            <th>Solde</th>
            <th>Émission</th>
            <th>Échéance</th>
            <th>Statut</th>
            <th>Actions</th>
        </tr>

        <?php foreach ($notes as $n): ?>

            <?php
            $echue = noteEstEchue($n);
            $amr = getAMRNote($pdo, $n['id']);
            ?>

            <tr>
                <td>
                    <strong><?= htmlspecialchars($n['numero_np']) ?></strong>

                    <?php if (($n['type_np'] ?? '') === 'fractionnee'): ?>
                        <div class="small-muted">
                            Tranche <?= str_pad((int)($n['numero_tranche'] ?? 0), 3, '0', STR_PAD_LEFT) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($echue && ($n['statut'] ?? '') !== 'payee'): ?>
                        <div class="warning-text">
                            ÉCHUE — AMR requis
                        </div>
                    <?php endif; ?>
                </td>

                <td><?= strtoupper(htmlspecialchars($n['type_np'] ?? '-')) ?></td>
                <td><?= htmlspecialchars(nomContribuableNPList($n)) ?></td>
                <td><?= htmlspecialchars($n['nif'] ?? '-') ?></td>
                <td><?= htmlspecialchars($n['numero_nd']) ?></td>
                <td><?= htmlspecialchars($n['numero_nt']) ?></td>
                <td><?= number_format($n['montant_initial'] ?? 0, 2, ',', ' ') ?> CDF</td>
                <td><strong><?= number_format($n['solde_restant'] ?? 0, 2, ',', ' ') ?> CDF</strong></td>
                <td><?= htmlspecialchars(formatDateNPList($n['date_emission'] ?? null)) ?></td>
                <td><?= htmlspecialchars(formatDateNPList($n['date_echeance'] ?? null)) ?></td>

                <td>
                    <?= badgeNP($n['statut'] ?? '') ?>

                    <?php if ($amr): ?>
                        <br>
                        <?php if ($amr['statut'] === 'valide'): ?>
                            <span class="badge green">AMR VALIDÉ</span>
                        <?php elseif ($amr['statut'] === 'emis'): ?>
                            <span class="badge purple">AMR ÉMIS</span>
                        <?php else: ?>
                            <span class="badge red">AMR REJETÉ</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>

                <td>
                    <div class="action-buttons">

                        <a class="btn-view"
                           href="np_view.php?numero=<?= urlencode($n['numero_np']) ?>">
                            Voir
                        </a>

                        <?php if (
                            $echue &&
                            ($n['statut'] ?? '') !== 'payee' &&
                            !$amr
                        ): ?>
                            <a class="btn-amr"
                               href="../recouvrement/amr_generate.php?numero=<?= urlencode($n['numero_np']) ?>">
                                ⚠️ Générer AMR
                            </a>
                        <?php endif; ?>

                        <?php if (peutPayerNP($n)): ?>
                            <a class="btn-pay"
                               href="../recouvrement/paiement_add.php?numero=<?= urlencode($n['numero_np']) ?>">
                                Payer
                            </a>
                        <?php endif; ?>

                        <?php if (($n['type_np'] ?? '') === 'fractionnee'): ?>
                            <a class="btn-pdf"
                               target="_blank"
                               href="../rapports/npf_pdf.php?numero=<?= urlencode($n['numero_np']) ?>">
                                PDF
                            </a>
                        <?php else: ?>
                            <a class="btn-pdf"
                               target="_blank"
                               href="../rapports/np_pdf.php?numero=<?= urlencode($n['numero_np']) ?>">
                                PDF
                            </a>
                        <?php endif; ?>

                        <?php if (($n['statut'] ?? '') === 'payee'): ?>
                            <a class="btn-pay"
                               href="../recouvrement/apurement_process.php?numero=<?= urlencode($n['numero_np']) ?>">
                                Apurer
                            </a>
                        <?php endif; ?>

                    </div>
                </td>
            </tr>
        <?php endforeach; ?>

        <?php if (empty($notes)): ?>
            <tr>
                <td colspan="12">Aucune note de perception trouvée.</td>
            </tr>
        <?php endif; ?>
    </table>
</div>

</main>
</div>
</body>
</html>