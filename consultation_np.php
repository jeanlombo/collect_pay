<?php
ini_set('display_errors','0');
error_reporting(E_ALL);

$databaseFile = __DIR__ . '/config/database.php';
if (is_file($databaseFile)) {
    require_once $databaseFile;
}

function cpPublicE($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}
function cpPublicMoney($value, string $currency='CDF'): string {
    $decimals = strtoupper($currency)==='CDF' ? 0 : 2;
    return number_format((float)$value,$decimals,',',' ').' '.strtoupper($currency);
}
function cpPublicDate($value): string {
    if (!$value) return '-';
    $ts = strtotime((string)$value);
    return $ts ? date('d/m/Y',$ts) : cpPublicE($value);
}

$db = isset($pdo) && $pdo instanceof PDO ? $pdo : null;
$numero = strtoupper(trim((string)($_GET['numero'] ?? '')));
$note = null;
$error = '';

if ($numero !== '' && $db) {
    try {
        $stmt = $db->prepare("
            SELECT
                np.id,
                np.numero_np,
                np.type_np,
                np.montant_initial,
                np.montant_total,
                np.solde_restant,
                np.date_emission,
                np.date_echeance,
                np.statut,
                np.devise,
                nd.numero_nd,
                nt.numero_nt,
                COALESCE((
                    SELECT SUM(p.montant_converti_cdf)
                    FROM paiements p
                    WHERE p.note_perception_id = np.id
                      AND p.statut <> 'annule'
                ),0) AS montant_paye_cdf,
                COALESCE((
                    SELECT COUNT(*)
                    FROM paiements p
                    WHERE p.note_perception_id = np.id
                      AND p.statut <> 'annule'
                ),0) AS nombre_paiements,
                COALESCE((
                    SELECT MAX(p.date_paiement)
                    FROM paiements p
                    WHERE p.note_perception_id = np.id
                      AND p.statut <> 'annule'
                ),NULL) AS dernier_paiement
            FROM notes_perception np
            JOIN notes_debit nd ON np.note_debit_id = nd.id
            JOIN notes_taxation nt ON nd.note_taxation_id = nt.id
            WHERE UPPER(np.numero_np)=?
            LIMIT 1
        ");
        $stmt->execute([$numero]);
        $note = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$note) {
            $error = "Aucune NP ou NPF ne correspond à ce numéro.";
        }
    } catch (Throwable $e) {
        error_log("Consultation NP publique : ".$e->getMessage());
        $error = "La consultation est momentanément indisponible.";
    }
} elseif ($numero !== '' && !$db) {
    $error = "La base de données est momentanément indisponible.";
}

$du = $note ? (float)($note['montant_initial'] ?: $note['montant_total']) : 0;
$paye = $note ? (float)$note['montant_paye_cdf'] : 0;
$solde = $note ? max(0,(float)$note['solde_restant']) : 0;
$taux = $du > 0 ? min(100,($paye/$du)*100) : 0;

$statusLabels = [
    'payee'=>'PAYÉE',
    'partiellement_payee'=>'PARTIELLEMENT PAYÉE',
    'non_payee'=>'NON PAYÉE',
    'en_attente'=>'EN ATTENTE',
    'defaillante'=>'DÉFAILLANTE',
    'annulee'=>'ANNULÉE'
];
$statut = strtolower((string)($note['statut'] ?? ''));
$statusLabel = $statusLabels[$statut] ?? strtoupper($statut ?: '-');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Consultation NP / NPF | cOllect_Pay</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="assets/css/public.css" rel="stylesheet">
<link href="assets/css/public_interactive.css" rel="stylesheet">
</head>
<body class="public-tool-page">
<nav class="navbar navbar-dark premium-nav public-tool-nav">
<div class="container">
<a class="navbar-brand" href="index.php">
<span class="brand-mark"><i class="bi bi-shield-check"></i></span>
<span class="brand-copy"><strong>cOllect_Pay</strong><small>CONSULTATION PUBLIQUE NP / NPF</small></span>
</a>
<div class="public-tool-actions">
<a href="verification_qr.php" class="btn btn-nav-outline"><i class="bi bi-qr-code-scan"></i> Vérifier QR</a>
<a href="index.php" class="btn btn-nav-gold"><i class="bi bi-house"></i> Vitrine</a>
</div>
</div>
</nav>

