<?php
require_once "../../config/database.php";
require_once "../../config/security.php";
require_once "../../core/numero_generator.php";
require_once "../../core/penalite_engine.php";

checkAuth();

if (!function_exists('cpRecouvrementCurrentUserId')) {
    function cpRecouvrementCurrentUserId(PDO $pdo): int
    {
        $id = (int)(cpRecouvrementCurrentUserId($pdo) ?? 0);

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



$page_title = "Émission AMR";

$numero = $_GET['numero'] ?? ($_POST['numero'] ?? null);

if (!$numero) {
    die("Numéro NP / NPF obligatoire.");
}

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

function nomContribuableAMR($c)
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

function formatDateAMR($date)
{
    if (!$date) return '-';
    return date('d/m/Y', strtotime($date));
}

if (($np['statut'] ?? '') === 'payee') {
    die("Impossible d'émettre un AMR : cette note est déjà payée.");
}

if (empty($np['date_echeance'])) {
    die("Impossible d'émettre un AMR : date d'échéance manquante.");
}

$today = date('Y-m-d');
$echeance = date('Y-m-d', strtotime($np['date_echeance']));

if ($today <= $echeance) {
    die("Impossible d'émettre un AMR : cette note n'est pas encore échue.");
}

$stmt = $pdo->prepare("
    SELECT *
    FROM amr
    WHERE note_perception_id = ?
    AND reference_numero = ?
    LIMIT 1
");
$stmt->execute([
    $np['id'],
    $np['numero_np']
]);
$amrExistant = $stmt->fetch();

if ($amrExistant) {
    header("Location: amr_list.php?existing=1");
    exit;
}

$jours_retard = floor(
    (strtotime($today) - strtotime($echeance)) / 86400
);

/*
|--------------------------------------------------------------------------
| Base AMR
|--------------------------------------------------------------------------
| La pénalité est calculée sur le solde réel restant.
| Après émission AMR, la pénalité est ajoutée une seule fois au solde de la NP/NPF.
| Ainsi le paiement se fera toujours sur le montant global exigible :
| Solde NP + pénalité AMR.
|--------------------------------------------------------------------------
*/
$montant_principal = (float)($np['solde_restant'] ?? $np['montant_initial'] ?? 0);

$montant_penalite = calculerPenaliteProgressive(
    $montant_principal,
    $jours_retard,
    'recouvrement',
    $pdo
);

$montant_total = $montant_principal + $montant_penalite;

$numero_amr = $np['numero_np'];
$reference_type = (($np['type_np'] ?? '') === 'fractionnee') ? 'NPF' : 'NP';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $motif = trim($_POST['motif'] ?? '');

    if ($motif === '') {
        $motif = "AMR émis pour dépassement de l'échéance de paiement.";
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO amr
            (
                numero_amr,
                reference_type,
                reference_numero,
                note_perception_id,
                montant_principal,
                montant_penalite,
                montant_total,
                jours_retard,
                statut,
                motif,
                user_emission_id,
                date_emission
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'emis', ?, ?, NOW())
        ");

        $stmt->execute([
            $numero_amr,
            $reference_type,
            $np['numero_np'],
            $np['id'],
            $montant_principal,
            $montant_penalite,
            $montant_total,
            $jours_retard,
            $motif,
            cpRecouvrementCurrentUserId($pdo)
        ]);

        /*
        |--------------------------------------------------------------------------
        | Application de la pénalité AMR sur le solde de la note
        |--------------------------------------------------------------------------
        | Important : c'est ici que le paiement futur inclura automatiquement
        | toutes les pénalités de recouvrement AMR.
        |--------------------------------------------------------------------------
        */
        if ($montant_penalite > 0) {
            $stmt = $pdo->prepare("
                UPDATE notes_perception
                SET
                    montant_initial = IFNULL(montant_initial, 0) + ?,
                    solde_restant = IFNULL(solde_restant, 0) + ?,
                    statut = CASE
                        WHEN statut = 'payee' THEN statut
                        ELSE 'defaillante'
                    END
                WHERE id = ?
            ");
            $stmt->execute([
                $montant_penalite,
                $montant_penalite,
                $np['id']
            ]);

            /*
            |--------------------------------------------------------------------------
            | Si l'AMR concerne une NPF, on augmente aussi le solde de la NP mère.
            |--------------------------------------------------------------------------
            */
            if (($np['type_np'] ?? '') === 'fractionnee' && !empty($np['np_mere_id'])) {
                $stmt = $pdo->prepare("
                    UPDATE notes_perception
                    SET
                        montant_initial = IFNULL(montant_initial, 0) + ?,
                        solde_restant = IFNULL(solde_restant, 0) + ?,
                        statut = CASE
                            WHEN statut = 'payee' THEN 'partiellement_payee'
                            ELSE statut
                        END
                    WHERE id = ?
                ");
                $stmt->execute([
                    $montant_penalite,
                    $montant_penalite,
                    $np['np_mere_id']
                ]);
            }
        }

        $pdo->commit();

        header("Location: amr_list.php?success=1");
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        die("Erreur émission AMR : " . $e->getMessage());
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
.hero-amr{
    background:linear-gradient(135deg,#7f1d1d,#991b1b);
    color:white;
    padding:24px;
    border-radius:24px;
    margin-bottom:22px;
}
.hero-amr h2{margin:0;font-weight:900}
.hero-amr p{margin:8px 0 0;color:#fee2e2}
.warning-box{
    background:#fff7ed;
    color:#9a3412;
    border:1px solid #fdba74;
    padding:15px;
    border-radius:16px;
    font-weight:800;
    margin-bottom:18px;
}
.amount-big{
    font-size:26px;
    font-weight:900;
    color:#991b1b;
}
.grid-3{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:14px;
    margin-bottom:18px;
}
.stat-card{
    background:#f8fafc;
    border:1px solid #e5e7eb;
    border-radius:18px;
    padding:18px;
}
.stat-card span{
    display:block;
    color:#64748b;
    font-weight:800;
    margin-bottom:6px;
}
.stat-card strong{
    color:#06152b;
    font-size:20px;
}
.actions{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
}
.btn-danger{
    background:#991b1b;
}
.btn-secondary{
    background:#fbbf24;
    color:#111827;
}
@media(max-width:900px){
    .grid-3{grid-template-columns:1fr}
}
</style>
<link rel="stylesheet" href="../../assets/css/recouvrement.css">
</head>

<body class="cp-recouvrement-page">
<div class="admin-layout">

<?php require_once "../../includes/sidebar.php"; ?>

<main class="main-content">
<?php require_once "../../includes/topbar.php"; ?>

<div class="hero-amr">
    <h2>Émission d’un AMR</h2>
    <p>Avis de Mise en Recouvrement généré suite au dépassement de la date d’échéance.</p>
</div>

<div class="warning-box">
    Cette NP / NPF est échue. Après émission de l’AMR, la pénalité sera ajoutée au solde global à payer.
</div>

<div class="grid-3">
    <div class="stat-card">
        <span>Solde principal actuel</span>
        <strong><?= number_format($montant_principal, 2, ',', ' ') ?> CDF</strong>
    </div>

    <div class="stat-card">
        <span>Pénalité AMR calculée</span>
        <strong><?= number_format($montant_penalite, 2, ',', ' ') ?> CDF</strong>
    </div>

    <div class="stat-card">
        <span>Total global exigible</span>
        <strong class="amount-big"><?= number_format($montant_total, 2, ',', ' ') ?> CDF</strong>
    </div>
</div>

<div class="panel cp-rec-panel">
    <h3>Informations de la note concernée</h3>

    <table class="table-premium cp-rec-table">
        <tr>
            <th>Numéro AMR</th>
            <td><strong><?= htmlspecialchars($numero_amr) ?></strong></td>
        </tr>
        <tr>
            <th>Type référence</th>
            <td><?= htmlspecialchars($reference_type) ?></td>
        </tr>
        <tr>
            <th>Numéro NP / NPF</th>
            <td><strong><?= htmlspecialchars($np['numero_np']) ?></strong></td>
        </tr>
        <tr>
            <th>Contribuable</th>
            <td><?= htmlspecialchars(nomContribuableAMR($np)) ?></td>
        </tr>
        <tr>
            <th>NIF</th>
            <td><?= htmlspecialchars($np['nif'] ?? '-') ?></td>
        </tr>
        <tr>
            <th>ND</th>
            <td><?= htmlspecialchars($np['numero_nd']) ?></td>
        </tr>
        <tr>
            <th>NT</th>
            <td><?= htmlspecialchars($np['numero_nt']) ?></td>
        </tr>
        <tr>
            <th>Date échéance</th>
            <td><?= htmlspecialchars(formatDateAMR($np['date_echeance'])) ?></td>
        </tr>
        <tr>
            <th>Jours de retard</th>
            <td><strong><?= (int)$jours_retard ?> jour(s)</strong></td>
        </tr>
    </table>
</div>

<div class="panel cp-rec-panel">
    <h3>Confirmation émission AMR</h3>

    <form method="POST">
        <input type="hidden" name="numero" value="<?= htmlspecialchars($np['numero_np']) ?>">

        <label>Motif / Observation</label>
        <textarea name="motif" required>AMR émis pour dépassement de l'échéance de paiement.</textarea>

        <div class="warning-box">
            À la validation de ce formulaire, la pénalité AMR de
            <strong><?= number_format($montant_penalite, 2, ',', ' ') ?> CDF</strong>
            sera ajoutée au solde de la note. Le paiement devra ensuite couvrir
            le total global exigible.
        </div>

        <div class="actions">
            <button type="submit" class="btn-danger">
                Émettre l’AMR
            </button>

            <a href="../ordonnancement/np_list.php" class="btn btn-secondary">
                Retour
            </a>
        </div>
    </form>
</div>

</main>
</div>
</body>
</html>
