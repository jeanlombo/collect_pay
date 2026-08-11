<?php
require_once "../../config/database.php";
require_once "../../config/security.php";
require_once "../../core/functions.php";
require_once "../../core/numero_generator.php";

checkAuth();

if (!function_exists('cpRecouvrementCurrentUserId')) {
    function cpRecouvrementCurrentUserId(PDO $pdo): int
    {
        $id = (int)($_SESSION['user_id'] ?? 0);

        if ($id > 0) {
            return $id;
        }

        $email = trim((string)($_SESSION['email'] ?? $_SESSION['user_email'] ?? ''));

        if ($email !== '') {
            $stmtUser = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmtUser->execute([$email]);
            $rowUser = $stmtUser->fetch(PDO::FETCH_ASSOC);
            $id = (int)($rowUser['id'] ?? 0);

            if ($id > 0) {
                $_SESSION['user_id'] = $id;
                return $id;
            }
        }

        return 0;
    }
}

requireRole([
    'SUPER_ADMIN',
    'RECOUVREMENT',
    'CHEF_RECOUVREMENT',
    'CAISSIER'
]);

$page_title = "Paiement NP / NPF";

$numero = $_GET['numero'] ?? ($_POST['numero'] ?? null);

if (!$numero) {
    die("Numéro NP ou NPF obligatoire.");
}

function tableColumnsPay($pdo, $table) {
    $cols = [];
    $stmt = $pdo->query("SHOW COLUMNS FROM $table");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $cols[] = $c['Field'];
    }
    return $cols;
}

function hasColPay($cols, $name) {
    return in_array($name, $cols);
}

