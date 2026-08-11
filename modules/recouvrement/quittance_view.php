<?php
/*
|--------------------------------------------------------------------------
| cOllect_Pay - Vue Quittance
|--------------------------------------------------------------------------
| Permission :
| - quittances / view  : consulter la quittance
| - quittances / print : afficher le bouton PDF
|--------------------------------------------------------------------------
*/

require_once "../../auth/check_auth.php";

checkAuth();
requirePermission('quittances', 'view');

$numero = $_GET['numero'] ?? null;

if (!$numero) {
    die("Numéro quittance obligatoire.");
}

$stmt = $pdo->prepare("
    SELECT 
        q.*,
        ap.reference_id,
        ap.reference_type,
        ap.montant_du,
        ap.montant_paye AS montant_apure,
        ap.statut AS statut_apurement,
        np.numero_np,
        np.type_np,
        np.montant_initial,
        np.montant_paye,
        np.solde_restant,
        np.date_echeance,
        nd.numero_nd,
        nt.numero_nt,
        nt.exercice,
        c.type_personne,
        c.raison_sociale,
        c.nom,
        c.postnom,
        c.prenom,
        c.nif,
        c.rccm,
        c.telephone,
        c.adresse,
        c.ville,
        u.nom AS nom_comptable
    FROM quittances q
    JOIN apurements ap ON q.apurement_id = ap.id
    JOIN notes_perception np ON ap.reference_id = np.id
    JOIN notes_debit nd ON np.note_debit_id = nd.id
    JOIN notes_taxation nt ON nd.note_taxation_id = nt.id
    JOIN contribuables c ON nt.contribuable_id = c.id
    LEFT JOIN users u ON q.user_comptable_id = u.id
    WHERE q.numero_quittance = ?
    LIMIT 1
");
$stmt->execute([$numero]);
$q = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$q) {
    die("Quittance introuvable.");
}

