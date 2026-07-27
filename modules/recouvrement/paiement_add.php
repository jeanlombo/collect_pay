<?php
require_once "../../config/database.php";
require_once "../../config/security.php";
require_once "../../core/functions.php";

checkAuth();
requireRole([
    'SUPER_ADMIN',
    'RECOUVREMENT',
    'CHEF_RECOUVREMENT',
    'CAISSIER'
]);

$page_title = "Enregistrer un paiement";

$numero = $_GET['numero'] ?? ($_POST['numero'] ?? null);

if (!$numero) {
    die("Numéro NP / NPF obligatoire.");
}

/*
|--------------------------------------------------------------------------
| Fonctions utilitaires
|--------------------------------------------------------------------------
*/
function tableColumnsPaiementAdd($pdo, $table)
{
    $cols = [];
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `$table`");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $cols[] = $c['Field'];
        }
    } catch (Exception $e) {
        die("Impossible de lire la structure de la table $table : " . $e->getMessage());
    }
    return $cols;
}

function hasColPaiementAdd($cols, $name)
{
    return in_array($name, $cols, true);
}

function tauxPaiementAdd($pdo, $devise)
{
    $devise = strtoupper(trim($devise));

    if ($devise === 'CDF') {
        return 1;
    }

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

function nomContribuablePaiementAdd($c)
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

function formatMoneyPaiementAdd($v)
{
    return number_format((float)$v, 2, ',', ' ');
}

/*
|--------------------------------------------------------------------------
| Chargement de la NP / NPF
|--------------------------------------------------------------------------
*/
$colsPaiements = tableColumnsPaiementAdd($pdo, "paiements");

$stmt = $pdo->prepare("
    SELECT 
        np.*,
        mere.numero_np AS numero_np_mere,
        nd.numero_nd,
        nt.numero_nt,
        c.type_personne,
        c.raison_sociale,
        c.nom,
        c.postnom,
        c.prenom,
        c.nif,
        c.rccm,
        c.telephone,
        c.adresse,
        c.ville
    FROM notes_perception np
    LEFT JOIN notes_perception mere ON np.np_mere_id = mere.id
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

if ((float)($np['solde_restant'] ?? 0) <= 0 || ($np['statut'] ?? '') === 'payee') {
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
            SELECT id
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
                Paiement bloqué.<br><br>
                Cette note a dépassé sa date d'échéance.
                Un AMR doit être émis et validé avant tout paiement.
            ");
        }
    }
}

/*
|--------------------------------------------------------------------------
| Pénalités AMR déjà appliquées à la note
|--------------------------------------------------------------------------
| Important :
| - L'AMR ajoute sa pénalité dans le solde global de la NP / NPF au moment
|   de son émission.
| - Le paiement valide donc toujours le solde global restant, pénalités incluses.
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT 
        IFNULL(SUM(montant_penalite), 0) AS total_penalite_amr,
        IFNULL(SUM(montant_total), 0) AS total_amr,
        COUNT(*) AS total_amr_count
    FROM amr
    WHERE note_perception_id = ?
    AND reference_numero = ?
    AND statut IN ('emis', 'valide', 'validée', 'validee')
");
$stmt->execute([
    $np['id'],
    $np['numero_np']
]);
$amrGlobal = $stmt->fetch();

$penalite_amr = (float)($amrGlobal['total_penalite_amr'] ?? 0);
$solde_global_exigible = (float)($np['solde_restant'] ?? 0);

/*
|--------------------------------------------------------------------------
| Sécurité NPF : respect de l'ordre chronologique
|--------------------------------------------------------------------------
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
| NP globale : comptes de la NP.
| NPF       : comptes de la NP mère.
|--------------------------------------------------------------------------
*/
$noteBanqueSourceId = (int)$np['id'];

if (($np['type_np'] ?? '') === 'fractionnee' && !empty($np['np_mere_id'])) {
    $noteBanqueSourceId = (int)$np['np_mere_id'];
}

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