<main class="public-tool-main">
<section class="tool-hero np-tool-hero">
<div class="tool-grid-overlay"></div>
<div class="container position-relative">
<div class="row align-items-center g-4">
<div class="col-lg-7">
<span class="tool-eyebrow"><i class="bi bi-database-check"></i> Données officielles en temps réel</span>
<h1>Consulter une <span>NP ou NPF</span></h1>
<p>Recherchez le numéro exact de votre Note de Perception pour connaître sa situation officielle sans accéder à l’espace administratif.</p>
<div class="tool-trust">
<span><i class="bi bi-eye-slash"></i> Données personnelles masquées</span>
<span><i class="bi bi-shield-lock"></i> Lecture seule</span>
<span><i class="bi bi-arrow-repeat"></i> Situation actualisée</span>
</div>
</div>
<div class="col-lg-5">
<form method="get" class="public-search-glass">
<label for="numero">Numéro NP / NPF</label>
<div class="public-search-line">
<i class="bi bi-search"></i>
<input id="numero" name="numero" value="<?= cpPublicE($numero) ?>" placeholder="Ex. NP-BU-CPR-26-000017" required autocomplete="off">
<button type="submit">Consulter</button>
</div>
<small>La recherche interroge directement le registre cOllect_Pay.</small>
</form>
</div>
</div>
</div>
</section>

<section class="tool-result-section">
<div class="container">
<?php if ($error): ?>
<div class="tool-message error"><i class="bi bi-exclamation-octagon"></i><div><strong>Note introuvable</strong><span><?= cpPublicE($error) ?></span></div></div>
<?php endif; ?>

<?php if ($note): ?>
<div class="np-status-head">
<div>
<span class="status-led <?= cpPublicE($statut) ?>"></span>
<small>STATUT OFFICIEL</small>
<h2><?= cpPublicE($statusLabel) ?></h2>
</div>
<div class="np-number-block"><small>RÉFÉRENCE</small><strong><?= cpPublicE($note['numero_np']) ?></strong><span><?= cpPublicE(strtoupper($note['type_np'] ?? 'NP')) ?></span></div>
</div>

<div class="real-revenue-cards">
<article><span class="metric-icon due"><i class="bi bi-receipt"></i></span><small>Montant dû</small><strong><?= cpPublicMoney($du) ?></strong></article>
<article><span class="metric-icon paid"><i class="bi bi-check2-circle"></i></span><small>Montant payé</small><strong><?= cpPublicMoney($paye) ?></strong></article>
<article><span class="metric-icon balance"><i class="bi bi-hourglass-split"></i></span><small>Solde restant</small><strong><?= cpPublicMoney($solde) ?></strong></article>
<article><span class="metric-icon payments"><i class="bi bi-arrow-left-right"></i></span><small>Paiements enregistrés</small><strong><?= (int)$note['nombre_paiements'] ?></strong></article>
</div>

<div class="row g-4">
<div class="col-lg-8">
<article class="np-public-detail-card">
<div class="detail-card-head"><div><small>CYCLE FISCAL</small><h3>Situation de la note</h3></div><span class="secure-chip"><i class="bi bi-lock-fill"></i> Lecture publique</span></div>
<div class="np-detail-grid">
<div><small>Note de Taxation</small><strong><?= cpPublicE($note['numero_nt']) ?></strong></div>
<div><small>Note de Débit</small><strong><?= cpPublicE($note['numero_nd']) ?></strong></div>
<div><small>Date d’émission</small><strong><?= cpPublicDate($note['date_emission']) ?></strong></div>
<div><small>Échéance</small><strong><?= cpPublicDate($note['date_echeance']) ?></strong></div>
<div><small>Dernier paiement</small><strong><?= cpPublicDate($note['dernier_paiement']) ?></strong></div>
<div><small>Devise de consolidation</small><strong>CDF</strong></div>
</div>
</article>
</div>
<div class="col-lg-4">
<article class="recovery-gauge-card">
<div class="gauge-ring" style="--progress:<?= max(0,min(100,$taux)) ?>;">
<div><strong><?= number_format($taux,1,',',' ') ?>%</strong><small>recouvré</small></div>
</div>
<p>Ce taux compare les paiements validés au montant dû de la note.</p>
<?php if ($solde > 0): ?>
<a href="paiement_en_ligne.php?numero=<?= urlencode($note['numero_np']) ?>" class="tool-primary-action"><i class="bi bi-credit-card"></i> Payer cette note</a>
<?php else: ?>
<span class="tool-sold-note"><i class="bi bi-patch-check-fill"></i> Note soldée</span>
<?php endif; ?>
</article>
</div>
</div>
<?php elseif ($numero===''): ?>
<div class="tool-empty-launch">
<div class="empty-launch-icon"><i class="bi bi-receipt-cutoff"></i></div>
<h2>Entrez votre numéro de NP / NPF</h2>
<p>La plateforme affichera uniquement la situation financière et documentaire nécessaire à la consultation publique.</p>
</div>
<?php endif; ?>
</div>
</section>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/public_interactive.js"></script>
</body>
</html>
