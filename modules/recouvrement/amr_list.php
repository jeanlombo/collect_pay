<?php
/*
|--------------------------------------------------------------------------
| cOllect_Pay - Liste des AMR
|--------------------------------------------------------------------------
| Correction sécurité :
| - Avant : requireRole(...)
| - Maintenant : requirePermission('amr','view')
| Le CAISSIER peut voir les AMR si la permission AMR lui est attribuée.
|--------------------------------------------------------------------------
*/

require_once "../../auth/check_auth.php";

checkAuth();
requirePermission('amr', 'view');

$page_title = "Liste des AMR";

$statut = $_GET['statut'] ?? '';

$where = "";
$params = [];

if ($statut !== '') {
    $where = "WHERE amr.statut = ?";
    $params[] = $statut;
}

$stmt = $pdo->prepare("
    SELECT 
        amr.*,
        np.type_np,
        np.statut AS statut_np,
        np.date_echeance,
        nd.numero_nd,
        nt.numero_nt,
        c.raison_sociale,
        c.nom,
        c.postnom,
        c.prenom,
        c.nif,
        ue.nom AS nom_emetteur,
        uv.nom AS nom_validateur
    FROM amr
    JOIN notes_perception np ON amr.note_perception_id = np.id
    JOIN notes_debit nd ON np.note_debit_id = nd.id
    JOIN notes_taxation nt ON nd.note_taxation_id = nt.id
    JOIN contribuables c ON nt.contribuable_id = c.id
    LEFT JOIN users ue ON amr.user_emission_id = ue.id
    LEFT JOIN users uv ON amr.user_validation_id = uv.id
    $where
    ORDER BY amr.date_emission DESC
");
$stmt->execute($params);
$amrs = $stmt->fetchAll(PDO::FETCH_ASSOC);

function nomContribuableAMRList($c)
{
    if (!empty($c['raison_sociale'])) {
        return $c['raison_sociale'];
    }

    return trim(
        ($c['nom'] ?? '') . ' ' .
        ($c['postnom'] ?? '') . ' ' .
        ($c['prenom'] ?? '')
    );
}

function formatDateAMRList($date)
{
    if (!$date) return '-';
    return date('d/m/Y H:i:s', strtotime($date));
}

function badgeAMR($statut)
{
    if ($statut === 'valide') {
        return "<span class='badge badge-green'>VALIDÉ</span>";
    }

    if ($statut === 'rejete') {
        return "<span class='badge badge-red'>REJETÉ</span>";
    }

    return "<span class='badge badge-orange'>ÉMIS</span>";
}

$canPayAMR = function_exists('canDo') && canDo('amr', 'pay');
$canPrintAMR = function_exists('canDo') ? canDo('amr', 'print') : true;
$canValidateAMR = function_exists('canDo') ? canDo('amr', 'create') : false;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($page_title) ?> | cOllect_Pay</title>
<link rel="stylesheet" href="/collect_pay/assets/css/admin.css">

<style>
.hero-amr{
    background:linear-gradient(135deg,#7f1d1d,#991b1b);
    color:white;
    padding:24px;
    border-radius:24px;
    margin-bottom:22px;
}
.hero-amr h2{margin:0;font-weight:900}
.hero-amr p{margin:8px 0 0;color:#fee2e2}
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
    display:inline-block;
    padding:7px 12px;
    border-radius:999px;
    font-weight:900;
}
.badge-green{background:#dcfce7;color:#166534}
.badge-orange{background:#ffedd5;color:#9a3412}
.badge-red{background:#fee2e2;color:#991b1b}
.amount{
    font-weight:900;
    color:#991b1b;
}
.action-group{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
}
.btn-action{
    display:inline-block;
    padding:8px 12px;
    border-radius:12px;
    text-decoration:none;
    font-weight:900;
    background:#0f3460;
    color:white;
}
.btn-validate{
    background:#16a34a;
}
.btn-pdf{
    background:#fbbf24;
    color:#111827;
}
.btn-pay{
    background:#0f766e;
    color:white;
}
</style>
</head>

<body>
<div class="admin-layout">

<?php require_once "../../includes/sidebar.php"; ?>

<main class="main-content">
<?php require_once "../../includes/topbar.php"; ?>

<div class="hero-amr">
    <h2>Avis de Mise en Recouvrement</h2>
    <p>Suivi des AMR émis pour NP / NPF échues avant reprise du paiement.</p>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="panel" style="background:#dcfce7;color:#166534;font-weight:900;">
        AMR émis avec succès.
    </div>
<?php endif; ?>

<?php if (isset($_GET['existing'])): ?>
    <div class="panel" style="background:#ffedd5;color:#9a3412;font-weight:900;">
        Un AMR existe déjà pour cette NP / NPF.
    </div>
<?php endif; ?>

<?php if (isset($_GET['validated'])): ?>
    <div class="panel" style="background:#dcfce7;color:#166534;font-weight:900;">
        AMR validé avec succès.
    </div>
<?php endif; ?>

<div class="panel">
    <h3>Filtres</h3>

    <div class="filters">
        <a href="amr_list.php">Tous</a>
        <a href="amr_list.php?statut=emis">Émis</a>
        <a href="amr_list.php?statut=valide">Validés</a>
        <a href="amr_list.php?statut=rejete">Rejetés</a>
    </div>

    <table class="table-premium">
        <tr>
            <th>Date émission</th>
            <th>N° AMR</th>
            <th>Type</th>
            <th>Contribuable</th>
            <th>NIF</th>
            <th>Principal</th>
            <th>Pénalité</th>
            <th>Total</th>
            <th>Retard</th>
            <th>Statut</th>
            <th>Action</th>
        </tr>

        <?php foreach ($amrs as $a): ?>
            <tr>
                <td><?= htmlspecialchars(formatDateAMRList($a['date_emission'])) ?></td>
                <td><strong><?= htmlspecialchars($a['numero_amr']) ?></strong></td>
                <td><?= htmlspecialchars($a['reference_type']) ?></td>
                <td><?= htmlspecialchars(nomContribuableAMRList($a)) ?></td>
                <td><?= htmlspecialchars($a['nif'] ?? '-') ?></td>
                <td><?= number_format((float)$a['montant_principal'], 2, ',', ' ') ?> CDF</td>
                <td><span class="amount"><?= number_format((float)$a['montant_penalite'], 2, ',', ' ') ?> CDF</span></td>
                <td><strong><?= number_format((float)$a['montant_total'], 2, ',', ' ') ?> CDF</strong></td>
                <td><?= (int)$a['jours_retard'] ?> j</td>
                <td><?= badgeAMR($a['statut']) ?></td>
                <td>
                    <div class="action-group">
                        <?php if ($canPrintAMR): ?>
                            <a class="btn-action btn-pdf"
                               target="_blank"
                               href="/collect_pay/reports/amr_pdf.php?numero=<?= urlencode($a['numero_amr']) ?>">
                                PDF
                            </a>
                        <?php endif; ?>

                        <?php if ($canPayAMR && (($a['statut'] ?? '') === 'valide' || ($a['statut'] ?? '') === 'emis')): ?>
                            <a class="btn-action btn-pay"
                               href="/collect_pay/modules/recouvrement/paiement_amr.php?numero=<?= urlencode($a['numero_amr']) ?>">
                                Payer
                            </a>
                        <?php endif; ?>

                        <?php if ($canValidateAMR && (($a['statut'] ?? '') === 'emis')): ?>
                            <a class="btn-action btn-validate"
                               href="amr_validate.php?id=<?= (int)$a['id'] ?>">
                                Valider
                            </a>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>

        <?php if (empty($amrs)): ?>
            <tr>
                <td colspan="11">Aucun AMR enregistré.</td>
            </tr>
        <?php endif; ?>
    </table>
</div>
<?php if (function_exists('canDo') && (canDo('amr','pay') || canDo('apurement','create'))): ?>
    <a class="btn-action btn-pay"
       href="/collect_pay/modules/recouvrement/paiement_amr.php?numero=<?= urlencode($a['numero_amr']) ?>">
        Apurer / Payer
    </a>
<?php endif; ?>
</main>
</div>
</body>
</html>
