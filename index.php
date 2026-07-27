<?php
/*
|--------------------------------------------------------------------------
| cOllect_Pay - Vitrine publique premium
|--------------------------------------------------------------------------
| Mise à jour : design public modernisé et ajout du paiement en ligne.
| Les liens historiques du projet sont conservés sans modification.
|--------------------------------------------------------------------------
*/

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

$databaseFile = __DIR__ . '/config/database.php';
if (is_file($databaseFile)) {
    require_once $databaseFile;
}

function publicDb(): ?PDO
{
    global $pdo;
    return isset($pdo) && $pdo instanceof PDO ? $pdo : null;
}

function safePublic($value): string
{
    return htmlspecialchars((string) ($value ?? '-'), ENT_QUOTES, 'UTF-8');
}

function moneyPublic($amount, string $currency = 'CDF'): string
{
    $decimals = strtoupper($currency) === 'USD' ? 2 : 0;
    return number_format((float) $amount, $decimals, ',', ' ') . ' ' . strtoupper($currency);
}

function fetchOnePublic(?PDO $db, string $sql, $default = 0)
{
    if (!$db) {
        return $default;
    }

    try {
        $statement = $db->query($sql);
        $row = $statement ? $statement->fetch(PDO::FETCH_NUM) : false;
        return $row ? ($row[0] ?? $default) : $default;
    } catch (Throwable $exception) {
        error_log('Vitrine cOllect_Pay : ' . $exception->getMessage());
        return $default;
    }
}

