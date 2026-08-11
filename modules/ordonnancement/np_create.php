<?php
require_once "../../config/database.php";
require_once "../../config/security.php";
require_once "../../core/numero_generator.php";

checkAuth();
requireRole([
    'SUPER_ADMIN',
    'ORDONNATEUR'
]);

$page_title = "Créer Note de Perception";

$numero_nd = $_GET['numero_nd'] ?? null;

if (!$numero_nd) {
    die("Numéro ND obligatoire.");
}

function ajouterJoursOuvrables($dateDepart, $jours)
{
    $date = new DateTime($dateDepart);
    $ajoutes = 0;

    while ($ajoutes < $jours) {
        $date->modify('+1 day');
        if ((int)$date->format('N') <= 5) {
            $ajoutes++;
        }
    }

    return $date->format('Y-m-d H:i:s');
}

$stmt = $pdo->prepare("
    SELECT 
        nd.*,
        nt.numero_nt,
        c.raison_sociale,
        c.nom,
        c.postnom,
        c.prenom,
        c.nif
    FROM notes_debit nd
    JOIN notes_taxation nt ON nd.note_taxation_id = nt.id
    JOIN contribuables c ON nt.contribuable_id = c.id
    WHERE nd.numero_nd = ?
    LIMIT 1
");
$stmt->execute([$numero_nd]);
$nd = $stmt->fetch();

if (!$nd) {
    die("ND introuvable.");
}

if (!(($nd['statut'] ?? '') === 'validee' && ($nd['decision'] ?? '') === 'conforme')) {
    die("Impossible de créer une NP : la ND doit être validée conforme.");
}

$stmt = $pdo->prepare("
    SELECT numero_np
    FROM notes_perception
    WHERE note_debit_id = ?
    AND type_np = 'globale'
    LIMIT 1
");
$stmt->execute([$nd['id']]);
$existing = $stmt->fetch();

if ($existing) {
    header("Location: np_view.php?numero=" . urlencode($existing['numero_np']));
    exit;
}

$banques = $pdo->prepare("
    SELECT *
    FROM comptes_bancaires
    WHERE actif = 1
    ORDER BY banque ASC
");
$banques->execute();
$banques = $banques->fetchAll();

function nomContribuableNP($c)
{
    if (!empty($c['raison_sociale'])) {
        return $c['raison_sociale'];
    }

    return trim(($c['nom'] ?? '') . ' ' . ($c['postnom'] ?? '') . ' ' . ($c['prenom'] ?? ''));
}

$montant_total = (float)($nd['montant_total'] ?? $nd['total_exigible'] ?? 0);
$penalite_assiette = (float)($nd['penalite_assiette'] ?? 0);
$penalite_recouvrement = (float)($nd['penalite_recouvrement'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $declarant_nom = trim($_POST['declarant_nom'] ?? '');
    $sceau_appose = isset($_POST['sceau_appose']) ? 1 : 0;
    $banquesPost = $_POST['banques'] ?? [];

    if ($declarant_nom === '') {
        die("Le nom du déclarant est obligatoire.");
    }

    $totalBanques = 0;
    $repartition = [];

    foreach ($banquesPost as $compteId => $montant) {
        $montant = (float)str_replace(',', '.', $montant);

        if ($montant > 0) {
            $repartition[(int)$compteId] = round($montant, 2);
            $totalBanques += round($montant, 2);
        }
    }

    if (empty($repartition)) {
        die("Veuillez affecter le montant de la NP à au moins un compte bancaire.");
    }

    if (round($totalBanques, 2) != round($montant_total, 2)) {
        die(
            "Erreur : la somme affectée aux banques doit être égale au montant total de la NP. " .
            "Montant NP : " . number_format($montant_total, 2, ',', ' ') . " CDF. " .
            "Total banques : " . number_format($totalBanques, 2, ',', ' ') . " CDF."
        );
    }

    try {
        $numero_np = genererNumero(
            'NP',
            $_SESSION['province_id'],
            $_SESSION['centre_id'],
            $pdo
        );

        $date_emission = date('Y-m-d H:i:s');
        $date_echeance = ajouterJoursOuvrables($date_emission, 8);

        $stmt = $pdo->prepare("
            INSERT INTO notes_perception
            (
                numero_np,
                note_debit_id,
                type_np,
                np_mere_id,
                avis_fractionnement_id,
                numero_tranche,
                declarant_nom,
                user_ordonnateur_id,
                montant_initial,
                montant_paye,
                solde_restant,
                penalite_assiette,
                penalite_recouvrement,
                date_emission,
                date_echeance,
                statut,
                annotation_autorite,
                sceau_appose
            )
            VALUES
            (
                ?, ?, 'globale',
                NULL, NULL, NULL,
                ?, ?,
                ?, 0, ?,
                ?, ?,
                ?, ?,
                'en_attente',
                NULL,
                ?
            )
        ");

        $stmt->execute([
            $numero_np,
            $nd['id'],
            $declarant_nom,
            $_SESSION['user_id'],
            $montant_total,
            $montant_total,
            $penalite_assiette,
            $penalite_recouvrement,
            $date_emission,
            $date_echeance,
            $sceau_appose
        ]);

        $note_perception_id = $pdo->lastInsertId();

        foreach ($repartition as $compteId => $montant) {
            $stmt = $pdo->prepare("
                INSERT INTO note_banques
                (
                    note_perception_id,
                    compte_bancaire_id,
                    montant_affecte,
                    observation
                )
                VALUES (?, ?, ?, ?)
            ");

            $stmt->execute([
                $note_perception_id,
                $compteId,
                $montant,
                "Affectation bancaire à la création de la NP"
            ]);
        }

        if ($pdo->query("SHOW TABLES LIKE 'audit_logs'")->fetch()) {
            $stmt = $pdo->prepare("
                INSERT INTO audit_logs
                (user_id, action, module, reference_document, details)
                VALUES (?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $_SESSION['user_id'] ?? null,
                "Création NP globale",
                "Ordonnancement",
                $numero_np,
                "Création de la NP depuis la ND " . $nd['numero_nd'] . " avec répartition bancaire."
            ]);
        }

        header("Location: np_view.php?numero=" . urlencode($numero_np));
        exit;

    } catch (Exception $e) {
        die("Erreur création NP : " . $e->getMessage());
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
.grid-2 {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 14px;
}

.info-box {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    color: #1e3a8a;
    padding: 14px;
    border-radius: 14px;
    font-weight: 800;
    margin-bottom: 18px;
}

.warning-box {
    background: #fff7ed;
    border: 1px solid #fed7aa;
    color: #9a3412;
    padding: 14px;
    border-radius: 14px;
    font-weight: 800;
    margin-bottom: 18px;
}

.amount-big {
    font-size: 28px;
    font-weight: 900;
    color: #0f3460;
}

label {
    font-weight: 900;
    color: #0f3460;
    display: block;
    margin-bottom: 6px;
}

.btn-luxoria {
    border: none;
    padding: 14px 22px;
    border-radius: 16px;
    font-weight: 900;
    cursor: pointer;
    background: linear-gradient(135deg, #0f3460, #06152b);
    color: white;
    box-shadow: 0 10px 22px rgba(15,52,96,.25);
    margin-top: 14px;
}

.btn-secondary-luxoria {
    display: inline-block;
    text-decoration: none;
    padding: 13px 18px;
    border-radius: 16px;
    font-weight: 900;
    border: 1px solid #0f3460;
    color: #0f3460;
    background: white;
    margin-top: 14px;
}

.action-zone {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
}

.bank-total {
    font-size: 20px;
    font-weight: 900;
    color: #0f3460;
    text-align: right;
    margin-top: 14px;
}
</style>
<link rel="stylesheet" href="../../assets/css/ordonnancement.css">
</head>

<body class="cp-ordonnancement-page">
<div class="admin-layout">

<?php require_once "../../includes/sidebar.php"; ?>

<main class="main-content">
<?php require_once "../../includes/topbar.php"; ?>

<div class="panel cp-panel cp-np-create-shell">
    <div class="cp-page-heading"><span class="cp-kicker">Ordonnancement</span><h2>Créer une Note de Perception Globale</h2><p>Génération d’une NP depuis une Note de Débit validée conforme.</p></div>

    <div class="info-box">
        Cette NP sera générée depuis une ND validée conforme.
        L’échéance est automatiquement fixée à 8 jours ouvrables.
    </div>

    <div class="cp-reference-grid"><p><strong>ND :</strong> <?= htmlspecialchars($nd['numero_nd']) ?></p>
    <p><strong>NT :</strong> <?= htmlspecialchars($nd['numero_nt']) ?></p>
    <p><strong>Contribuable :</strong> <?= htmlspecialchars(nomContribuableNP($nd)) ?></p>
    <p><strong>NIF :</strong> <?= htmlspecialchars($nd['nif'] ?? '-') ?></p></div>

    <table class="table-premium cp-ord-table">
        <tr>
            <th>Montant principal ND</th>
            <td><?= number_format($nd['montant_acte'] ?? 0, 2, ',', ' ') ?> CDF</td>
        </tr>
        <tr>
            <th>Frais administratifs</th>
            <td><?= number_format($nd['montant_frais_admin'] ?? 0, 2, ',', ' ') ?> CDF</td>
        </tr>
        <tr>
            <th>Frais techniques</th>
            <td><?= number_format($nd['montant_frais_tech'] ?? 0, 2, ',', ' ') ?> CDF</td>
        </tr>
        <tr>
            <th>Pénalité d’assiette</th>
            <td><?= number_format($penalite_assiette, 2, ',', ' ') ?> CDF</td>
        </tr>
        <tr>
            <th>Pénalité de recouvrement</th>
            <td><?= number_format($penalite_recouvrement, 2, ',', ' ') ?> CDF</td>
        </tr>
        <tr>
            <th>Total à percevoir</th>
            <td>
                <strong class="amount-big" id="montantTotalNP" data-total="<?= htmlspecialchars($montant_total) ?>">
                    <?= number_format($montant_total, 2, ',', ' ') ?> CDF
                </strong>
            </td>
        </tr>
    </table>

    <br>

    <form method="POST" class="cp-form">
        <div class="grid-2">
            <div>
                <label>Nom du déclarant / signataire</label>
                <input type="text"
                       name="declarant_nom"
                       placeholder="Nom complet du déclarant"
                       required>
            </div>

            <div>
                <label>Sceau</label>
                <label>
                    <input type="checkbox" name="sceau_appose" checked>
                    Sceau apposé sur la NP
                </label>
            </div>
        </div>

        <div class="panel cp-bank-panel">
            <h3>Répartition bancaire de la NP</h3>

            <div class="warning-box">
                La somme affectée aux comptes bancaires doit être exactement égale au montant total de la NP.
            </div>

            <table class="table-premium cp-ord-table">
                <tr>
                    <th>Banque</th>
                    <th>Compte</th>
                    <th>Devise</th>
                    <th>Intitulé</th>
                    <th>Montant affecté</th>
                </tr>

                <?php foreach ($banques as $b): ?>
                    <tr>
                        <td><?= htmlspecialchars($b['banque']) ?></td>
                        <td><?= htmlspecialchars($b['numero_compte']) ?></td>
                        <td><?= htmlspecialchars($b['devise']) ?></td>
                        <td><?= htmlspecialchars($b['intitule_compte'] ?? '-') ?></td>
                        <td>
                            <input type="number"
                                   step="0.01"
                                   min="0"
                                   class="bank-input"
                                   name="banques[<?= (int)$b['id'] ?>]"
                                   value="0">
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($banques)): ?>
                    <tr>
                        <td colspan="5">Aucun compte bancaire actif trouvé.</td>
                    </tr>
                <?php endif; ?>
            </table>

            <div class="bank-total">
                <span>Total réparti : <strong><span id="bankTotalDisplay">0,00</span> CDF</strong></span><span class="cp-bank-remaining">Reste à répartir : <strong><span id="bankRemainingDisplay">0,00</span> CDF</strong></span>
            </div>
        </div>

        <div class="action-zone">
            <button type="submit" class="btn-luxoria">
                Générer la NP Globale
            </button>

            <a href="../liquidation/nd_view.php?numero=<?= urlencode($nd['numero_nd']) ?>"
               class="btn-secondary-luxoria">
                Retour à la ND
            </a>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    const inputs = document.querySelectorAll('.bank-input');
    const display = document.getElementById('bankTotalDisplay');
    const remainingDisplay = document.getElementById('bankRemainingDisplay');
    const totalExpected = parseFloat(document.getElementById('montantTotalNP')?.dataset.total || '0');

    function formatNumber(n){
        return n.toLocaleString('fr-FR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function updateTotal(){
        let total = 0;

        inputs.forEach(function(input){
            total += parseFloat(input.value || 0);
        });

        display.textContent = formatNumber(total);
        if (remainingDisplay) remainingDisplay.textContent = formatNumber(Math.max(totalExpected - total, 0));
    }

    inputs.forEach(function(input){
        input.addEventListener('input', updateTotal);
    });

    updateTotal();
});
</script>

</main>
</div>
</body>
</html>