<?php
require_once "../../config/database.php";
require_once "../../config/security.php";

checkAuth();
requireRole([
    'SUPER_ADMIN',
    'RECOUVREMENT',
    'CHEF_RECOUVREMENT',
    'CAISSIER'
]);

$page_title = "Détail du paiement";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$reference = trim($_GET['reference'] ?? '');

if ($id <= 0 && $reference === '') {
    die("ID paiement ou référence obligatoire.");
}

function moneyPV($v)
{
    return number_format((float)$v, 2, ',', ' ') . ' CDF';
}

function moneySourcePV($montant, $devise)
{
    return number_format((float)$montant, 2, ',', ' ') . ' ' . htmlspecialchars($devise);
}

function datePV($d)
{
    if (!$d) return '-';
    return date('d/m/Y H:i:s', strtotime($d));
}

function nomContribuablePV($c)
{
    if (!empty($c['raison_sociale'])) return $c['raison_sociale'];
    return trim(($c['nom'] ?? '') . ' ' . ($c['postnom'] ?? '') . ' ' . ($c['prenom'] ?? ''));
}

function badgePV($statut)
{
    $s = strtolower($statut ?? '');
    $class = 'badge-blue';

    if (in_array($s, ['payee', 'apure_total', 'total'], true)) {
        $class = 'badge-green';
    } elseif (in_array($s, ['partiellement_payee', 'apure_partiel', 'partiel'], true)) {
        $class = 'badge-orange';
    } elseif (in_array($s, ['annule', 'annulee'], true)) {
        $class = 'badge-red';
    }

    return '<span class="badge '.$class.'">'.htmlspecialchars(strtoupper(str_replace('_', ' ', $statut ?? '-'))).'</span>';
}

/*
|--------------------------------------------------------------------------
| Chargement paiement + NP
|--------------------------------------------------------------------------
*/
if ($id > 0) {
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            np.numero_np,
            np.type_np,
            np.np_mere_id,
            np.numero_tranche,
            np.montant_initial,
            np.montant_paye AS montant_total_paye_note,
            np.solde_restant,
            np.statut AS statut_np,
            mere.numero_np AS numero_np_mere,
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
        FROM paiements p
        JOIN notes_perception np ON p.note_perception_id = np.id
        LEFT JOIN notes_perception mere ON np.np_mere_id = mere.id
        JOIN notes_debit nd ON np.note_debit_id = nd.id
        JOIN notes_taxation nt ON nd.note_taxation_id = nt.id
        JOIN contribuables c ON nt.contribuable_id = c.id
        LEFT JOIN users u ON p.user_comptable_id = u.id
        WHERE p.id = ?
        LIMIT 1
    ");
    $stmt->execute([$id]);
} else {
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            np.numero_np,
            np.type_np,
            np.np_mere_id,
            np.numero_tranche,
            np.montant_initial,
            np.montant_paye AS montant_total_paye_note,
            np.solde_restant,
            np.statut AS statut_np,
            mere.numero_np AS numero_np_mere,
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
        FROM paiements p
        JOIN notes_perception np ON p.note_perception_id = np.id
        LEFT JOIN notes_perception mere ON np.np_mere_id = mere.id
        JOIN notes_debit nd ON np.note_debit_id = nd.id
        JOIN notes_taxation nt ON nd.note_taxation_id = nt.id
        JOIN contribuables c ON nt.contribuable_id = c.id
        LEFT JOIN users u ON p.user_comptable_id = u.id
        WHERE p.reference_transaction = ?
        ORDER BY p.id DESC
        LIMIT 1
    ");
    $stmt->execute([$reference]);
}

$p = $stmt->fetch();

if (!$p) {
    die("Paiement introuvable.");
}

/*
|--------------------------------------------------------------------------
| Historique des paiements de la même NP / NPF
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT *
    FROM paiements
    WHERE note_perception_id = ?
    ORDER BY created_at ASC, id ASC
");
$stmt->execute([$p['note_perception_id']]);
$historique = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Apurement / quittance
|--------------------------------------------------------------------------
*/
$referenceType = (($p['type_np'] ?? '') === 'fractionnee') ? 'FRACTION' : 'NP';

