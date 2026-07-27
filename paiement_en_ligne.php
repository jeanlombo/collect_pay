<?php
/*
|--------------------------------------------------------------------------
| cOllect_Pay - Paiement public NP / NPF
|--------------------------------------------------------------------------
| Cette page prépare et enregistre une demande de paiement.
| Elle ne stocke jamais un numéro complet de carte ni un cryptogramme CVV.
| Le débit réel nécessite la connexion à une passerelle bancaire/mobile.
|--------------------------------------------------------------------------
*/

declare(strict_types=1);
ini_set('display_errors', '0');
error_reporting(E_ALL);
session_start();

$databaseFile = __DIR__ . '/config/database.php';
if (is_file($databaseFile)) {
    require_once $databaseFile;
}

function paymentDb(): ?PDO
{
    global $pdo;
    return isset($pdo) && $pdo instanceof PDO ? $pdo : null;
}

function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function paymentMoney($amount, string $currency = 'CDF'): string
{
    return number_format((float) $amount, strtoupper($currency) === 'USD' ? 2 : 0, ',', ' ') . ' ' . strtoupper($currency);
}

function requestReference(): string
{
    return 'WEB-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

$db = paymentDb();
$errors = [];
$success = null;
$note = null;
$searchNumber = trim((string) ($_GET['numero'] ?? $_POST['numero_np'] ?? ''));

if (!isset($_SESSION['payment_csrf'])) {
    $_SESSION['payment_csrf'] = bin2hex(random_bytes(32));
}

if ($db) {
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS demandes_paiement_public (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            note_perception_id BIGINT UNSIGNED NOT NULL,
            numero_np VARCHAR(120) NOT NULL,
            reference_demande VARCHAR(80) NOT NULL UNIQUE,
            mode_paiement ENUM('cash','carte','banque','mobile_money') NOT NULL,
            operateur_mobile VARCHAR(40) NULL,
            nom_payeur VARCHAR(160) NOT NULL,
            telephone VARCHAR(40) NULL,
            email VARCHAR(190) NULL,
            devise ENUM('CDF','USD') NOT NULL DEFAULT 'CDF',
            montant DECIMAL(18,2) NOT NULL,
            banque VARCHAR(120) NULL,
            compte_masque VARCHAR(80) NULL,
            reference_externe VARCHAR(160) NULL,
            carte_quatre_derniers VARCHAR(4) NULL,
            statut ENUM('en_attente','confirmee','echouee','annulee') NOT NULL DEFAULT 'en_attente',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_numero_np (numero_np),
            INDEX idx_statut (statut)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $exception) {
        error_log('Création demandes_paiement_public : ' . $exception->getMessage());
    }
}

if ($searchNumber !== '' && $db) {
    try {
        $statement = $db->prepare("SELECT id, numero_np, type_np, montant_initial, solde_restant, date_echeance, statut
                                   FROM notes_perception
                                   WHERE UPPER(numero_np) = UPPER(:numero)
                                   LIMIT 1");
        $statement->execute(['numero' => $searchNumber]);
        $note = $statement->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$note) {
            $errors[] = 'Aucune NP ou NPF ne correspond à ce numéro.';
        }
    } catch (Throwable $exception) {
        $errors[] = 'La recherche est momentanément indisponible.';
        error_log('Recherche NP publique : ' . $exception->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_payment'])) {
    $token = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals($_SESSION['payment_csrf'], $token)) {
        $errors[] = 'La session du formulaire a expiré. Rechargez la page.';
    }

    $noteId = filter_input(INPUT_POST, 'note_id', FILTER_VALIDATE_INT);
    $mode = (string) ($_POST['mode_paiement'] ?? '');
    $nomPayeur = trim((string) ($_POST['nom_payeur'] ?? ''));
    $telephone = trim((string) ($_POST['telephone'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $devise = strtoupper((string) ($_POST['devise'] ?? 'CDF'));
    $montant = (float) str_replace(',', '.', (string) ($_POST['montant'] ?? '0'));
    $operateur = trim((string) ($_POST['operateur_mobile'] ?? ''));
    $banque = trim((string) ($_POST['banque'] ?? ''));
    $compteMasque = trim((string) ($_POST['compte_masque'] ?? ''));
    $referenceExterne = trim((string) ($_POST['reference_externe'] ?? ''));
    $cardLast4 = preg_replace('/\D/', '', (string) ($_POST['carte_quatre_derniers'] ?? ''));

    if (!$db) {
        $errors[] = 'La base de données est indisponible.';
    }
    if (!$noteId || !$note || (int) $note['id'] !== (int) $noteId) {
        $errors[] = 'La note sélectionnée est invalide.';
    }
    if (!in_array($mode, ['cash', 'carte', 'banque', 'mobile_money'], true)) {
        $errors[] = 'Sélectionnez un moyen de paiement.';
    }
    if ($nomPayeur === '') {
        $errors[] = 'Le nom du payeur est obligatoire.';
    }
    if (!in_array($devise, ['CDF', 'USD'], true)) {
        $errors[] = 'La devise sélectionnée est invalide.';
    }
    if ($montant <= 0) {
        $errors[] = 'Le montant doit être supérieur à zéro.';
    }
    if ($note && $devise === 'CDF' && $montant > (float) $note['solde_restant']) {
        $errors[] = 'Le montant ne peut pas dépasser le solde restant de la note.';
    }
    if ($mode === 'mobile_money' && ($telephone === '' || $operateur === '')) {
        $errors[] = 'Le numéro de téléphone et l’opérateur Mobile Money sont obligatoires.';
    }
    if ($mode === 'banque' && ($banque === '' || $referenceExterne === '')) {
        $errors[] = 'La banque et la référence du virement sont obligatoires.';
    }
    if ($mode === 'carte' && strlen($cardLast4) !== 4) {
        $errors[] = 'Indiquez uniquement les 4 derniers chiffres de la carte.';
    }

    if (!$errors && $db && $note) {
        try {
            $reference = requestReference();
            $statement = $db->prepare("INSERT INTO demandes_paiement_public
                (note_perception_id, numero_np, reference_demande, mode_paiement, operateur_mobile,
                 nom_payeur, telephone, email, devise, montant, banque, compte_masque,
                 reference_externe, carte_quatre_derniers, statut)
                VALUES
                (:note_id, :numero_np, :reference_demande, :mode_paiement, :operateur_mobile,
                 :nom_payeur, :telephone, :email, :devise, :montant, :banque, :compte_masque,
                 :reference_externe, :carte_quatre_derniers, 'en_attente')");
            $statement->execute([
                'note_id' => (int) $note['id'],
                'numero_np' => $note['numero_np'],
                'reference_demande' => $reference,
                'mode_paiement' => $mode,
                'operateur_mobile' => $operateur ?: null,
                'nom_payeur' => $nomPayeur,
                'telephone' => $telephone ?: null,
                'email' => $email ?: null,
                'devise' => $devise,
                'montant' => $montant,
                'banque' => $banque ?: null,
                'compte_masque' => $compteMasque ?: null,
                'reference_externe' => $referenceExterne ?: null,
                'carte_quatre_derniers' => $cardLast4 ?: null,
            ]);
            $success = [
                'reference' => $reference,
                'mode' => $mode,
                'montant' => $montant,
                'devise' => $devise,
            ];
            $_SESSION['payment_csrf'] = bin2hex(random_bytes(32));
        } catch (Throwable $exception) {
            $errors[] = 'La demande n’a pas pu être enregistrée. Réessayez.';
            error_log('Demande paiement public : ' . $exception->getMessage());
        }
    }
}

$notePayable = $note && strtolower((string) $note['statut']) !== 'payee' && (float) $note['solde_restant'] > 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Paiement NP / NPF | cOllect_Pay</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/public.css" rel="stylesheet">
</head>
<body class="payment-page">
<nav class="navbar navbar-dark premium-nav payment-nav">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <span class="brand-mark"><i class="bi bi-shield-check"></i></span>
            <span class="brand-copy"><strong>cOllect_Pay</strong><small>PAIEMENT PUBLIC SÉCURISÉ</small></span>
        </a>
        <a href="index.php" class="btn btn-nav-outline"><i class="bi bi-arrow-left"></i> Retour à la vitrine</a>
    </div>
</nav>

<main class="payment-main">
    <section class="payment-hero">
        <div class="container">
            <div class="row align-items-center g-4">
                <div class="col-lg-7">
                    <span class="payment-eyebrow"><i class="bi bi-lock-fill"></i> Consultation sécurisée</span>
                    <h1>Rechercher et payer une NP ou NPF</h1>
                    <p>Entrez le numéro exact de la note pour vérifier son état, son échéance et son solde avant de choisir le moyen de paiement.</p>
                </div>
                <div class="col-lg-5">
                    <form class="np-search-card" method="get" action="paiement_en_ligne.php">
                        <label for="numero">Numéro de la NP / NPF</label>
                        <div class="search-input-wrap">
                            <i class="bi bi-search"></i>
                            <input id="numero" name="numero" value="<?= e($searchNumber) ?>" placeholder="Ex. NP-BU-CPR-26-000017" autocomplete="off" required>
                            <button type="submit">Rechercher</button>
                        </div>
                        <small>Le numéro est vérifié directement dans le registre cOllect_Pay.</small>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <section class="payment-content">
        <div class="container">
            <?php foreach ($errors as $error): ?>
                <div class="alert alert-danger payment-alert"><i class="bi bi-exclamation-circle"></i> <?= e($error) ?></div>
            <?php endforeach; ?>

            <?php if ($success): ?>
                <div class="payment-success-card">
                    <div class="success-check"><i class="bi bi-check2"></i></div>
                    <div>
                        <span>Demande enregistrée</span>
                        <h2>Référence : <?= e($success['reference']) ?></h2>
                        <p><?= paymentMoney($success['montant'], $success['devise']) ?> · statut en attente de confirmation.</p>
                        <small>Le paiement réel sera confirmé après validation de la banque, de l’opérateur ou du guichet.</small>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($note): ?>
            <div class="row g-4">
                <div class="col-lg-5">
                    <article class="note-preview-card">
                        <div class="note-preview-head">
                            <div><small>DOCUMENT RETROUVÉ</small><h2><?= e($note['numero_np']) ?></h2></div>
                            <span class="note-type"><?= strtoupper(e($note['type_np'])) ?></span>
                        </div>
                        <div class="note-status <?= $notePayable ? 'payable' : 'closed' ?>">
                            <i class="bi <?= $notePayable ? 'bi-check-circle' : 'bi-lock' ?>"></i>
                            <?= $notePayable ? 'Note disponible pour paiement' : 'Note soldée ou non payable' ?>
                        </div>
                        <dl class="note-details">
                            <div><dt>Montant initial</dt><dd><?= paymentMoney($note['montant_initial']) ?></dd></div>
                            <div class="emphasis"><dt>Solde restant</dt><dd><?= paymentMoney($note['solde_restant']) ?></dd></div>
                            <div><dt>Échéance</dt><dd><?= e($note['date_echeance'] ?: 'Non définie') ?></dd></div>
                            <div><dt>Statut</dt><dd><?= strtoupper(e($note['statut'])) ?></dd></div>
                        </dl>
                        <div class="note-security"><i class="bi bi-shield-check"></i><span>Les données sensibles du contribuable ne sont pas exposées sur cette page publique.</span></div>
                    </article>
                </div>

                <div class="col-lg-7">
                <?php if ($notePayable): ?>
                    <form method="post" action="paiement_en_ligne.php?numero=<?= urlencode((string) $note['numero_np']) ?>" class="payment-form-card" id="paymentForm">
                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['payment_csrf']) ?>">
                        <input type="hidden" name="note_id" value="<?= (int) $note['id'] ?>">
                        <input type="hidden" name="numero_np" value="<?= e($note['numero_np']) ?>">

                        <div class="form-heading"><div><small>ÉTAPE 1</small><h2>Choisissez le moyen de paiement</h2></div><i class="bi bi-credit-card-2-front"></i></div>

                        <div class="payment-method-grid">
                            <label class="method-option"><input type="radio" name="mode_paiement" value="mobile_money" checked><span><i class="bi bi-phone"></i><strong>Mobile Money</strong><small>M-Pesa, Airtel, Orange, Afrimoney</small></span></label>
                            <label class="method-option"><input type="radio" name="mode_paiement" value="carte"><span><i class="bi bi-credit-card"></i><strong>Carte bancaire</strong><small>Visa, Mastercard</small></span></label>
                            <label class="method-option"><input type="radio" name="mode_paiement" value="banque"><span><i class="bi bi-bank"></i><strong>Compte bancaire</strong><small>Virement ou dépôt</small></span></label>
                            <label class="method-option"><input type="radio" name="mode_paiement" value="cash"><span><i class="bi bi-cash-coin"></i><strong>Cash</strong><small>Référence de guichet</small></span></label>
                        </div>

                        <div class="form-section">
                            <div class="form-section-title"><span>2</span><h3>Informations du payeur</h3></div>
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label">Nom complet *</label><input class="form-control" name="nom_payeur" required></div>
                                <div class="col-md-6"><label class="form-label">Téléphone</label><input class="form-control" name="telephone" placeholder="+243 ..."></div>
                                <div class="col-md-6"><label class="form-label">Adresse e-mail</label><input class="form-control" type="email" name="email" placeholder="nom@exemple.com"></div>
                                <div class="col-6 col-md-3"><label class="form-label">Devise *</label><select class="form-select" name="devise" id="currency"><option value="CDF">CDF</option><option value="USD">USD</option></select></div>
                                <div class="col-6 col-md-3"><label class="form-label">Montant *</label><input class="form-control" type="number" name="montant" min="0.01" step="0.01" value="<?= e($note['solde_restant']) ?>" required></div>
                            </div>
                        </div>

                        <div class="form-section method-panel" data-method="mobile_money">
                            <div class="form-section-title"><span>3</span><h3>Informations Mobile Money</h3></div>
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label">Opérateur *</label><select class="form-select" name="operateur_mobile"><option value="">Sélectionner</option><option>M-Pesa</option><option>Airtel Money</option><option>Orange Money</option><option>Afrimoney</option></select></div>
                                <div class="col-md-6"><label class="form-label">Référence opérateur (facultatif)</label><input class="form-control" name="reference_externe" placeholder="Après confirmation USSD"></div>
                            </div>
                            <div class="method-note"><i class="bi bi-info-circle"></i> Une intégration API opérateur est nécessaire pour déclencher automatiquement la demande USSD.</div>
                        </div>

                        <div class="form-section method-panel d-none" data-method="carte">
                            <div class="form-section-title"><span>3</span><h3>Informations de carte</h3></div>
                            <div class="row g-3">
                                <div class="col-md-7"><label class="form-label">Titulaire de la carte</label><input class="form-control" placeholder="Nom inscrit sur la carte"></div>
                                <div class="col-md-5"><label class="form-label">4 derniers chiffres *</label><input class="form-control" name="carte_quatre_derniers" maxlength="4" inputmode="numeric" placeholder="1234"></div>
                            </div>
                            <div class="method-note secure"><i class="bi bi-shield-lock"></i> Pour votre sécurité, cOllect_Pay ne collecte ni le numéro complet ni le cryptogramme CVV. Le paiement final doit être redirigé vers une passerelle certifiée.</div>
                        </div>

                        <div class="form-section method-panel d-none" data-method="banque">
                            <div class="form-section-title"><span>3</span><h3>Informations bancaires</h3></div>
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label">Banque *</label><input class="form-control" name="banque" placeholder="Nom de la banque"></div>
                                <div class="col-md-6"><label class="form-label">Compte masqué</label><input class="form-control" name="compte_masque" placeholder="Ex. **** 4589"></div>
                                <div class="col-12"><label class="form-label">Référence du virement / bordereau *</label><input class="form-control" name="reference_externe" placeholder="Référence fournie par la banque"></div>
                            </div>
                        </div>

                        <div class="form-section method-panel d-none" data-method="cash">
                            <div class="form-section-title"><span>3</span><h3>Paiement en espèces</h3></div>
                            <div class="method-note"><i class="bi bi-building"></i> Une référence de demande sera générée. Présentez-la au guichet habilité pour finaliser le paiement et obtenir la quittance.</div>
                        </div>

                        <div class="payment-consent">
                            <label><input type="checkbox" required> <span>Je confirme l’exactitude des informations et j’accepte que cette demande reste en attente jusqu’à validation du canal de paiement.</span></label>
                        </div>

                        <button type="submit" name="submit_payment" class="submit-payment-btn">
                            <span><i class="bi bi-lock-fill"></i> Effectuer le paiement</span>
                            <i class="bi bi-arrow-right"></i>
                        </button>
                    </form>
                <?php else: ?>
                    <div class="not-payable-card"><i class="bi bi-check2-circle"></i><h2>Aucun paiement requis</h2><p>Cette note est déjà soldée ou n’est plus disponible pour un paiement public.</p></div>
                <?php endif; ?>
                </div>
            </div>
            <?php elseif ($searchNumber === ''): ?>
                <div class="payment-empty-state">
                    <span><i class="bi bi-receipt"></i></span>
                    <h2>Commencez par rechercher votre note</h2>
                    <p>Le formulaire de paiement apparaîtra après vérification du numéro de la NP ou NPF.</p>
                    <div class="empty-payment-methods"><span>M-Pesa</span><span>Airtel Money</span><span>Orange Money</span><span>Carte</span><span>Banque</span><span>Cash</span></div>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<footer class="payment-footer"><div class="container"><span><i class="bi bi-shield-check"></i> cOllect_Pay</span><p>Connexion sécurisée · CDF et USD · Vérification NP / NPF</p></div></footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/public.js"></script>
</body>
</html>
