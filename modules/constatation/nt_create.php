<?php
require_once "../../auth/check_auth.php";
require_once "../../core/functions.php";
require_once "../../core/numero_generator.php";



$page_title = "Nouvelle Note de Taxation";

$contribuable_id = $_GET['contribuable_id'] ?? null;

if (!$contribuable_id) {
    die("Contribuable obligatoire.");
}

function nomContribuableNTCreate($c)
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

function countParamNTCreate($pdo, $sql)
{
    try {
        $row = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
        return (int)($row['total'] ?? 0);
    } catch (Exception $e) {
        return 0;
    }
}

/*
|--------------------------------------------------------------------------
| Vérification paramétrage minimum
|--------------------------------------------------------------------------
*/

$totalDirections = countParamNTCreate($pdo, "
    SELECT COUNT(*) total FROM directions WHERE actif = 1
");

$totalServices = countParamNTCreate($pdo, "
    SELECT COUNT(*) total FROM services_assiette WHERE actif = 1
");

$totalArticles = countParamNTCreate($pdo, "
    SELECT COUNT(*) total FROM articles_budgetaires WHERE actif = 1
");

$totalTauxChange = countParamNTCreate($pdo, "
    SELECT COUNT(*) total
    FROM taux_change_officiel
    WHERE devise = 'USD'
    AND actif = 1
");

$parametrageOK =
    $totalDirections > 0 &&
    $totalServices > 0 &&
    $totalArticles > 0 &&
    $totalTauxChange > 0;

/*
|--------------------------------------------------------------------------
| Contribuable
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM contribuables
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$contribuable_id]);
$contribuable = $stmt->fetch();

if (!$contribuable) {
    die("Contribuable introuvable.");
}

/*
|--------------------------------------------------------------------------
| Services d'assiette
|--------------------------------------------------------------------------
*/

$services = $pdo->query("
    SELECT 
        s.*,
        c.nom AS centre_nom
    FROM services_assiette s
    LEFT JOIN centres c ON s.centre_id = c.id
    WHERE s.actif = 1
    ORDER BY s.nom_service ASC
")->fetchAll();

/*
|--------------------------------------------------------------------------
| Création en-tête NT
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!$parametrageOK) {
        die("Impossible de créer une NT : le paramétrage fiscal est incomplet.");
    }

    $service_id = $_POST['service_id'] ?? null;
    $exercice   = $_POST['exercice'] ?? date('Y');

    if (!$service_id) {
        die("Service d’assiette obligatoire.");
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM services_assiette
        WHERE id = ?
        AND actif = 1
        LIMIT 1
    ");
    $stmt->execute([$service_id]);
    $service = $stmt->fetch();

    if (!$service) {
        die("Service d’assiette invalide.");
    }

    $province_id = $_SESSION['province_id'];
    $centre_id   = $_SESSION['centre_id'];

    $numero_nt = genererNumero('NT', $province_id, $centre_id, $pdo);

    $stmt = $pdo->prepare("
        INSERT INTO notes_taxation
        (
            numero_nt,
            contribuable_id,
            centre_id,
            service_id,
            exercice,
            devise,
            taux_change,
            user_taxateur_id
        )
        VALUES
        (?, ?, ?, ?, ?, 'CDF', 1, ?)
    ");

    $stmt->execute([
        $numero_nt,
        $contribuable_id,
        $centre_id,
        $service_id,
        $exercice,
        $_SESSION['user_id']
    ]);

    header("Location: nt_view.php?numero=" . urlencode($numero_nt));
    exit;
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
.info-box{
    background:#f8fafc;
    border:1px solid #e5e7eb;
    padding:18px;
    border-radius:16px;
    margin-bottom:22px;
}
.warning-box{
    background:#fff7ed;
    color:#9a3412;
    border:1px solid #fed7aa;
    padding:14px;
    border-radius:14px;
    margin-bottom:18px;
    font-weight:800;
}
.success-box{
    background:#ecfdf5;
    color:#047857;
    border:1px solid #bbf7d0;
    padding:14px;
    border-radius:14px;
    margin-bottom:18px;
    font-weight:800;
}
.check-list{
    margin-top:10px;
    line-height:1.8;
}
.btn-param{
    display:inline-block;
    margin-top:12px;
    background:#0f3460;
    color:white;
    padding:10px 16px;
    border-radius:12px;
    text-decoration:none;
    font-weight:900;
}
.badge-info{
    display:inline-block;
    background:#dbeafe;
    color:#1e40af;
    padding:6px 12px;
    border-radius:999px;
    font-weight:900;
    font-size:12px;
}
</style>
<link rel="stylesheet" href="../../assets/css/constatation.css">
</head>

<body class="cp-constatation-page cp-nt-create">
<div class="admin-layout">

<?php require_once "../../includes/sidebar.php"; ?>

<main class="main-content">

<?php require_once "../../includes/topbar.php"; ?>

<div class="panel cp-nt-shell">
    <div class="cp-page-heading">
        <div>
            <span class="cp-eyebrow">Constatation fiscale</span>
            <h3>Créer une Note de Taxation</h3>
            <p>Renseignez le service d’assiette et l’exercice pour ouvrir une nouvelle NT.</p>
        </div>
    </div>

    <?php if ($parametrageOK): ?>
        <div class="success-box">
            ✅ Paramétrage fiscal complet. La création de la NT est autorisée.
        </div>
    <?php else: ?>
        <div class="warning-box">
            ⚠️ Impossible de créer correctement la NT : le paramétrage fiscal est incomplet.

            <div class="check-list">
                <?= $totalDirections > 0 ? "✅" : "❌" ?> Directions configurées<br>
                <?= $totalServices > 0 ? "✅" : "❌" ?> Services d’assiette configurés<br>
                <?= $totalArticles > 0 ? "✅" : "❌" ?> Nomenclature fiscale configurée<br>
                <?= $totalTauxChange > 0 ? "✅" : "❌" ?> Taux de change officiel actif
            </div>

            <a class="btn-param" href="../parametrage/index.php">
                Aller au paramétrage
            </a>
        </div>
    <?php endif; ?>

    <div class="info-box cp-taxpayer-card">
        <strong>Contribuable :</strong>
        <?= htmlspecialchars(nomContribuableNTCreate($contribuable)) ?><br>

        <strong>Type personne :</strong>
        <span class="badge-info">
            <?= strtoupper(htmlspecialchars($contribuable['type_personne'] ?? '-')) ?>
        </span><br>

        <strong>Code contribuable :</strong>
        <?= htmlspecialchars($contribuable['code_contribuable'] ?? '-') ?><br>

        <strong>NIF :</strong>
        <?= htmlspecialchars($contribuable['nif'] ?? 'NON ATTRIBUE') ?><br>

        <strong>RCCM / Patente :</strong>
        <?= htmlspecialchars($contribuable['rccm'] ?? '-') ?><br>

        <strong>Téléphone :</strong>
        <?= htmlspecialchars($contribuable['telephone'] ?? '-') ?>
    </div>

    <form method="POST" class="cp-nt-form">

        <label>Service d’assiette</label>
        <select name="service_id" required <?= !$parametrageOK ? 'disabled' : '' ?>>
            <option value="">-- Sélectionner le service d’assiette --</option>

            <?php foreach ($services as $s): ?>
                <option value="<?= (int)$s['id'] ?>">
                    <?= htmlspecialchars($s['nom_service']) ?>
                    <?php if (!empty($s['centre_nom'])): ?>
                        — Centre : <?= htmlspecialchars($s['centre_nom']) ?>
                    <?php endif; ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Exercice</label>
        <input
            type="number"
            name="exercice"
            value="<?= date('Y') ?>"
            required
            <?= !$parametrageOK ? 'disabled' : '' ?>
        >

        <button type="submit" <?= !$parametrageOK ? 'disabled' : '' ?>>
            Créer la Note de Taxation
        </button>
    </form>

    <div class="cp-form-footer">
    <a class="cp-back-link" href="../contribuables/view.php?id=<?= (int)$contribuable['id'] ?>">
        ← Retour à la fiche contribuable
    </a>
    </div>
</div>

</main>
</div>
</body>
</html>