$stmt = $pdo->prepare("
    SELECT *
    FROM apurements
    WHERE reference_type = ?
    AND reference_id = ?
    ORDER BY id DESC
    LIMIT 1
");
$stmt->execute([
    $referenceType,
    $p['note_perception_id']
]);
$apurement = $stmt->fetch();

$quittance = null;
if ($apurement) {
    $stmt = $pdo->prepare("
        SELECT *
        FROM quittances
        WHERE apurement_id = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$apurement['id']]);
    $quittance = $stmt->fetch();
}

/*
|--------------------------------------------------------------------------
| Cas NP mère pour quittance finale
|--------------------------------------------------------------------------
*/
$mereInfo = null;
$toutesFractionsPayees = false;

if (($p['type_np'] ?? '') === 'fractionnee' && !empty($p['np_mere_id'])) {
    $stmt = $pdo->prepare("
        SELECT *
        FROM notes_perception
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$p['np_mere_id']]);
    $mereInfo = $stmt->fetch();

    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS total_non_payees
        FROM notes_perception
        WHERE np_mere_id = ?
        AND type_np = 'fractionnee'
        AND statut <> 'payee'
    ");
    $stmt->execute([$p['np_mere_id']]);
    $nonPayees = (int)$stmt->fetch()['total_non_payees'];

    $toutesFractionsPayees = ($nonPayees === 0);
}

$modeLabel = [
    1 => 'BANQUE',
    2 => 'CARTE BANCAIRE',
    3 => 'VIREMENT',
    4 => 'MOBILE MONEY',
    5 => 'PAIEMENT EN LIGNE'
];

?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($page_title) ?> | cOllect_Pay</title>
<link rel="stylesheet" href="/collect_pay/assets/css/admin.css">

