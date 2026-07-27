<?php
/*
|--------------------------------------------------------------------------
| cOllect_Pay - Paiement AMR
|--------------------------------------------------------------------------
| Ce fichier évite le Not Found sur :
| modules/recouvrement/paiement_amr.php?numero=...
|
| Il accepte :
| - numero AMR
| - numero NP / NPF
|--------------------------------------------------------------------------
*/

require_once "../../auth/check_auth.php";

checkAuth();

if (function_exists('canDo')) {
    if (!canDo('amr', 'pay') && !canDo('paiements', 'add_np') && !canDo('paiements', 'add_npf')) {
        requirePermission('amr', 'pay');
    }
}

$page_title = "Paiement AMR";

$numero = trim($_GET['numero'] ?? '');

if ($numero === '') {
    die("Numéro AMR ou NP obligatoire.");
}

/*
|--------------------------------------------------------------------------
| Recherche AMR par numéro AMR ou numéro NP
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT 
        amr.*,
        np.id AS np_id,
        np.numero_np,
        np.type_np,
        np.statut AS statut_np,
        np.montant_total AS montant_np_total,
        np.montant_paye AS montant_np_paye,
        np.solde_restant AS solde_np_restant,
        nd.numero_nd,
        nt.numero_nt,
        c.raison_sociale,
        c.nom,
        c.postnom,
        c.prenom,
        c.nif,
        c.telephone
    FROM amr
    JOIN notes_perception np ON amr.note_perception_id = np.id
    JOIN notes_debit nd ON np.note_debit_id = nd.id
    JOIN notes_taxation nt ON nd.note_taxation_id = nt.id
    JOIN contribuables c ON nt.contribuable_id = c.id
    WHERE amr.numero_amr = ?
       OR np.numero_np = ?
    LIMIT 1
");
$stmt->execute([$numero, $numero]);
$amr = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$amr) {
    die("AMR ou NP introuvable pour le numéro : " . htmlspecialchars($numero));
}

function nomContribuablePaiementAMR(array $c): string
{
    if (!empty($c['raison_sociale'])) {
        return $c['raison_sociale'];
    }

    return trim(($c['nom'] ?? '') . ' ' . ($c['postnom'] ?? '') . ' ' . ($c['prenom'] ?? ''));
}

function montantAMR($v): string
{
    return number_format((float)$v, 2, ',', ' ') . " CDF";
}

/*
|--------------------------------------------------------------------------
| Détection de la vraie page de paiement existante dans ton projet
|--------------------------------------------------------------------------
*/

$npId = (int)($amr['np_id'] ?? 0);
$numeroNp = $amr['numero_np'] ?? '';

$candidates = [
    [
        'label' => 'Continuer vers paiement NP',
        'file'  => __DIR__ . "/paiement_np.php",
        'url'   => "paiement_np.php?np_id=" . $npId
    ],
    [
        'label' => 'Continuer vers paiement NP',
        'file'  => __DIR__ . "/paiement_create.php",
        'url'   => "paiement_create.php?np_id=" . $npId
    ],
    [
        'label' => 'Continuer vers paiement NP',
        'file'  => __DIR__ . "/payer_np.php",
        'url'   => "payer_np.php?np_id=" . $npId
    ],
    [
        'label' => 'Continuer vers paiement NP',
        'file'  => __DIR__ . "/paiement.php",
        'url'   => "paiement.php?np_id=" . $npId
    ],
    [
        'label' => 'Ouvrir la liste des NP à payer',
        'file'  => __DIR__ . "/../ordonnancement/np_list.php",
        'url'   => "../ordonnancement/np_list.php?statut=en_attente&numero=" . urlencode($numeroNp)
    ],
];

$paiementUrl = "../ordonnancement/np_list.php?numero=" . urlencode($numeroNp);
$paiementLabel = "Ouvrir la NP liée";

foreach ($candidates as $c) {
    if (file_exists($c['file'])) {
        $paiementUrl = $c['url'];
        $paiementLabel = $c['label'];
        break;
    }
}

$isPaid = in_array(($amr['statut_np'] ?? ''), ['payee', 'payé', 'paye'], true)
    || (float)($amr['solde_np_restant'] ?? 0) <= 0.01;

?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($page_title) ?> | cOllect_Pay</title>
<link rel="stylesheet" href="/collect_pay/assets/css/admin.css">