function tauxPaiement($pdo, $devise) {
    if ($devise === 'CDF') return 1;

    try {
        $stmt = $pdo->prepare("
            SELECT taux
            FROM taux_change
            WHERE devise = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([$devise]);
        $row = $stmt->fetch();

        if ($row && (float)$row['taux'] > 0) {
            return (float)$row['taux'];
        }
    } catch (Exception $e) {}

    if ($devise === 'USD') return 2800;
    if ($devise === 'EUR') return 3000;

    return 1;
}

function nomContribuablePaiement($c) {
    if (!empty($c['raison_sociale'])) return $c['raison_sociale'];

    return trim(
        ($c['nom'] ?? '') . ' ' .
        ($c['postnom'] ?? '') . ' ' .
        ($c['prenom'] ?? '')
    );
}

$colsPaiements = tableColumnsPay($pdo, "paiements");

$stmt = $pdo->prepare("
    SELECT 
        np.*,
        nd.numero_nd,
        nt.numero_nt,
        c.raison_sociale,
        c.nom,
        c.postnom,
        c.prenom,
        c.nif
    FROM notes_perception np
    JOIN notes_debit nd ON np.note_debit_id = nd.id
    JOIN notes_taxation nt ON nd.note_taxation_id = nt.id
    JOIN contribuables c ON nt.contribuable_id = c.id
    WHERE np.numero_np = ?
    LIMIT 1
");
$stmt->execute([$numero]);
$np = $stmt->fetch();

if (!$np) {
    die("NP / NPF introuvable.");
}

if ((float)$np['solde_restant'] <= 0 || ($np['statut'] ?? '') === 'payee') {
    die("Cette note est déjà totalement payée. Aucun nouveau paiement n'est autorisé.");
}
/*
|--------------------------------------------------------------------------
| Contrôle AMR pour note échue
|--------------------------------------------------------------------------
*/

if (!empty($np['date_echeance'])) {

    $today = date('Y-m-d');
    $echeance = date('Y-m-d', strtotime($np['date_echeance']));

    if ($today > $echeance) {

        $stmt = $pdo->prepare("
            SELECT *
            FROM amr
            WHERE note_perception_id = ?
            AND reference_numero = ?
            AND statut = 'valide'
            LIMIT 1
        ");

        $stmt->execute([
            $np['id'],
            $np['numero_np']
        ]);

        $amr = $stmt->fetch();

        if (!$amr) {

            die("
                Paiement bloqué.

                Cette note a dépassé sa date d'échéance.
                Un AMR doit être émis et validé avant tout paiement.
            ");
        }
    }
}
/*
|--------------------------------------------------------------------------
| Source des comptes bancaires
|--------------------------------------------------------------------------
| NP globale  : utilise ses propres comptes.
| NPF         : utilise les comptes de la NP mère.
*/
$noteBanqueSourceId = (int)$np['id'];

if (($np['type_np'] ?? '') === 'fractionnee' && !empty($np['np_mere_id'])) {
    $noteBanqueSourceId = (int)$np['np_mere_id'];
}

/*
|--------------------------------------------------------------------------
| Sécurité NPF : respect de l'ordre chronologique
|--------------------------------------------------------------------------
| Une NPF ne peut être payée que si toutes les tranches précédentes
| de la même NP mère sont déjà payées.
*/
if (($np['type_np'] ?? '') === 'fractionnee') {
    $stmt = $pdo->prepare("
        SELECT numero_np
        FROM notes_perception
        WHERE type_np = 'fractionnee'
        AND np_mere_id = ?
        AND numero_tranche < ?
        AND statut <> 'payee'
        ORDER BY numero_tranche ASC
        LIMIT 1
    ");
    $stmt->execute([
        $np['np_mere_id'],
        $np['numero_tranche']
    ]);

    $fractionAvant = $stmt->fetch();

    if ($fractionAvant) {
        die(
            "Paiement refusé. Vous devez d'abord payer la fraction antérieure : " .
            htmlspecialchars($fractionAvant['numero_np'])
        );
    }
}

/*
|--------------------------------------------------------------------------
| Comptes bancaires autorisés
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT 
        nb.id AS note_banque_id,
        nb.montant_affecte,
        nb.observation,
        cb.id AS compte_bancaire_id,
        cb.banque,
        cb.numero_compte,
        cb.devise,
        cb.intitule_compte
    FROM note_banques nb
    JOIN comptes_bancaires cb ON nb.compte_bancaire_id = cb.id
    WHERE nb.note_perception_id = ?
    ORDER BY cb.banque ASC
");

$stmt->execute([$noteBanqueSourceId]);
$banques = $stmt->fetchAll();

if (empty($banques)) {
    die("Aucun compte bancaire n'a été affecté à cette note ou à sa NP mère par l'ordonnateur.");
}

$modesPaiement = [
    1 => "BANQUE",
    2 => "CARTE BANCAIRE",
    3 => "VIREMENT",
    4 => "MOBILE MONEY",
    5 => "PAIEMENT EN LIGNE"
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $montant = (float)str_replace(',', '.', $_POST['montant'] ?? 0);
    $devise = $_POST['devise'] ?? 'CDF';
    $mode_id = (int)($_POST['mode_paiement_id'] ?? 0);
    $reference = trim($_POST['reference_transaction'] ?? '');

    $note_banque_id = (int)($_POST['note_banque_id'] ?? 0);

    $banque = '';
    $numero_compte = '';
    $intitule_compte = '';
    $devise_compte = '';

    if ($note_banque_id > 0) {
        $stmt = $pdo->prepare("
            SELECT 
                nb.id AS note_banque_id,
                nb.montant_affecte,
                cb.banque,
                cb.numero_compte,
                cb.devise,
                cb.intitule_compte
            FROM note_banques nb
            JOIN comptes_bancaires cb ON nb.compte_bancaire_id = cb.id
            WHERE nb.id = ?
            AND nb.note_perception_id = ?
            LIMIT 1
        ");
        $stmt->execute([$note_banque_id, $noteBanqueSourceId]);
        $compteAffecte = $stmt->fetch();

        if (!$compteAffecte) {
            die("Compte bancaire non autorisé pour cette note.");
        }

        $banque = $compteAffecte['banque'];
        $numero_compte = $compteAffecte['numero_compte'];
        $intitule_compte = $compteAffecte['intitule_compte'];
        $devise_compte = $compteAffecte['devise'];
    }
        $type_carte = trim($_POST['type_carte'] ?? '');
    $banque_emettrice = trim($_POST['banque_emettrice'] ?? '');
    $numero_carte_masque = trim($_POST['numero_carte_masque'] ?? '');

    $banque_beneficiaire = trim($_POST['banque_beneficiaire'] ?? '');
    $reseau_mobile_money = trim($_POST['reseau_mobile_money'] ?? '');
    $telephone_mobile_money = trim($_POST['telephone_mobile_money'] ?? '');
    $titulaire_mobile_money = trim($_POST['titulaire_mobile_money'] ?? '');
    $observation = trim($_POST['observation'] ?? '');

    if ($montant <= 0) {
        die("Le montant payé doit être supérieur à zéro.");
    }

    if (!in_array($devise, ['CDF', 'USD', 'EUR'])) {
        die("Devise invalide.");
    }

    if ($mode_id <= 0) {
        die("Mode de paiement obligatoire.");
    }

    if ($reference === '') {
        die("Référence de transaction obligatoire.");
    }

    if ($mode_id === 1 && $note_banque_id <= 0) {
        die("Veuillez sélectionner le compte bancaire prévu par l'ordonnateur.");
    }

    $taux = tauxPaiement($pdo, $devise);
    $montant_converti_cdf = round($montant * $taux, 2);

    if ($montant_converti_cdf > (float)$np['solde_restant']) {
        die("Le montant payé dépasse le solde restant de la note.");
    }

    $nouveau_montant_paye = (float)$np['montant_paye'] + $montant_converti_cdf;
    $nouveau_solde = (float)$np['solde_restant'] - $montant_converti_cdf;

    if ($nouveau_solde <= 0) {
        $nouveau_solde = 0;
        $nouveau_statut = 'payee';
    } else {
        $nouveau_statut = 'partiellement_payee';
    }

    try {
        $insertCols = [];
        $values = [];

        $map = [
            'note_perception_id' => $np['id'],
            'fraction_id' => null,
            'date_paiement' => date('Y-m-d'),
            'montant_paye' => $montant,
            'devise' => $devise,
            'taux_change' => $taux,
            'montant_converti_cdf' => $montant_converti_cdf,
            'mode_paiement_id' => $mode_id,
            'reference_transaction' => $reference,
            'compte_credite' => $numero_compte,
            'banque' => $banque,
            'numero_compte' => $numero_compte,
            'intitule_compte' => $intitule_compte,
            'type_carte' => $type_carte,
            'banque_emettrice' => $banque_emettrice,
            'numero_carte_masque' => $numero_carte_masque,
            'banque_beneficiaire' => $banque_beneficiaire,
            'reseau_mobile_money' => $reseau_mobile_money,
            'telephone_mobile_money' => $telephone_mobile_money,
            'titulaire_mobile_money' => $titulaire_mobile_money,
            'observation' => $observation,
            'statut' => $nouveau_statut === 'payee' ? 'apure_total' : 'apure_partiel',
            'user_comptable_id' => cpRecouvrementCurrentUserId($pdo),
            'created_at' => date('Y-m-d H:i:s')
        ];

        foreach ($map as $col => $val) {
            if (hasColPay($colsPaiements, $col)) {
                $insertCols[] = $col;
                $values[] = $val;
            }
        }

        $sql = "
            INSERT INTO paiements (" . implode(',', $insertCols) . ")
            VALUES (" . implode(',', array_fill(0, count($insertCols), '?')) . ")
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);

        $stmt = $pdo->prepare("
            UPDATE notes_perception
            SET
                montant_paye = ?,
                solde_restant = ?,
                statut = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $nouveau_montant_paye,
            $nouveau_solde,
            $nouveau_statut,
            $np['id']
        ]);

        if (function_exists('auditLog')) {
            auditLog(
                $pdo,
                cpRecouvrementCurrentUserId($pdo),
                "Paiement enregistré",
                "Recouvrement",
                $np['numero_np'],
                "Paiement de " . formatMoney($montant_converti_cdf) . " CDF enregistré. Référence : " . $reference
            );
        }

        header("Location: paiement_create.php?numero=" . urlencode($np['numero_np']) . "&success=1");
        exit;

    } catch (Exception $e) {
        die("Erreur paiement : " . $e->getMessage());
    }
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
.hero-luxoria{
    background:linear-gradient(135deg,#06152b,#0f3460);
    color:white;
    border-radius:24px;
    padding:24px;
    margin-bottom:22px;
}
.grid-2{
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:14px;
}
.grid-3{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:14px;
}
.info-box{
    background:#eff6ff;
    border:1px solid #bfdbfe;
    color:#1e3a8a;
    padding:14px;
    border-radius:14px;
    font-weight:800;
    margin-bottom:18px;
}
.success-box{
    background:#dcfce7;
    border:1px solid #86efac;
    color:#166534;
    padding:14px;
    border-radius:14px;
    font-weight:900;
    margin-bottom:18px;
}
.warning-box{
    background:#fff7ed;
    border:1px solid #fed7aa;
    color:#9a3412;
    padding:14px;
    border-radius:14px;
    font-weight:800;
    margin-bottom:18px;
}
.amount-big{
    font-size:28px;
    font-weight:900;
    color:#0f3460;
}
label{
    font-weight:900;
    color:#0f3460;
    display:block;
    margin-bottom:6px;
}
.payment-zone{
    background:#f8fafc;
    border:1px solid #e5e7eb;
    border-radius:18px;
    padding:18px;
    margin-top:15px;
}
.mode-block{
    display:none;
}
.mode-block.active{
    display:block;
}
.btn-luxoria{
    border:none;
    padding:14px 22px;
    border-radius:16px;
    font-weight:900;
    cursor:pointer;
    background:linear-gradient(135deg,#0f3460,#06152b);
    color:white;
    box-shadow:0 10px 22px rgba(15,52,96,.25);
}
.btn-secondary{
    display:inline-block;
    padding:13px 18px;
    border-radius:16px;
    text-decoration:none;
    font-weight:900;
    border:1px solid #0f3460;
    color:#0f3460;
    background:white;
}
.actions{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    margin-top:18px;
}
.bank-card{
    background:#fff;
    border:1px solid #e5e7eb;
    border-radius:14px;
    padding:12px;
    margin-bottom:10px;
}
.bank-card strong{
    color:#0f3460;
}
.small-muted{
    color:#64748b;
    font-size:12px;
}
</style>
<link rel="stylesheet" href="../../assets/css/recouvrement.css">
</head>

<body class="cp-recouvrement-page">
<div class="admin-layout">

<?php require_once "../../includes/sidebar.php"; ?>

<main class="main-content">
<?php require_once "../../includes/topbar.php"; ?>

<div class="hero-luxoria">
    <h2>Paiement NP / NPF</h2>
    <p>Confirmation sécurisée d’un paiement déjà effectué auprès du canal autorisé.</p>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="success-box">
        Paiement enregistré avec succès. Le solde et le statut de la note ont été mis à jour.
    </div>
<?php endif; ?>

<div class="panel cp-rec-panel">
    <h3>I. Note concernée</h3>

    <table class="table-premium cp-rec-table">
        <tr><th>Numéro</th><td><strong><?= htmlspecialchars($np['numero_np']) ?></strong></td></tr>
        <tr><th>Type</th><td><?= strtoupper(htmlspecialchars($np['type_np'])) ?></td></tr>
        <tr><th>ND</th><td><?= htmlspecialchars($np['numero_nd']) ?></td></tr>
        <tr><th>NT</th><td><?= htmlspecialchars($np['numero_nt']) ?></td></tr>
        <tr><th>Assujetti</th><td><?= htmlspecialchars(nomContribuablePaiement($np)) ?></td></tr>
        <tr><th>NIF</th><td><?= htmlspecialchars($np['nif'] ?? '-') ?></td></tr>
        <tr><th>Montant initial</th><td><?= formatMoney($np['montant_initial']) ?> CDF</td></tr>
        <tr><th>Montant déjà payé</th><td><?= formatMoney($np['montant_paye']) ?> CDF</td></tr>
        <tr><th>Solde restant</th><td><span class="amount-big"><?= formatMoney($np['solde_restant']) ?> CDF</span></td></tr>
        <tr><th>Statut</th><td><?= strtoupper(htmlspecialchars(str_replace('_', ' ', $np['statut']))) ?></td></tr>
    </table>
</div>

<div class="panel cp-rec-panel">
    <h3>II. Comptes bancaires autorisés par l’ordonnateur</h3>

    <div class="info-box">
        Seuls les comptes affectés à cette note par l’ordonnateur sont disponibles pour la confirmation du paiement.
    </div>

    <?php foreach ($banques as $b): ?>
        <div class="bank-card">
            <strong><?= htmlspecialchars($b['banque']) ?></strong><br>
            Compte : <?= htmlspecialchars($b['numero_compte']) ?><br>
            Intitulé : <?= htmlspecialchars($b['intitule_compte'] ?? '-') ?><br>
            Devise : <?= htmlspecialchars($b['devise']) ?><br>
            Montant prévu : <strong><?= formatMoney($b['montant_affecte']) ?> CDF</strong>
            <?php if (!empty($b['observation'])): ?>
                <div class="small-muted"><?= htmlspecialchars($b['observation']) ?></div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<div class="panel cp-rec-panel">
    <h3>III. Enregistrer un paiement</h3>

    <div class="warning-box">
        Le paiement correspond à une confirmation d’un versement déjà effectué. Le montant converti en CDF ne peut jamais dépasser le solde restant.
    </div>

    <form method="POST">
        <input type="hidden" name="numero" value="<?= htmlspecialchars($np['numero_np']) ?>">

        <div class="grid-3">
            <div>
                <label>Montant payé</label>
                <input type="number" step="0.01" min="0" name="montant" required>
            </div>

            <div>
                <label>Devise paiement</label>
                <select name="devise" required>
                    <option value="CDF">CDF</option>
                    <option value="USD">USD</option>
                    <option value="EUR">EUR</option>
                </select>
            </div>

            <div>
                <label>Mode de paiement</label>
                <select name="mode_paiement_id" id="modePaiement" required>
                    <option value="">-- Sélectionner --</option>
                    <?php foreach ($modesPaiement as $id => $label): ?>
                        <option value="<?= $id ?>"><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div>
            <label>Référence transaction</label>
            <input type="text" name="reference_transaction" required>
        </div>

        <div class="payment-zone mode-block" id="mode1">
            <h4>Paiement bancaire</h4>

            <div class="grid-3">
                <div>
                    <label>Compte bancaire autorisé</label>
                    <select name="note_banque_id" id="banqueSelect">
                        <option value="">-- Sélectionner --</option>
                        <?php foreach ($banques as $b): ?>
                            <option
                                value="<?= (int)$b['note_banque_id'] ?>"
                                data-compte="<?= htmlspecialchars($b['numero_compte']) ?>"
                                data-intitule="<?= htmlspecialchars($b['intitule_compte'] ?? '') ?>"
                                data-devise="<?= htmlspecialchars($b['devise']) ?>">
                                <?= htmlspecialchars($b['banque']) ?>
                                —
                                <?= htmlspecialchars($b['numero_compte']) ?>
                                —
                                prévu <?= formatMoney($b['montant_affecte']) ?> <?= htmlspecialchars($b['devise']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Numéro compte</label>
                    <input type="text" id="numeroCompte" readonly>
                </div>

                <div>
                    <label>Intitulé compte</label>
                    <input type="text" id="intituleCompte" readonly>
                </div>
            </div>
        </div>

        <div class="payment-zone mode-block" id="mode2">
            <h4>Paiement par carte bancaire</h4>

            <div class="grid-3">
                <div><label>Type carte</label><input type="text" name="type_carte" placeholder="Visa, Mastercard..."></div>
                <div><label>Banque émettrice</label><input type="text" name="banque_emettrice"></div>
                <div><label>Numéro carte masqué</label><input type="text" name="numero_carte_masque" placeholder="**** **** **** 1234"></div>
            </div>
        </div>

        <div class="payment-zone mode-block" id="mode3">
            <h4>Virement bancaire</h4>

            <div class="grid-2">
                <div><label>Banque émettrice</label><input type="text" name="banque_emettrice"></div>
                <div><label>Banque bénéficiaire</label><input type="text" name="banque_beneficiaire"></div>
            </div>
        </div>

        <div class="payment-zone mode-block" id="mode4">
            <h4>Mobile Money</h4>

            <div class="grid-3">
                <div>
                    <label>Réseau</label>
                    <select name="reseau_mobile_money">
                        <option value="">-- Réseau --</option>
                        <option value="Airtel Money">Airtel Money</option>
                        <option value="M-Pesa">M-Pesa</option>
                        <option value="Orange Money">Orange Money</option>
                        <option value="AfriMoney">AfriMoney</option>
                    </select>
                </div>
                <div><label>Numéro téléphone</label><input type="text" name="telephone_mobile_money"></div>
                <div><label>Nom titulaire</label><input type="text" name="titulaire_mobile_money"></div>
            </div>
        </div>

        <div class="payment-zone mode-block" id="mode5">
            <h4>Paiement en ligne</h4>
            <div class="info-box">
                Prévu pour intégration future : passerelle bancaire, carte, Mobile Money, QR Code ou lien sécurisé.
            </div>
        </div>

        <div>
            <label>Observation</label>
            <textarea name="observation"></textarea>
        </div>

        <div class="actions">
            <button type="submit" class="btn-luxoria">
                Confirmer le paiement
            </button>

            <a href="../ordonnancement/np_view.php?numero=<?= urlencode($np['numero_np']) ?>" class="btn-secondary">
                Retour à la note
            </a>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    const mode = document.getElementById('modePaiement');
    const blocks = document.querySelectorAll('.mode-block');

    function refreshMode(){
        blocks.forEach(b => b.classList.remove('active'));
        const selected = mode.value;
        const block = document.getElementById('mode' + selected);
        if(block){ block.classList.add('active'); }
    }

    mode.addEventListener('change', refreshMode);
    refreshMode();

    const banqueSelect = document.getElementById('banqueSelect');
    const numeroCompte = document.getElementById('numeroCompte');
    const intituleCompte = document.getElementById('intituleCompte');

    if(banqueSelect){
        banqueSelect.addEventListener('change', function(){
            const opt = this.options[this.selectedIndex];
            numeroCompte.value = opt.getAttribute('data-compte') || '';
            intituleCompte.value = opt.getAttribute('data-intitule') || '';
        });
    }
});
</script>

</main>
</div>
</body>
</html>