<style>
.hero{
    background:linear-gradient(135deg,#06152b,#0f3460);
    color:white;
    border-radius:24px;
    padding:24px;
    margin-bottom:22px;
}
.hero h2{margin:0;font-weight:900}
.hero p{margin:8px 0 0;color:#dbeafe}
.grid-2{display:grid;grid-template-columns:repeat(2,1fr);gap:18px}
.badge{display:inline-block;padding:7px 12px;border-radius:999px;font-weight:900;font-size:12px}
.badge-green{background:#dcfce7;color:#166534}
.badge-orange{background:#ffedd5;color:#9a3412}
.badge-blue{background:#dbeafe;color:#1e40af}
.badge-red{background:#fee2e2;color:#991b1b}
.amount-big{font-size:28px;font-weight:900;color:#0f3460}
.action-row{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px}
.btn-action{display:inline-block;padding:11px 15px;border-radius:14px;text-decoration:none;font-weight:900;background:#0f3460;color:white}
.btn-attestation{background:#2563eb}
.btn-apurement{background:#7c3aed}
.btn-quittance{background:#059669}
.btn-secondary{background:white;color:#0f3460;border:1px solid #0f3460}
.note-info{background:#f8fafc;border:1px solid #e5e7eb;border-radius:16px;padding:14px;margin-top:12px;color:#334155}
@media(max-width:900px){.grid-2{grid-template-columns:1fr}}
</style>
</head>

<body>
<div class="admin-layout">

<?php require_once "../../includes/sidebar.php"; ?>

<main class="main-content">
<?php require_once "../../includes/topbar.php"; ?>

<div class="hero">
    <h2>Détail du paiement</h2>
    <p>Attestation disponible après chaque paiement. Quittance uniquement après apurement total de la NP globale.</p>
</div>

<div class="grid-2">
    <div class="panel">
        <h3>I. Paiement enregistré</h3>

        <table class="table-premium">
            <tr><th>Date paiement</th><td><?= htmlspecialchars(datePV($p['date_paiement'] ?? $p['created_at'])) ?></td></tr>
            <tr><th>Montant payé</th><td><strong><?= moneySourcePV($p['montant_paye'], $p['devise']) ?></strong></td></tr>
            <tr><th>Taux appliqué</th><td><?= number_format((float)$p['taux_change'], 4, ',', ' ') ?></td></tr>
            <tr><th>Montant converti</th><td><span class="amount-big"><?= moneyPV($p['montant_converti_cdf']) ?></span></td></tr>
            <tr><th>Mode paiement</th><td><?= htmlspecialchars($modeLabel[(int)$p['mode_paiement_id']] ?? $p['mode_paiement_id']) ?></td></tr>
            <tr><th>Référence transaction</th><td><?= htmlspecialchars($p['reference_transaction'] ?? '-') ?></td></tr>
            <tr><th>Statut paiement</th><td><?= badgePV($p['statut'] ?? '-') ?></td></tr>
            <tr><th>Agent / Comptable</th><td><?= htmlspecialchars($p['nom_comptable'] ?? '-') ?></td></tr>
        </table>
    </div>

    <div class="panel">
        <h3>II. Note concernée</h3>

        <table class="table-premium">
            <tr><th>NP / NPF</th><td><strong><?= htmlspecialchars($p['numero_np']) ?></strong></td></tr>
            <tr><th>Type</th><td><?= strtoupper(htmlspecialchars($p['type_np'])) ?></td></tr>
            <?php if (!empty($p['numero_np_mere'])): ?>
                <tr><th>NP mère</th><td><?= htmlspecialchars($p['numero_np_mere']) ?></td></tr>
            <?php endif; ?>
            <tr><th>ND</th><td><?= htmlspecialchars($p['numero_nd']) ?></td></tr>
            <tr><th>NT</th><td><?= htmlspecialchars($p['numero_nt']) ?></td></tr>
            <tr><th>Montant initial</th><td><?= moneyPV($p['montant_initial']) ?></td></tr>
            <tr><th>Total payé sur note</th><td><?= moneyPV($p['montant_total_paye_note']) ?></td></tr>
            <tr><th>Solde restant</th><td><strong><?= moneyPV($p['solde_restant']) ?></strong></td></tr>
            <tr><th>Statut NP / NPF</th><td><?= badgePV($p['statut_np'] ?? '-') ?></td></tr>
        </table>
    </div>
</div>

<div class="panel">
    <h3>III. Assujetti</h3>

    <table class="table-premium">
        <tr><th>Type</th><td><?= strtoupper(htmlspecialchars($p['type_personne'] ?? '-')) ?></td></tr>
        <tr><th>Nom / Raison sociale</th><td><strong><?= htmlspecialchars(nomContribuablePV($p)) ?></strong></td></tr>
        <tr><th>NIF</th><td><?= htmlspecialchars($p['nif'] ?? '-') ?></td></tr>
        <tr><th>RCCM / Patente</th><td><?= htmlspecialchars($p['rccm'] ?? '-') ?></td></tr>
        <tr><th>Téléphone</th><td><?= htmlspecialchars($p['telephone'] ?? '-') ?></td></tr>
        <tr><th>Adresse</th><td><?= htmlspecialchars(trim(($p['ville'] ?? '') . ' - ' . ($p['adresse'] ?? '-'))) ?></td></tr>
    </table>
</div>

<div class="panel">
    <h3>IV. Canal de paiement</h3>

    <table class="table-premium">
        <tr><th>Banque créditée</th><td><?= htmlspecialchars($p['banque'] ?? '-') ?></td></tr>
        <tr><th>Compte crédité</th><td><?= htmlspecialchars($p['numero_compte'] ?? $p['compte_credite'] ?? '-') ?></td></tr>
        <tr><th>Intitulé compte</th><td><?= htmlspecialchars($p['intitule_compte'] ?? '-') ?></td></tr>
        <tr><th>Banque émettrice</th><td><?= htmlspecialchars($p['banque_emettrice'] ?? '-') ?></td></tr>
        <tr><th>Banque bénéficiaire</th><td><?= htmlspecialchars($p['banque_beneficiaire'] ?? '-') ?></td></tr>
        <tr><th>Carte</th><td><?= htmlspecialchars(trim(($p['type_carte'] ?? '') . ' ' . ($p['numero_carte_masque'] ?? '')) ?: '-') ?></td></tr>
        <tr><th>Mobile Money</th><td><?= htmlspecialchars(trim(($p['reseau_mobile_money'] ?? '') . ' ' . ($p['telephone_mobile_money'] ?? '') . ' ' . ($p['titulaire_mobile_money'] ?? '')) ?: '-') ?></td></tr>
        <tr><th>Observation</th><td><?= nl2br(htmlspecialchars($p['observation'] ?? '-')) ?></td></tr>
    </table>
</div>

<div class="panel">
    <h3>V. Historique des paiements de cette note</h3>

    <table class="table-premium">
        <tr>
            <th>Date</th>
            <th>Montant source</th>
            <th>Taux</th>
            <th>Montant CDF</th>
            <th>Mode</th>
            <th>Référence</th>
            <th>Statut</th>
        </tr>

        <?php foreach ($historique as $h): ?>
            <tr>
                <td><?= htmlspecialchars(datePV($h['created_at'] ?? $h['date_paiement'])) ?></td>
                <td><?= moneySourcePV($h['montant_paye'], $h['devise']) ?></td>
                <td><?= number_format((float)$h['taux_change'], 4, ',', ' ') ?></td>
                <td><strong><?= moneyPV($h['montant_converti_cdf']) ?></strong></td>
                <td><?= htmlspecialchars($modeLabel[(int)$h['mode_paiement_id']] ?? $h['mode_paiement_id']) ?></td>
                <td><?= htmlspecialchars($h['reference_transaction'] ?? '-') ?></td>
                <td><?= badgePV($h['statut'] ?? '-') ?></td>
            </tr>
        <?php endforeach; ?>

        <?php if (empty($historique)): ?>
            <tr><td colspan="7">Aucun historique trouvé.</td></tr>
        <?php endif; ?>
    </table>
</div>

<div class="panel">
    <h3>VI. Attestation, apurement et quittance</h3>

    <div class="note-info">
        Chaque paiement donne droit à une attestation de paiement. La quittance est disponible uniquement lorsque la NP globale est soldée à zéro et apurée.
    </div>

    <div class="action-row">
        <a class="btn-action btn-attestation"
           target="_blank"
           href="/collect_pay/reports/attestation_paiement_pdf.php?numero=<?= urlencode($p['numero_np']) ?>">
            Imprimer Attestation
        </a>

        <?php if (($p['statut_np'] ?? '') === 'payee' && !$apurement): ?>
            <a class="btn-action btn-apurement"
               href="apurement_process.php?numero=<?= urlencode($p['numero_np']) ?>">
                Apurer cette note
            </a>
        <?php endif; ?>

        <?php if ($apurement): ?>
            <a class="btn-action btn-apurement"
               href="apurement_list.php">
                Voir apurement
            </a>

            <?php if ($quittance): ?>
                <a class="btn-action btn-quittance"
                   href="quittance_view.php?numero=<?= urlencode($quittance['numero_quittance']) ?>">
                    Voir quittance
                </a>

                <a class="btn-action btn-quittance"
                   target="_blank"
                   href="/collect_pay/reports/quittance_pdf.php?numero=<?= urlencode($quittance['numero_quittance']) ?>">
                    Imprimer quittance
                </a>
            <?php else: ?>

                <?php if (($p['type_np'] ?? '') === 'globale'): ?>
                    <a class="btn-action btn-quittance"
                       href="quittance_generate.php?apurement_id=<?= urlencode($apurement['id']) ?>">
                        Générer quittance
                    </a>
                <?php endif; ?>

            <?php endif; ?>
        <?php endif; ?>

        <?php if (($p['type_np'] ?? '') === 'fractionnee' && $toutesFractionsPayees && !empty($p['np_mere_id'])): ?>
            <a class="btn-action btn-quittance"
               href="quittance_generate_finale.php?np_mere_id=<?= urlencode($p['np_mere_id']) ?>">
                Générer quittance finale NP globale
            </a>
        <?php endif; ?>

        <a class="btn-action btn-secondary"
           href="../ordonnancement/np_view.php?numero=<?= urlencode($p['numero_np']) ?>">
            Retour NP / NPF
        </a>

        <a class="btn-action btn-secondary"
           href="paiement_list.php">
            Liste paiements
        </a>
    </div>

    <?php if (($p['type_np'] ?? '') === 'fractionnee' && !$toutesFractionsPayees): ?>
        <div class="note-info">
            Cette fraction peut avoir une attestation de paiement, mais la quittance finale sera disponible seulement lorsque toutes les fractions de la NP mère seront payées.
        </div>
    <?php endif; ?>
</div>

</main>
</div>
</body>
</html>