<style>
.amr-pay-hero{
    background:linear-gradient(135deg,#0f3460,#06152b);
    color:white;
    padding:24px;
    border-radius:24px;
    margin-bottom:20px;
}
.amr-pay-hero h2{
    margin:0;
    font-weight:1000;
}
.amr-pay-hero p{
    margin:8px 0 0;
    color:#dbeafe;
}
.info-grid{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:14px;
    margin-top:18px;
}
.info-card{
    background:#f8fafc;
    border:1px solid #e5e7eb;
    border-radius:18px;
    padding:16px;
}
.info-card small{
    display:block;
    color:#64748b;
    font-weight:900;
    margin-bottom:6px;
}
.info-card strong{
    font-size:18px;
    color:#06152b;
}
.amount-red{
    color:#991b1b!important;
}
.actions{
    display:flex;
    gap:12px;
    flex-wrap:wrap;
    margin-top:20px;
}
.btn-main{
    display:inline-block;
    background:#0f766e;
    color:white;
    padding:13px 18px;
    border-radius:14px;
    text-decoration:none;
    font-weight:1000;
}
.btn-gray{
    display:inline-block;
    background:#e5e7eb;
    color:#111827;
    padding:13px 18px;
    border-radius:14px;
    text-decoration:none;
    font-weight:1000;
}
.btn-pdf{
    display:inline-block;
    background:#fbbf24;
    color:#111827;
    padding:13px 18px;
    border-radius:14px;
    text-decoration:none;
    font-weight:1000;
}
.alert-paid{
    background:#dcfce7;
    color:#166534;
    border:1px solid #bbf7d0;
    padding:14px;
    border-radius:16px;
    font-weight:900;
    margin-bottom:18px;
}
.alert-warn{
    background:#ffedd5;
    color:#9a3412;
    border:1px solid #fed7aa;
    padding:14px;
    border-radius:16px;
    font-weight:900;
    margin-bottom:18px;
}
@media(max-width:900px){
    .info-grid{
        grid-template-columns:1fr;
    }
}
</style>
</head>

<body>
<div class="admin-layout">
<?php require_once "../../includes/sidebar.php"; ?>

<main class="main-content">
<?php require_once "../../includes/topbar.php"; ?>

<div class="amr-pay-hero">
    <h2>Paiement AMR</h2>
    <p>AMR / NP liée : <?= htmlspecialchars($amr['numero_amr'] ?? '-') ?> — <?= htmlspecialchars($numeroNp) ?></p>
</div>

<?php if ($isPaid): ?>
    <div class="alert-paid">
        ✅ Cette NP liée à l’AMR semble déjà totalement payée.
    </div>
<?php else: ?>
    <div class="alert-warn">
        ⚠️ Cette AMR est encore à régulariser par paiement.
    </div>
<?php endif; ?>

<div class="panel">
    <h3>Détails de l’AMR</h3>

    <div class="info-grid">
        <div class="info-card">
            <small>N° AMR</small>
            <strong><?= htmlspecialchars($amr['numero_amr'] ?? '-') ?></strong>
        </div>

        <div class="info-card">
            <small>N° NP / NPF</small>
            <strong><?= htmlspecialchars($numeroNp) ?></strong>
        </div>

        <div class="info-card">
            <small>Contribuable</small>
            <strong><?= htmlspecialchars(nomContribuablePaiementAMR($amr)) ?></strong>
        </div>

        <div class="info-card">
            <small>NIF</small>
            <strong><?= htmlspecialchars($amr['nif'] ?? '-') ?></strong>
        </div>

        <div class="info-card">
            <small>Principal</small>
            <strong><?= montantAMR($amr['montant_principal'] ?? 0) ?></strong>
        </div>

        <div class="info-card">
            <small>Pénalité AMR</small>
            <strong class="amount-red"><?= montantAMR($amr['montant_penalite'] ?? 0) ?></strong>
        </div>

        <div class="info-card">
            <small>Total AMR</small>
            <strong class="amount-red"><?= montantAMR($amr['montant_total'] ?? 0) ?></strong>
        </div>

        <div class="info-card">
            <small>Payé sur NP</small>
            <strong><?= montantAMR($amr['montant_np_paye'] ?? 0) ?></strong>
        </div>

        <div class="info-card">
            <small>Solde restant NP</small>
            <strong class="amount-red"><?= montantAMR($amr['solde_np_restant'] ?? 0) ?></strong>
        </div>
    </div>

    <div class="actions">
        <a class="btn-main" href="<?= htmlspecialchars($paiementUrl) ?>">
            <?= htmlspecialchars($paiementLabel) ?>
        </a>

        <a class="btn-pdf" target="_blank" href="/collect_pay/reports/amr_pdf.php?numero=<?= urlencode($amr['numero_amr'] ?? '') ?>">
            PDF AMR
        </a>

        <a class="btn-gray" href="amr_list.php">
            ← Retour liste AMR
        </a>
    </div>
</div>

</main>
</div>
</body>
</html>