function fetchAllPublic(?PDO $db, string $sql): array
{
    if (!$db) {
        return [];
    }

    try {
        $statement = $db->query($sql);
        return $statement ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (Throwable $exception) {
        error_log('Vitrine cOllect_Pay : ' . $exception->getMessage());
        return [];
    }
}

$db = publicDb();

$totalSemaine = fetchOnePublic($db, "
    SELECT IFNULL(SUM(montant_converti_cdf), 0)
    FROM paiements
    WHERE YEARWEEK(COALESCE(date_paiement, created_at), 1) = YEARWEEK(CURDATE(), 1)
");

$totalMois = fetchOnePublic($db, "
    SELECT IFNULL(SUM(montant_converti_cdf), 0)
    FROM paiements
    WHERE MONTH(COALESCE(date_paiement, created_at)) = MONTH(CURDATE())
      AND YEAR(COALESCE(date_paiement, created_at)) = YEAR(CURDATE())
");

$totalAnnee = fetchOnePublic($db, "
    SELECT IFNULL(SUM(montant_converti_cdf), 0)
    FROM paiements
    WHERE YEAR(COALESCE(date_paiement, created_at)) = YEAR(CURDATE())
");

$totalNT = fetchOnePublic($db, 'SELECT COUNT(*) FROM notes_taxation');
$totalNP = fetchOnePublic($db, 'SELECT COUNT(*) FROM notes_perception');
$totalPaiements = fetchOnePublic($db, 'SELECT COUNT(*) FROM paiements');
$totalQuittances = fetchOnePublic($db, 'SELECT COUNT(*) FROM quittances');
$totalConstatation = fetchOnePublic($db, 'SELECT IFNULL(SUM(total_estime), 0) FROM notes_taxation');
$totalOrdonnance = fetchOnePublic($db, 'SELECT IFNULL(SUM(montant_initial), 0) FROM notes_perception');
$totalRecouvre = fetchOnePublic($db, 'SELECT IFNULL(SUM(montant_converti_cdf), 0) FROM paiements');
$totalSolde = fetchOnePublic($db, "SELECT IFNULL(SUM(solde_restant), 0) FROM notes_perception WHERE statut <> 'payee'");
$tauxRecouvrement = (float) $totalOrdonnance > 0
    ? ((float) $totalRecouvre / (float) $totalOrdonnance) * 100
    : 0;

$npDefaillantes = fetchAllPublic($db, "
    SELECT numero_np, type_np, montant_initial, solde_restant, date_echeance
    FROM notes_perception
    WHERE statut <> 'payee'
      AND date_echeance IS NOT NULL
      AND date_echeance < CURDATE()
    ORDER BY date_echeance ASC
    LIMIT 5
");

$notesPayees = fetchAllPublic($db, "
    SELECT np.numero_np, q.numero_quittance, q.montant_acquitte, q.date_emission
    FROM quittances q
    JOIN apurements a ON q.apurement_id = a.id
    JOIN notes_perception np ON a.reference_id = np.id
    ORDER BY q.date_emission DESC
    LIMIT 5
");

$notesNonPayees = fetchAllPublic($db, "
    SELECT numero_np, type_np, montant_initial, solde_restant, date_echeance
    FROM notes_perception
    WHERE statut <> 'payee'
    ORDER BY created_at DESC
    LIMIT 5
");

$derniersPaiements = fetchAllPublic($db, "
    SELECT p.reference_transaction,
           p.montant_converti_cdf,
           p.devise,
           COALESCE(p.date_paiement, p.created_at) AS date_paiement,
           np.numero_np
    FROM paiements p
    LEFT JOIN notes_perception np ON p.note_perception_id = np.id
    ORDER BY p.created_at DESC
    LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="cOllect_Pay, guichet digital sécurisé de mobilisation et de paiement des recettes publiques.">
    <title>cOllect_Pay | Recettes publiques digitales</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/public.css" rel="stylesheet">
</head>
<body>
<?php if (!$db): ?>
<div class="database-alert">
    <i class="bi bi-exclamation-triangle-fill"></i>
    Connexion à la base indisponible : les statistiques sont temporairement affichées à zéro.
</div>
<?php endif; ?>

<nav class="navbar navbar-expand-lg navbar-dark fixed-top premium-nav" id="mainNav">
    <div class="container">
        <a class="navbar-brand" href="#accueil" aria-label="Accueil cOllect_Pay">
            <span class="brand-mark"><i class="bi bi-shield-check"></i></span>
            <span class="brand-copy">
                <strong>cOllect_Pay</strong>
                <small>LUXORIA PUBLIC REVENUE SUITE</small>
            </span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#publicNav" aria-controls="publicNav" aria-expanded="false" aria-label="Afficher le menu">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="publicNav">
            <ul class="navbar-nav mx-auto public-menu">
                <li class="nav-item"><a class="nav-link" href="#situation">Situation</a></li>
                <li class="nav-item"><a class="nav-link" href="#processus">Processus</a></li>
                <li class="nav-item"><a class="nav-link" href="#transparence">Transparence</a></li>
            </ul>

            <div class="nav-actions">
                <a href="modules/inspection/scan_qr.php" class="btn btn-nav-outline">
                    <i class="bi bi-qr-code-scan"></i> Vérifier QR
                </a>
                <a href="modules/ordonnancement/np_list.php" class="btn btn-nav-soft">
                    Consulter NP
                </a>
                <a href="login.php" class="btn btn-nav-gold">
                    <i class="bi bi-person-lock"></i> Connexion
                </a>
            </div>
        </div>
    </div>
</nav>

<main>
<section class="hero" id="accueil">
    <div class="hero-grid"></div>
    <div class="hero-orb hero-orb-one"></div>
    <div class="hero-orb hero-orb-two"></div>

    <div class="container position-relative">
        <div class="row align-items-center g-5">
            <div class="col-lg-7">
                <div class="hero-eyebrow reveal-up">
                    <span class="pulse-dot"></span>
                    Guichet fiscal sécurisé, traçable et vérifiable
                </div>

                <h1 class="reveal-up delay-1">
                    La recette publique devient
                    <span>simple, transparente et sécurisée.</span>
                </h1>

                <p class="hero-lead reveal-up delay-2">
                    cOllect_Pay digitalise toute la chaîne de mobilisation : constatation,
                    liquidation, ordonnancement, paiement, apurement, quittance et contrôle QR anti-fraude.
                </p>

                <div class="hero-actions reveal-up delay-3">
                    <a href="paiement_en_ligne.php" class="btn btn-hero-primary">
                        <i class="bi bi-credit-card-2-front"></i>
                        Payer une NP / NPF
                    </a>
                    <a href="modules/inspection/scan_qr.php" class="btn btn-hero-secondary">
                        <i class="bi bi-patch-check"></i>
                        Vérifier un document
                    </a>
                    <a href="login.php" class="btn btn-hero-ghost">
                        Accéder au Guichet Unique
                    </a>
                </div>

                <div class="trust-row reveal-up delay-4">
                    <div><i class="bi bi-shield-lock"></i><span>Données sécurisées</span></div>
                    <div><i class="bi bi-currency-exchange"></i><span>CDF et USD</span></div>
                    <div><i class="bi bi-phone"></i><span>Mobile Money</span></div>
                </div>
            </div>

            <div class="col-lg-5">
                <aside class="revenue-panel reveal-right">
                    <div class="panel-heading">
                        <div>
                            <small>TABLEAU PUBLIC</small>
                            <h2>Recettes réalisées</h2>
                        </div>
                        <span class="live-badge"><span></span> En direct</span>
                    </div>

                    <div class="revenue-total">
                        <small>Recettes de l’année</small>
                        <strong><?= moneyPublic($totalAnnee) ?></strong>
                    </div>

                    <div class="revenue-stats">
                        <div>
                            <span>Semaine</span>
                            <strong><?= moneyPublic($totalSemaine) ?></strong>
                        </div>
                        <div>
                            <span>Mois</span>
                            <strong><?= moneyPublic($totalMois) ?></strong>
                        </div>
                    </div>

                    <div class="recovery-block">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>Taux de recouvrement</span>
                            <strong><?= number_format($tauxRecouvrement, 2, ',', ' ') ?> %</strong>
                        </div>
                        <div class="progress" role="progressbar" aria-label="Taux de recouvrement" aria-valuenow="<?= min(100, max(0, $tauxRecouvrement)) ?>" aria-valuemin="0" aria-valuemax="100">
                            <div class="progress-bar" style="width:<?= min(100, max(0, $tauxRecouvrement)) ?>%"></div>
                        </div>
                    </div>

                    <a href="paiement_en_ligne.php" class="panel-pay-link">
                        Commencer un paiement
                        <i class="bi bi-arrow-up-right"></i>
                    </a>
                </aside>
            </div>
        </div>
    </div>
</section>

<section class="kpi-strip" id="situation">
    <div class="container">
        <div class="row g-3">
            <div class="col-6 col-lg-3">
                <article class="kpi-card">
                    <span class="kpi-icon blue"><i class="bi bi-file-earmark-text"></i></span>
                    <div><small>Notes de taxation</small><strong><?= (int) $totalNT ?></strong></div>
                </article>
            </div>
            <div class="col-6 col-lg-3">
                <article class="kpi-card">
                    <span class="kpi-icon violet"><i class="bi bi-receipt"></i></span>
                    <div><small>Notes de perception</small><strong><?= (int) $totalNP ?></strong></div>
                </article>
            </div>
            <div class="col-6 col-lg-3">
                <article class="kpi-card">
                    <span class="kpi-icon green"><i class="bi bi-wallet2"></i></span>
                    <div><small>Paiements enregistrés</small><strong><?= (int) $totalPaiements ?></strong></div>
                </article>
            </div>
            <div class="col-6 col-lg-3">
                <article class="kpi-card">
                    <span class="kpi-icon gold"><i class="bi bi-patch-check"></i></span>
                    <div><small>Quittances émises</small><strong><?= (int) $totalQuittances ?></strong></div>
                </article>
            </div>
        </div>
    </div>
</section>

<section class="section section-light">
    <div class="container">
        <div class="section-heading centered">
            <span class="section-kicker">TRANSPARENCE FINANCIÈRE</span>
            <h2>Situation synthétique des recettes</h2>
            <p>Une lecture claire des montants constatés, ordonnancés, recouvrés et restant à recouvrer.</p>
        </div>

        <div class="row g-4">
            <?php
            $summaryCards = [
                ['label' => 'Constaté', 'value' => moneyPublic($totalConstatation), 'description' => 'Montants issus des notes de taxation.', 'icon' => 'bi-search', 'tone' => 'blue'],
                ['label' => 'Ordonnancé', 'value' => moneyPublic($totalOrdonnance), 'description' => 'Montants établis sur les NP et NPF.', 'icon' => 'bi-file-earmark-check', 'tone' => 'violet'],
                ['label' => 'Recouvré', 'value' => moneyPublic($totalRecouvre), 'description' => 'Paiements validés et convertis en CDF.', 'icon' => 'bi-graph-up-arrow', 'tone' => 'green'],
                ['label' => 'À recouvrer', 'value' => moneyPublic($totalSolde), 'description' => 'Solde restant sur les notes non soldées.', 'icon' => 'bi-hourglass-split', 'tone' => 'gold'],
            ];
            foreach ($summaryCards as $card):
            ?>
            <div class="col-md-6 col-xl-3">
                <article class="summary-card reveal-card">
                    <span class="summary-icon <?= safePublic($card['tone']) ?>"><i class="bi <?= safePublic($card['icon']) ?>"></i></span>
                    <small><?= safePublic($card['label']) ?></small>
                    <h3><?= safePublic($card['value']) ?></h3>
                    <p><?= safePublic($card['description']) ?></p>
                </article>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="online-payment-banner">
    <div class="container">
        <div class="payment-banner-inner">
            <div class="payment-banner-copy">
                <span class="section-kicker light">NOUVEAU SERVICE</span>
                <h2>Payez votre NP ou NPF en ligne</h2>
                <p>
                    Saisissez le numéro de la note, vérifiez le solde puis choisissez votre mode de paiement :
                    espèces au guichet, carte bancaire, virement ou Mobile Money.
                </p>
                <div class="payment-chips">
                    <span><i class="bi bi-cash-coin"></i> Espèces</span>
                    <span><i class="bi bi-credit-card"></i> Carte</span>
                    <span><i class="bi bi-bank"></i> Banque</span>
                    <span><i class="bi bi-phone"></i> Mobile Money</span>
                    <span><i class="bi bi-currency-exchange"></i> CDF / USD</span>
                </div>
            </div>
            <div class="payment-banner-action">
                <div class="secure-seal"><i class="bi bi-shield-check"></i></div>
                <a href="paiement_en_ligne.php" class="btn btn-payment-banner">
                    Rechercher ma NP / NPF
                    <i class="bi bi-arrow-right"></i>
                </a>
                <small>Consultation sécurisée avant toute opération.</small>
            </div>
        </div>
    </div>
</section>

<section class="section workflow-section" id="processus">
    <div class="container">
        <div class="section-heading centered">
            <span class="section-kicker">TRAITEMENT OFFICIEL</span>
            <h2>Une chaîne complète, de la taxation à la quittance</h2>
            <p>Chaque étape est journalisée, sécurisée et vérifiable par QR Code.</p>
        </div>

        <div class="workflow-line">
            <?php
            $steps = [
                ['NT', 'Constatation', 'Détermination de l’assiette.', 'bi-clipboard-data'],
                ['ND', 'Liquidation', 'Calcul officiel du montant.', 'bi-calculator'],
                ['NP / NPF', 'Ordonnancement', 'Émission de l’ordre de paiement.', 'bi-file-earmark-text'],
                ['Paiement', 'Encaissement', 'Banque, carte, cash ou mobile.', 'bi-wallet2'],
                ['Apurement', 'Validation', 'Mise à jour du solde.', 'bi-check2-circle'],
                ['Quittance', 'Acquit libératoire', 'Document final sécurisé.', 'bi-patch-check'],
            ];
            foreach ($steps as $index => $step):
            ?>
            <article class="workflow-step reveal-card">
                <div class="step-top">
                    <span class="step-number"><?= $index + 1 ?></span>
                    <i class="bi <?= safePublic($step[3]) ?>"></i>
                </div>
                <small><?= safePublic($step[0]) ?></small>
                <h3><?= safePublic($step[1]) ?></h3>
                <p><?= safePublic($step[2]) ?></p>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section transparency-section" id="transparence">
    <div class="container">
        <div class="section-heading split-heading">
            <div>
                <span class="section-kicker">DONNÉES PUBLIQUES</span>
                <h2>Suivi des notes et quittances</h2>
            </div>
            <p>Les dernières informations disponibles sont présentées sans exposer les données sensibles des contribuables.</p>
        </div>

        <div class="row g-4">
            <div class="col-lg-4">
                <article class="public-list-card danger-card">
                    <div class="list-card-header">
                        <div><span class="list-icon"><i class="bi bi-calendar-x"></i></span><h3>NP / NPF échues</h3></div>
                        <span class="list-count"><?= count($npDefaillantes) ?></span>
                    </div>
                    <div class="public-items">
                    <?php foreach ($npDefaillantes as $np): ?>
                        <div class="public-item">
                            <div class="item-top"><span class="status-pill danger"><?= strtoupper(safePublic($np['type_np'])) ?></span><small><?= safePublic($np['date_echeance']) ?></small></div>
                            <strong><?= safePublic($np['numero_np']) ?></strong>
                            <span>Solde : <?= moneyPublic($np['solde_restant']) ?></span>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$npDefaillantes): ?><p class="empty-state">Aucune note échue pour le moment.</p><?php endif; ?>
                    </div>
                </article>
            </div>

            <div class="col-lg-4">
                <article class="public-list-card success-card">
                    <div class="list-card-header">
                        <div><span class="list-icon"><i class="bi bi-patch-check"></i></span><h3>Dernières quittances</h3></div>
                        <span class="list-count"><?= count($notesPayees) ?></span>
                    </div>
                    <div class="public-items">
                    <?php foreach ($notesPayees as $note): ?>
                        <div class="public-item">
                            <div class="item-top"><span class="status-pill success">PAYÉE</span><small><?= !empty($note['date_emission']) ? safePublic(date('d/m/Y', strtotime($note['date_emission']))) : '-' ?></small></div>
                            <strong><?= safePublic($note['numero_np']) ?></strong>
                            <span><?= safePublic($note['numero_quittance']) ?> · <?= moneyPublic($note['montant_acquitte']) ?></span>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$notesPayees): ?><p class="empty-state">Aucune quittance disponible.</p><?php endif; ?>
                    </div>
                </article>
            </div>

            <div class="col-lg-4">
                <article class="public-list-card warning-card">
                    <div class="list-card-header">
                        <div><span class="list-icon"><i class="bi bi-hourglass-split"></i></span><h3>Notes non soldées</h3></div>
                        <span class="list-count"><?= count($notesNonPayees) ?></span>
                    </div>
                    <div class="public-items">
                    <?php foreach ($notesNonPayees as $note): ?>
                        <div class="public-item">
                            <div class="item-top"><span class="status-pill warning"><?= strtoupper(safePublic($note['type_np'])) ?></span><small><?= safePublic($note['date_echeance']) ?></small></div>
                            <strong><?= safePublic($note['numero_np']) ?></strong>
                            <span>Solde : <?= moneyPublic($note['solde_restant']) ?></span>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$notesNonPayees): ?><p class="empty-state">Toutes les notes sont soldées.</p><?php endif; ?>
                    </div>
                </article>
            </div>
        </div>
    </div>
</section>

<section class="section payments-table-section">
    <div class="container">
        <article class="payments-table-card">
            <div class="table-card-heading">
                <div>
                    <span class="section-kicker">TRANSACTIONS RÉCENTES</span>
                    <h2>Derniers paiements enregistrés</h2>
                </div>
                <a href="modules/ordonnancement/np_list.php">Consulter les NP <i class="bi bi-arrow-right"></i></a>
            </div>

            <div class="table-responsive">
                <table class="public-table">
                    <thead><tr><th>Date</th><th>NP / NPF</th><th>Référence</th><th>Devise</th><th class="text-end">Montant CDF</th></tr></thead>
                    <tbody>
                    <?php foreach ($derniersPaiements as $payment): ?>
                        <tr>
                            <td><?= !empty($payment['date_paiement']) ? safePublic(date('d/m/Y', strtotime($payment['date_paiement']))) : '-' ?></td>
                            <td><strong><?= safePublic($payment['numero_np']) ?></strong></td>
                            <td><span class="reference-tag"><?= safePublic($payment['reference_transaction']) ?></span></td>
                            <td><?= safePublic($payment['devise']) ?></td>
                            <td class="text-end"><strong><?= moneyPublic($payment['montant_converti_cdf']) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$derniersPaiements): ?><tr><td colspan="5" class="empty-table">Aucun paiement enregistré.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </article>
    </div>
</section>

<section class="features-section">
    <div class="container">
        <div class="section-heading centered dark-heading">
            <span class="section-kicker light">CONFIANCE NUMÉRIQUE</span>
            <h2>Conçu pour protéger chaque franc mobilisé</h2>
            <p>Des outils orientés sécurité, traçabilité, transparence et performance.</p>
        </div>
        <div class="row g-4">
            <?php
            $features = [
                ['bi-qr-code-scan', 'QR Code sécurisé', 'Vérification instantanée de l’authenticité des documents.'],
                ['bi-currency-exchange', 'Paiement multi-devise', 'Opérations en CDF et USD selon les règles configurées.'],
                ['bi-arrow-repeat', 'Apurement automatique', 'Mise à jour contrôlée du solde, du statut et de la quittance.'],
                ['bi-fingerprint', 'Audit anti-fraude', 'Traçabilité des actions et inspection des documents.'],
            ];
            foreach ($features as $feature):
            ?>
            <div class="col-md-6 col-xl-3">
                <article class="feature-card reveal-card">
                    <span><i class="bi <?= safePublic($feature[0]) ?>"></i></span>
                    <h3><?= safePublic($feature[1]) ?></h3>
                    <p><?= safePublic($feature[2]) ?></p>
                </article>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="final-cta">
    <div class="container">
        <div class="final-cta-inner">
            <div>
                <span class="section-kicker">GUICHET UNIQUE DIGITAL</span>
                <h2>Centralisez le cycle complet des recettes publiques</h2>
                <p>Créez les documents, suivez les paiements, apurez les notes et produisez des quittances sécurisées.</p>
            </div>
            <div class="final-cta-actions">
                <a href="paiement_en_ligne.php" class="btn btn-dark-action"><i class="bi bi-credit-card"></i> Paiement en ligne</a>
                <a href="login.php" class="btn btn-gold-action">Connexion au système <i class="bi bi-arrow-right"></i></a>
            </div>
        </div>
    </div>
</section>
</main>

<footer class="public-footer">
    <div class="container">
        <div class="footer-inner">
            <div class="footer-brand">
                <span class="brand-mark"><i class="bi bi-shield-check"></i></span>
                <div><strong>cOllect_Pay</strong><small>Système digital des recettes publiques</small></div>
            </div>
            <p>© <?= date('Y') ?> cOllect_Pay. Tous droits réservés.</p>
            <div class="footer-links">
                <a href="modules/inspection/scan_qr.php">Vérifier QR</a>
                <a href="modules/ordonnancement/np_list.php">Consulter NP</a>
                <a href="login.php">Connexion</a>
            </div>
        </div>
    </div>
</footer>

<?php require_once __DIR__ . '/verification_widget.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/public.js"></script>
</body>
</html>
