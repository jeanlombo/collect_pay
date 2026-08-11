<?php
require_once "../../config/database.php";
require_once "../../config/security.php";
require_once "../../core/numero_generator.php";


$page_title = "Créer Note de Débit";

$numero_nt = $_GET['numero_nt'] ?? null;

if (!$numero_nt) {
    die("Numéro NT obligatoire.");
}

$stmt = $pdo->prepare("
    SELECT 
        nt.*,
        c.raison_sociale,
        c.nom,
        c.postnom,
        c.prenom,
        c.nif,
        c.telephone
    FROM notes_taxation nt
    JOIN contribuables c ON nt.contribuable_id = c.id
    WHERE nt.numero_nt = ?
    LIMIT 1
");
$stmt->execute([$numero_nt]);
$nt = $stmt->fetch();

if (!$nt) {
    die("NT introuvable.");
}

if ($nt['statut'] !== 'en_attente_liquidation') {
    die("Cette NT n’est pas en attente de liquidation.");
}

/*
|--------------------------------------------------------------------------
| Empêcher la duplication d'une ND pour la même NT
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT numero_nd
    FROM notes_debit
    WHERE note_taxation_id = ?
    LIMIT 1
");
$stmt->execute([$nt['id']]);
$existing = $stmt->fetch();

if ($existing) {
    header("Location: nd_view.php?numero=" . urlencode($existing['numero_nd']));
    exit;
}

/*
|--------------------------------------------------------------------------
| Totaux à liquider
|--------------------------------------------------------------------------
| IMPORTANT :
| Les colonnes montant_acte, montant_frais_admin, montant_frais_tech et
| total_ligne_cdf sont déjà enregistrées en CDF dans notes_taxation_details.
| On ne multiplie donc plus par taux_change ici.
*/
$stmt = $pdo->prepare("
    SELECT
        IFNULL(SUM(montant_acte), 0) AS principal_cdf,
        IFNULL(SUM(montant_frais_admin), 0) AS frais_admin_cdf,
        IFNULL(SUM(montant_frais_tech), 0) AS frais_tech_cdf,
        IFNULL(SUM(total_ligne_cdf), 0) AS total_actes_cdf,
        COUNT(*) AS nb_details
    FROM notes_taxation_details
    WHERE note_taxation_id = ?
");
$stmt->execute([$nt['id']]);
$totaux = $stmt->fetch();

if ((int)$totaux['nb_details'] <= 0) {
    die("Impossible de liquider une NT sans acte taxable.");
}

$penalite_assiette = (float)($nt['penalite_assiette'] ?? 0);
$penalite_recouvrement = (float)($nt['penalite_recouvrement'] ?? 0);

$principal_cdf = (float)$totaux['principal_cdf'];
$frais_admin_cdf = (float)$totaux['frais_admin_cdf'];
$frais_tech_cdf = (float)$totaux['frais_tech_cdf'];
$total_actes_cdf = (float)$totaux['total_actes_cdf'];

$total_general = $total_actes_cdf + $penalite_assiette + $penalite_recouvrement;

function nomContribuableNDCreate($c) {
    if (!empty($c['raison_sociale'])) {
        return $c['raison_sociale'];
    }

    return trim(($c['nom'] ?? '') . ' ' . ($c['postnom'] ?? '') . ' ' . ($c['prenom'] ?? ''));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $observation = trim($_POST['observation'] ?? '');

    /*
    |--------------------------------------------------------------------------
    | Centre et province de la NT
    |--------------------------------------------------------------------------
    | Ne pas dépendre de province_id / centre_id dans la session :
    | sur Railway ces clés peuvent ne pas être présentes.
    | La NT contient déjà son centre_id ; la province est récupérée
    | directement depuis la table centres.
    */
    $centre_id = (int)($nt['centre_id'] ?? 0);

    if ($centre_id <= 0) {
        die("Centre de la Note de Taxation introuvable.");
    }

    $stmtCentre = $pdo->prepare("
        SELECT province_id
        FROM centres
        WHERE id = ?
        LIMIT 1
    ");
    $stmtCentre->execute([$centre_id]);
    $centreInfo = $stmtCentre->fetch(PDO::FETCH_ASSOC);

    $province_id = (int)($centreInfo['province_id'] ?? 0);

    if ($province_id <= 0) {
        die("Province liée au centre de taxation introuvable.");
    }

    $numero_nd = genererNumero(
        'ND',
        $province_id,
        $centre_id,
        $pdo
    );

    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare("
            INSERT INTO notes_debit
            (
                numero_nd,
                note_taxation_id,
                date_liquidation,
                total_exigible,
                penalite_assiette,
                penalite_recouvrement,
                statut,
                observation,
                user_liquidateur_id,
                montant_acte,
                montant_frais_admin,
                montant_frais_tech,
                montant_total
            )
            VALUES
            (?, ?, CURDATE(), ?, ?, ?, 'en_controle', ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $numero_nd,
            $nt['id'],
            $total_general,
            $penalite_assiette,
            $penalite_recouvrement,
            $observation,
            $_SESSION['user_id'],
            $principal_cdf,
            $frais_admin_cdf,
            $frais_tech_cdf,
            $total_general
        ]);

        $stmt = $pdo->prepare("
            UPDATE notes_taxation
            SET statut = 'liquidee'
            WHERE id = ?
        ");
        $stmt->execute([$nt['id']]);

        $pdo->commit();

        header("Location: nd_view.php?numero=" . urlencode($numero_nd));
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Erreur création ND : " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($page_title) ?> | cOllect_Pay</title>
    <link rel="stylesheet" href="../../assets/css/admin.css">
<link rel="stylesheet" href="../../assets/css/liquidation.css">
</head>
<body class="cp-liquidation-page cp-nd-create">

<div class="admin-layout">
    <?php require_once "../../includes/sidebar.php"; ?>

    <main class="main-content">
        <?php require_once "../../includes/topbar.php"; ?>

        <div class="panel cp-liquidation-panel">
            <h3>Liquidation — Génération de la Note de Débit</h3>

            <p><strong>NT :</strong> <?= htmlspecialchars($nt['numero_nt']) ?></p>
            <p><strong>Contribuable :</strong> <?= htmlspecialchars(nomContribuableNDCreate($nt)) ?></p>
            <p><strong>NIF :</strong> <?= htmlspecialchars($nt['nif'] ?? '-') ?></p>

            <div class="cp-table-wrap"><table class="table-premium">
                <tr>
                    <th>Principal dû</th>
                    <td><?= number_format($principal_cdf, 2, ',', ' ') ?> CDF</td>
                </tr>
                <tr>
                    <th>Frais administratifs</th>
                    <td><?= number_format($frais_admin_cdf, 2, ',', ' ') ?> CDF</td>
                </tr>
                <tr>
                    <th>Frais techniques</th>
                    <td><?= number_format($frais_tech_cdf, 2, ',', ' ') ?> CDF</td>
                </tr>
                <tr>
                    <th>Total actes liquidables</th>
                    <td><strong><?= number_format($total_actes_cdf, 2, ',', ' ') ?> CDF</strong></td>
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
                    <th>Total général à liquider</th>
                    <td><strong><?= number_format($total_general, 2, ',', ' ') ?> CDF</strong></td>
                </tr>
            </table></div>

            <br>

            <form method="POST">
                <textarea name="observation"
                          placeholder="Observation du liquidateur..."
                          style="min-height:120px;"></textarea>

                <button type="submit">Générer la Note de Débit</button>
            </form>
        </div>

    </main>
</div>

</body>
</html>
