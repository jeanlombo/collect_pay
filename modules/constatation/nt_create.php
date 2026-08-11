<?php
require_once "../../auth/check_auth.php";
require_once "../../core/functions.php";
require_once "../../core/numero_generator.php";



$page_title = "Nouvelle Note de Taxation";

$contribuable_id = isset($_GET['contribuable_id']) && $_GET['contribuable_id'] !== ''
    ? (int)$_GET['contribuable_id']
    : null;

$mode_selection_contribuable = !$contribuable_id;
$recherche_contribuable = trim((string)($_GET['q'] ?? ''));
$contribuables = [];

if ($mode_selection_contribuable) {
    $sqlContribuables = "
        SELECT
            id,
            type_personne,
            code_contribuable,
            raison_sociale,
            nom,
            postnom,
            prenom,
            nif,
            rccm,
            telephone,
            ville,
            statut
        FROM contribuables
        WHERE 1=1
    ";

    $paramsContribuables = [];

    if ($recherche_contribuable !== '') {
        $sqlContribuables .= "
            AND (
                raison_sociale LIKE ?
                OR nom LIKE ?
                OR postnom LIKE ?
                OR prenom LIKE ?
                OR code_contribuable LIKE ?
                OR nif LIKE ?
                OR telephone LIKE ?
                OR rccm LIKE ?
            )
        ";

        $like = '%' . $recherche_contribuable . '%';
        $paramsContribuables = [$like,$like,$like,$like,$like,$like,$like,$like];
    }

    $sqlContribuables .= "
        ORDER BY
            CASE
                WHEN raison_sociale IS NOT NULL AND raison_sociale <> '' THEN raison_sociale
                ELSE CONCAT_WS(' ', nom, postnom, prenom)
            END ASC
        LIMIT 250
    ";

    $stmtContribuables = $pdo->prepare($sqlContribuables);
    $stmtContribuables->execute($paramsContribuables);
    $contribuables = $stmtContribuables->fetchAll(PDO::FETCH_ASSOC);
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

$contribuable = null;

if (!$mode_selection_contribuable) {
    $stmt = $pdo->prepare("
        SELECT *
        FROM contribuables
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$contribuable_id]);
    $contribuable = $stmt->fetch();

    if (!$contribuable) {
        http_response_code(404);
        die("Contribuable introuvable.");
    }
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$mode_selection_contribuable) {

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
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($page_title) ?> | cOllect_Pay</title>
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

.nt-selection-wrap{
    width:min(1180px,calc(100% - 28px));
    margin:20px auto;
}
.nt-selection-hero{
    position:relative;
    overflow:hidden;
    background:linear-gradient(135deg,#0b2340,#0f4d7a 58%,#166da1);
    color:#fff;
    padding:24px 26px;
    border-radius:20px;
    box-shadow:0 14px 32px rgba(15,52,82,.15);
    margin-bottom:16px;
}
.nt-selection-hero::after{
    content:"";
    position:absolute;
    width:240px;
    height:240px;
    border-radius:50%;
    right:-100px;
    top:-110px;
    background:rgba(255,204,37,.12);
}
.nt-selection-hero small{
    display:block;
    font-size:10px;
    font-weight:900;
    letter-spacing:.1em;
    color:#b9dbef;
    text-transform:uppercase;
}
.nt-selection-hero h2{
    margin:6px 0;
    font-size:27px;
    font-weight:950;
}
.nt-selection-hero p{
    margin:0;
    max-width:760px;
    color:#dceaf4;
    font-weight:650;
    line-height:1.5;
}
.nt-search-card{
    background:#fff;
    border:1px solid #dde7ef;
    border-radius:17px;
    padding:17px;
    box-shadow:0 8px 24px rgba(18,53,83,.05);
    margin-bottom:14px;
}
.nt-search-form{
    display:flex;
    gap:10px;
    align-items:center;
}
.nt-search-form input{
    flex:1;
    min-width:0;
    height:46px;
    border:1px solid #cddbe6;
    border-radius:12px;
    padding:0 14px;
    font:inherit;
}
.nt-search-form input:focus{
    outline:none;
    border-color:#4e96c5;
    box-shadow:0 0 0 4px rgba(52,132,186,.10);
}
.nt-search-form button,
.nt-search-form a{
    min-height:46px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    border:0;
    border-radius:12px;
    padding:0 16px;
    text-decoration:none;
    font-weight:900;
}
.nt-search-form button{
    background:linear-gradient(135deg,#12466e,#176b9c);
    color:#fff;
}
.nt-search-form a{
    background:#eef3f7;
    color:#36566e;
}
.nt-count{
    margin-top:10px;
    color:#728497;
    font-size:11px;
    font-weight:800;
}
.nt-contribuables-grid{
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:12px;
}
.nt-contribuable-card{
    min-width:0;
    background:#fff;
    border:1px solid #dde7ef;
    border-radius:16px;
    padding:16px;
    box-shadow:0 7px 20px rgba(18,53,83,.045);
    transition:.2s ease;
}
.nt-contribuable-card:hover{
    transform:translateY(-2px);
    border-color:#a9cadf;
    box-shadow:0 12px 26px rgba(18,53,83,.08);
}
.nt-contribuable-head{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:10px;
    margin-bottom:12px;
}
.nt-contribuable-head h3{
    margin:0;
    color:#183b55;
    font-size:15px;
    line-height:1.35;
    overflow-wrap:anywhere;
}
.nt-type-badge{
    flex:0 0 auto;
    display:inline-flex;
    padding:5px 8px;
    border-radius:999px;
    background:#eaf4fb;
    color:#17608a;
    font-size:9px;
    font-weight:900;
}
.nt-contribuable-data{
    display:grid;
    gap:6px;
    margin-bottom:13px;
}
.nt-contribuable-data div{
    display:flex;
    justify-content:space-between;
    gap:10px;
    font-size:11px;
    border-bottom:1px dashed #edf1f4;
    padding-bottom:5px;
}
.nt-contribuable-data span{
    color:#798b9b;
}
.nt-contribuable-data strong{
    color:#29475e;
    text-align:right;
    overflow-wrap:anywhere;
}
.nt-taxer-btn{
    display:flex;
    width:100%;
    min-height:42px;
    align-items:center;
    justify-content:center;
    border-radius:11px;
    background:linear-gradient(135deg,#f0b707,#ffd44a);
    color:#172f42;
    text-decoration:none;
    font-weight:950;
    font-size:12px;
}
.nt-empty{
    grid-column:1/-1;
    text-align:center;
    background:#fff;
    border:1px dashed #cfdce6;
    border-radius:16px;
    padding:34px 20px;
    color:#728497;
}
.nt-change-contribuable{
    display:inline-flex;
    margin-top:10px;
    padding:7px 10px;
    border-radius:9px;
    background:#edf4f8;
    color:#235579;
    text-decoration:none;
    font-size:11px;
    font-weight:900;
}
@media(max-width:1000px){
    .nt-contribuables-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
}
@media(max-width:700px){
    .nt-selection-wrap{width:calc(100% - 16px)}
    .nt-contribuables-grid{grid-template-columns:1fr}
    .nt-search-form{display:grid;grid-template-columns:1fr}
    .nt-selection-hero{padding:18px}
    .nt-selection-hero h2{font-size:22px}
}

</style>
</head>

<body>
<div class="admin-layout">

<?php require_once "../../includes/sidebar.php"; ?>

<main class="main-content">

<?php require_once "../../includes/topbar.php"; ?>

<?php if ($mode_selection_contribuable): ?>

<div class="nt-selection-wrap">
    <section class="nt-selection-hero">
        <small>Constatation fiscale</small>
        <h2>Choisir le contribuable à taxer</h2>
        <p>
            Sélectionnez d’abord le contribuable concerné. Après votre choix,
            cOllect_Pay ouvrira le formulaire de création de la Note de Taxation.
        </p>
    </section>

    <section class="nt-search-card">
        <form method="GET" class="nt-search-form">
            <input
                type="search"
                name="q"
                value="<?= htmlspecialchars($recherche_contribuable) ?>"
                placeholder="Rechercher par nom, raison sociale, code, NIF, RCCM ou téléphone..."
                autocomplete="off"
            >
            <button type="submit">Rechercher</button>
            <?php if ($recherche_contribuable !== ''): ?>
                <a href="nt_create.php">Afficher tous</a>
            <?php endif; ?>
        </form>

        <div class="nt-count">
            <?= count($contribuables) ?> contribuable(s) affiché(s)
            <?php if (count($contribuables) >= 250): ?>
                — utilisez la recherche pour affiner la liste
            <?php endif; ?>
        </div>
    </section>

    <section class="nt-contribuables-grid">
        <?php foreach ($contribuables as $c): ?>
            <?php $nomAffiche = nomContribuableNTCreate($c); ?>
            <article class="nt-contribuable-card">
                <div class="nt-contribuable-head">
                    <h3><?= htmlspecialchars($nomAffiche !== '' ? $nomAffiche : 'Contribuable sans nom') ?></h3>
                    <span class="nt-type-badge">
                        <?= strtoupper(htmlspecialchars($c['type_personne'] ?? '-')) ?>
                    </span>
                </div>

                <div class="nt-contribuable-data">
                    <div><span>Code</span><strong><?= htmlspecialchars($c['code_contribuable'] ?? '-') ?></strong></div>
                    <div><span>NIF</span><strong><?= htmlspecialchars($c['nif'] ?? 'NON ATTRIBUÉ') ?></strong></div>
                    <div><span>RCCM / Patente</span><strong><?= htmlspecialchars($c['rccm'] ?? '-') ?></strong></div>
                    <div><span>Téléphone</span><strong><?= htmlspecialchars($c['telephone'] ?? '-') ?></strong></div>
                    <div><span>Ville</span><strong><?= htmlspecialchars($c['ville'] ?? '-') ?></strong></div>
                </div>

                <a class="nt-taxer-btn" href="nt_create.php?contribuable_id=<?= (int)$c['id'] ?>">
                    Taxer ce contribuable
                </a>
            </article>
        <?php endforeach; ?>

        <?php if (!$contribuables): ?>
            <div class="nt-empty">
                <strong>Aucun contribuable trouvé.</strong><br>
                Modifiez votre recherche ou créez d’abord le contribuable dans le module Contribuables.
            </div>
        <?php endif; ?>
    </section>
</div>

<?php else: ?>


<div class="panel">
    <h3>Créer une Note de Taxation</h3>

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

    <div class="info-box">
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

    <a href="nt_create.php" class="nt-change-contribuable">
        ← Changer de contribuable
    </a>


    <form method="POST">

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

    <br>

    <a href="../contribuables/view.php?id=<?= (int)$contribuable['id'] ?>">
        ← Retour à la fiche contribuable
    </a>
</div>

<?php endif; ?>

</main>
</div>
</body>
</html>