/*
|--------------------------------------------------------------------------
| Traitement du formulaire
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $montant = (float)str_replace(',', '.', $_POST['montant'] ?? 0);
    $devise = strtoupper(trim($_POST['devise'] ?? 'CDF'));
    $mode_id = (int)($_POST['mode_paiement_id'] ?? 0);
    $reference = trim($_POST['reference_transaction'] ?? '');
    $note_banque_id = (int)($_POST['note_banque_id'] ?? 0);

    $type_carte = trim($_POST['type_carte'] ?? '');
    $banque_emettrice = trim($_POST['banque_emettrice'] ?? '');
    $numero_carte_masque = trim($_POST['numero_carte_masque'] ?? '');
    $banque_beneficiaire = trim($_POST['banque_beneficiaire'] ?? '');
    $reseau_mobile_money = trim($_POST['reseau_mobile_money'] ?? '');
    $telephone_mobile_money = trim($_POST['telephone_mobile_money'] ?? '');
    $titulaire_mobile_money = trim($_POST['titulaire_mobile_money'] ?? '');
    $observation = trim($_POST['observation'] ?? '');

    $banque = '';
    $numero_compte = '';
    $intitule_compte = '';
    $devise_compte = '';

    if ($montant <= 0) {
        die("Le montant payé doit être supérieur à zéro.");
    }

    if (!in_array($devise, ['CDF', 'USD', 'EUR'], true)) {
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

    $taux = tauxPaiementAdd($pdo, $devise);
    $montant_converti_cdf = round($montant * $taux, 2);

    if ($montant_converti_cdf > $solde_global_exigible) {
        die("Le montant payé dépasse le montant global exigible de la note, pénalités incluses.");
    }

    $nouveau_montant_paye = (float)$np['montant_paye'] + $montant_converti_cdf;
    $nouveau_solde = $solde_global_exigible - $montant_converti_cdf;

    if ($nouveau_solde <= 0.01) {
        $nouveau_solde = 0;
        $nouveau_statut = 'payee';
        $statutPaiement = 'apure_total';
    } else {
        $nouveau_statut = 'partiellement_payee';
        $statutPaiement = 'apure_partiel';
    }

    try {
        $pdo->beginTransaction();

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
            'statut' => $statutPaiement,
            'user_comptable_id' => $_SESSION['user_id'] ?? null,
            'created_at' => date('Y-m-d H:i:s')
        ];

        foreach ($map as $col => $val) {
            if (hasColPaiementAdd($colsPaiements, $col)) {
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

        $paiement_id = (int)$pdo->lastInsertId();

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

        /*
        |--------------------------------------------------------------------------
        | Si la note payée est une fraction, on synchronise le paiement de la NP mère
        |--------------------------------------------------------------------------
        */
        if (($np['type_np'] ?? '') === 'fractionnee' && !empty($np['np_mere_id'])) {
            $stmt = $pdo->prepare("
                SELECT
                    IFNULL(SUM(montant_paye),0) AS total_paye,
                    IFNULL(SUM(solde_restant),0) AS total_solde,
                    COUNT(*) AS total_fractions,
                    SUM(CASE WHEN statut = 'payee' THEN 1 ELSE 0 END) AS total_payees
                FROM notes_perception
                WHERE np_mere_id = ?
                AND type_np = 'fractionnee'
            ");
            $stmt->execute([$np['np_mere_id']]);
            $sync = $stmt->fetch();

            $statutMere = ((int)$sync['total_payees'] === (int)$sync['total_fractions'])
                ? 'payee'
                : (((float)$sync['total_paye'] > 0) ? 'partiellement_payee' : 'non_payee');

            $stmt = $pdo->prepare("
                UPDATE notes_perception
                SET
                    montant_paye = ?,
                    solde_restant = ?,
                    statut = ?
                WHERE id = ?
            ");
            $stmt->execute([
                (float)$sync['total_paye'],
                (float)$sync['total_solde'],
                $statutMere,
                $np['np_mere_id']
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | Apurement automatique de la NP / NPF payée
        |--------------------------------------------------------------------------
        | Chaque paiement met à jour ou crée l'apurement correspondant.
        | La quittance reste autorisée uniquement si solde_restant = 0.
        */
        $referenceTypeApurement = (($np['type_np'] ?? '') === 'fractionnee') ? 'FRACTION' : 'NP';
        $statutApurement = ($nouveau_solde <= 0.01) ? 'total' : 'partiel';
        $montantDuApurement = (float)($np['montant_initial'] ?? 0);

        $stmt = $pdo->prepare("\n            SELECT id\n            FROM apurements\n            WHERE reference_type = ?\n            AND reference_id = ?\n            LIMIT 1\n        ");
        $stmt->execute([$referenceTypeApurement, $np['id']]);
        $apurementExistant = $stmt->fetch();

        if ($apurementExistant) {
            $stmt = $pdo->prepare("\n                UPDATE apurements\n                SET\n                    montant_du = ?,\n                    montant_paye = ?,\n                    penalite_validee = ?,\n                    solde_restant = ?,\n                    statut = ?,\n                    date_apurement = CURDATE(),\n                    user_apurement_id = ?\n                WHERE id = ?\n            ");
            $stmt->execute([
                $montantDuApurement,
                $nouveau_montant_paye,
                $penalite_amr,
                $nouveau_solde,
                $statutApurement,
                $_SESSION['user_id'] ?? null,
                $apurementExistant['id']
            ]);
        } else {
            $stmt = $pdo->prepare("\n                INSERT INTO apurements\n                (\n                    reference_type,\n                    reference_id,\n                    montant_du,\n                    montant_paye,\n                    penalite_validee,\n                    solde_restant,\n                    statut,\n                    date_apurement,\n                    user_apurement_id\n                )\n                VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), ?)\n            ");
            $stmt->execute([
                $referenceTypeApurement,
                $np['id'],
                $montantDuApurement,
                $nouveau_montant_paye,
                $penalite_amr,
                $nouveau_solde,
                $statutApurement,
                $_SESSION['user_id'] ?? null
            ]);
        }

        if (function_exists('auditLog')) {
            auditLog(
                $pdo,
                $_SESSION['user_id'] ?? null,
                "Paiement enregistré",
                "Recouvrement",
                $np['numero_np'],
                "Paiement de " . formatMoneyPaiementAdd($montant_converti_cdf) . " CDF enregistré. Référence : " . $reference
            );
        }

        $pdo->commit();

        header("Location: paiement_view.php?id=" . urlencode($paiement_id));
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        die("Erreur paiement : " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($page_title) ?> | cOllect_Pay</title>
<link rel="stylesheet" href="/collect_pay/assets/css/admin.css">

<style>
.hero-luxoria{
    background:linear-gradient(135deg,#06152b,#0f3460);
    color:white;
    border-radius:24px;
    padding:24px;
    margin-bottom:22px;
}
.grid-2{display:grid;grid-template-columns:repeat(2,1fr);gap:14px}
.grid-3{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}
.info-box{background:#eff6ff;border:1px solid #bfdbfe;color:#1e3a8a;padding:14px;border-radius:14px;font-weight:800;margin-bottom:18px}
.warning-box{background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;padding:14px;border-radius:14px;font-weight:800;margin-bottom:18px}
.amount-big{font-size:28px;font-weight:900;color:#0f3460}
label{font-weight:900;color:#0f3460;display:block;margin-bottom:6px}
.payment-zone{background:#f8fafc;border:1px solid #e5e7eb;border-radius:18px;padding:18px;margin-top:15px}
.mode-block{display:none}
.mode-block.active{display:block}
.btn-luxoria{border:none;padding:14px 22px;border-radius:16px;font-weight:900;cursor:pointer;background:linear-gradient(135deg,#0f3460,#06152b);color:white;box-shadow:0 10px 22px rgba(15,52,96,.25)}
.btn-secondary{display:inline-block;padding:13px 18px;border-radius:16px;text-decoration:none;font-weight:900;border:1px solid #0f3460;color:#0f3460;background:white}
.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}
.bank-card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:12px;margin-bottom:10px}
.bank-card strong{color:#0f3460}
.small-muted{color:#64748b;font-size:12px}
@media(max-width:900px){.grid-2,.grid-3{grid-template-columns:1fr}}
</style>
</head>

<body>
<div class="admin-layout">

<?php require_once "../../includes/sidebar.php"; ?>

<main class="main-content">
<?php require_once "../../includes/topbar.php"; ?>

<div class="hero-luxoria">
    <h2>Paiement NP / NPF</h2>
    <p>Confirmation sécurisée d’un paiement déjà effectué auprès du canal autorisé.</p>
</div>

<div class="panel">
    <h3>I. Note concernée</h3>

    <table class="table-premium">
        <tr><th>Numéro</th><td><strong><?= htmlspecialchars($np['numero_np']) ?></strong></td></tr>
        <tr><th>Type</th><td><?= strtoupper(htmlspecialchars($np['type_np'])) ?></td></tr>
        <?php if (!empty($np['numero_np_mere'])): ?>
            <tr><th>NP mère</th><td><?= htmlspecialchars($np['numero_np_mere']) ?></td></tr>
        <?php endif; ?>
        <tr><th>ND</th><td><?= htmlspecialchars($np['numero_nd']) ?></td></tr>
        <tr><th>NT</th><td><?= htmlspecialchars($np['numero_nt']) ?></td></tr>
        <tr><th>Assujetti</th><td><?= htmlspecialchars(nomContribuablePaiementAdd($np)) ?></td></tr>
        <tr><th>NIF</th><td><?= htmlspecialchars($np['nif'] ?? '-') ?></td></tr>
        <tr><th>Montant initial</th><td><?= formatMoneyPaiementAdd($np['montant_initial']) ?> CDF</td></tr>
        <tr><th>Montant déjà payé</th><td><?= formatMoneyPaiementAdd($np['montant_paye']) ?> CDF</td></tr>
        <tr><th>Solde global à valider</th><td><span class="amount-big"><?= formatMoneyPaiementAdd($solde_global_exigible) ?> CDF</span></td></tr>
        <?php if ($penalite_amr > 0): ?>
            <tr><th>Pénalités AMR incluses</th><td><strong><?= formatMoneyPaiementAdd($penalite_amr) ?> CDF</strong></td></tr>
        <?php endif; ?>
        <tr><th>Statut</th><td><?= strtoupper(htmlspecialchars(str_replace('_', ' ', $np['statut']))) ?></td></tr>
    </table>
</div>

<div class="panel">
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
            Montant prévu : <strong><?= formatMoneyPaiementAdd($b['montant_affecte']) ?> CDF</strong>
            <?php if (!empty($b['observation'])): ?>
                <div class="small-muted"><?= htmlspecialchars($b['observation']) ?></div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<div class="panel">
    <h3>III. Enregistrer un paiement</h3>

    <div class="warning-box">
        Après confirmation, le système génère une attestation de paiement. La quittance reste disponible uniquement lorsque la NP globale est totalement apurée.
        <br><br>
        Montant à valider : <strong><?= formatMoneyPaiementAdd($solde_global_exigible) ?> CDF</strong>
        <?php if ($penalite_amr > 0): ?>
            <br>Pénalités AMR déjà incluses : <strong><?= formatMoneyPaiementAdd($penalite_amr) ?> CDF</strong>
        <?php endif; ?>
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
                                prévu <?= formatMoneyPaiementAdd($b['montant_affecte']) ?> CDF
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

    if(mode){
        mode.addEventListener('change', refreshMode);
        refreshMode();
    }

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