$stmt = $pdo->prepare("
    SELECT *
    FROM paiements
    WHERE note_perception_id = ?
    ORDER BY date_paiement ASC, id ASC
");
$stmt->execute([(int)$q['reference_id']]);
$paiements = $stmt->fetchAll(PDO::FETCH_ASSOC);

function nomQv(array $c): string
{
    if (!empty($c['raison_sociale'])) {
        return $c['raison_sociale'];
    }

    return trim(($c['nom'] ?? '') . ' ' . ($c['postnom'] ?? '') . ' ' . ($c['prenom'] ?? ''));
}

function moneyQv($v): string
{
    return number_format((float)$v, 2, ',', ' ') . ' CDF';
}

function dateQv($d): string
{
    return $d ? date('d/m/Y H:i:s', strtotime($d)) : '-';
}

$canPrint = function_exists('canDo') ? canDo('quittances', 'print') : true;

$page_title = "Quittance";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($page_title) ?> | cOllect_Pay</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../../assets/css/admin.css">

<style>
.doc-header{
    background:linear-gradient(135deg,#06152b,#0f3460);
    color:white;
    padding:26px;
    border-radius:24px;
    margin-bottom:24px;
}
.doc-header h2{margin:0 0 8px;font-weight:1000;}
.doc-header p{margin:6px 0;color:#dbeafe;font-weight:800;}
.badge{display:inline-block;padding:7px 12px;border-radius:999px;font-weight:900;}
.badge-green{background:#dcfce7;color:#166534;}
.amount-big{font-size:30px;font-weight:1000;color:#0f3460;}
.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:20px;}
.actions a{text-decoration:none;padding:12px 18px;border-radius:14px;font-weight:900;}
.btn-primary-custom{background:#0f3460;color:white;}
.btn-green-custom{background:#059669;color:white;}
.info-mini{
    background:#eff6ff;
    color:#1e3a8a;
    border:1px solid #bfdbfe;
    padding:12px 14px;
    border-radius:14px;
    font-weight:800;
    margin-bottom:18px;
}
</style>
<link rel="stylesheet" href="../../assets/css/recouvrement.css">
</head>

<body class="cp-recouvrement-page">
<div class="admin-layout">

<?php require_once "../../includes/sidebar.php"; ?>

<main class="main-content">

<?php require_once "../../includes/topbar.php"; ?>

<div class="doc-header">
    <h2>QUITTANCE INFORMATISÉE</h2>
    <p>Numéro : <strong><?= htmlspecialchars($q['numero_quittance']) ?></strong></p>
    <p>Comptable : <strong><?= htmlspecialchars($q['nom_comptable'] ?? '-') ?></strong></p>
    <span class="badge badge-green">ACQUIS LIBÉRATOIRE</span>
</div>

<div class="info-mini">
    Cette quittance confirme l’acquittement de la NP / NPF après apurement.
</div>

<div class="panel cp-rec-panel">
    <h3>I. Contribuable</h3>

    <table class="table-premium cp-rec-table">
        <tr><th>Nom / Raison sociale</th><td><?= htmlspecialchars(nomQv($q)) ?></td></tr>
        <tr><th>Type</th><td><?= strtoupper(htmlspecialchars($q['type_personne'] ?? '-')) ?></td></tr>
        <tr><th>NIF</th><td><?= htmlspecialchars($q['nif'] ?? '-') ?></td></tr>
        <tr><th>RCCM / Patente</th><td><?= htmlspecialchars($q['rccm'] ?? '-') ?></td></tr>
        <tr><th>Contacts</th><td><?= htmlspecialchars($q['telephone'] ?? '-') ?></td></tr>
        <tr><th>Ville / Adresse</th><td><?= htmlspecialchars(trim(($q['ville'] ?? '') . ' - ' . ($q['adresse'] ?? '-'))) ?></td></tr>
    </table>
</div>

<div class="panel cp-rec-panel">
    <h3>II. Références</h3>

    <table class="table-premium cp-rec-table">
        <tr><th>Note de Taxation</th><td><?= htmlspecialchars($q['numero_nt'] ?? '-') ?></td></tr>
        <tr><th>Note de Débit</th><td><?= htmlspecialchars($q['numero_nd'] ?? '-') ?></td></tr>
        <tr><th>NP / NPF</th><td><?= htmlspecialchars($q['numero_np'] ?? '-') ?></td></tr>
        <tr><th>Type NP</th><td><?= strtoupper(htmlspecialchars($q['type_np'] ?? '-')) ?></td></tr>
        <tr><th>Échéance</th><td><?= dateQv($q['date_echeance'] ?? null) ?></td></tr>
        <tr><th>Apurement</th><td><?= strtoupper(htmlspecialchars($q['statut_apurement'] ?? '-')) ?></td></tr>
    </table>
</div>

<div class="panel cp-rec-panel">
    <h3>III. Paiements</h3>

    <table class="table-premium cp-rec-table">
        <tr>
            <th>Date</th>
            <th>Mode</th>
            <th>Compte crédité</th>
            <th>Référence</th>
            <th>Montant</th>
        </tr>

        <?php foreach($paiements as $p): ?>
            <tr>
                <td><?= dateQv($p['date_paiement'] ?? $p['created_at'] ?? null) ?></td>
                <td><?= htmlspecialchars($p['mode_paiement'] ?? '-') ?></td>
                <td>
                    <?= htmlspecialchars(
                        trim(($p['banque'] ?? '') . ' ' . ($p['numero_compte'] ?? ''))
                        ?: ($p['compte_credite'] ?? '-')
                    ) ?>
                </td>
                <td><?= htmlspecialchars($p['reference_transaction'] ?? '-') ?></td>
                <td>
                    <strong>
                        <?= number_format((float)($p['montant_paye'] ?? 0), 2, ',', ' ') ?>
                        <?= htmlspecialchars($p['devise'] ?? 'CDF') ?>
                    </strong>
                </td>
            </tr>
        <?php endforeach; ?>

        <?php if(empty($paiements)): ?>
            <tr><td colspan="5">Aucun paiement trouvé.</td></tr>
        <?php endif; ?>
    </table>
</div>

<div class="panel cp-rec-panel">
    <h3>IV. Montant acquitté</h3>

    <table class="table-premium cp-rec-table">
        <tr><th>Montant dû</th><td><?= moneyQv($q['montant_du'] ?? 0) ?></td></tr>
        <tr><th>Montant payé</th><td><?= moneyQv($q['montant_paye'] ?? 0) ?></td></tr>
        <tr><th>Solde restant</th><td><?= moneyQv($q['solde_restant'] ?? 0) ?></td></tr>
        <tr><th>MONTANT ACQUITTÉ</th><td><span class="amount-big"><?= moneyQv($q['montant_acquitte'] ?? 0) ?></span></td></tr>
    </table>
</div>

<div class="actions">
    <a href="quittance_list.php" class="btn-primary-custom">
        Retour liste
    </a>

    <?php if ($canPrint): ?>
        <a target="_blank"
           href="../rapports/quittance_pdf.php?numero=<?= urlencode($q['numero_quittance']) ?>"
           class="btn-green-custom">
            Imprimer PDF
        </a>
    <?php endif; ?>
</div>

</main>
</div>
</body>
</html>
