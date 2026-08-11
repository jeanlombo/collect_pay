<?php
require_once "../../config/database.php";
require_once "../../config/security.php";

checkAuth();

requireRole([
    'SUPER_ADMIN',
    'ADMIN',
    'PARAMETRAGE',
    'CHEF_CENTRE'
]);

$id = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    die("Article budgétaire introuvable.");
}

function tableColumnsNom(PDO $pdo, string $table): array
{
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `$table`");
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
    } catch (Exception $e) {
        return [];
    }
}

function updateDynamicNom(PDO $pdo, string $table, array $data, int $id): void
{
    $cols = tableColumnsNom($pdo, $table);

    $sets = [];
    $values = [];

    foreach ($data as $col => $val) {
        if (in_array($col, $cols, true)) {
            $sets[] = "`$col` = ?";
            $values[] = $val;
        }
    }

    if (empty($sets)) {
        throw new Exception("Aucune colonne valide à modifier.");
    }

    $values[] = $id;

    $sql = "UPDATE `$table` SET " . implode(", ", $sets) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);
}

function fetchOptionsNom(PDO $pdo, string $table): array
{
    try {
        $stmt = $pdo->query("SELECT * FROM `$table` ORDER BY id DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

$colsArticles = tableColumnsNom($pdo, 'articles_budgetaires');

$stmt = $pdo->prepare("SELECT * FROM articles_budgetaires WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$article = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$article) {
    die("Article budgétaire introuvable.");
}

$directions = fetchOptionsNom($pdo, 'directions');
$services   = fetchOptionsNom($pdo, 'services_assiette');

if (!$services) {
    $services = fetchOptionsNom($pdo, 'services');
}

$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $code_article = trim($_POST['code_article'] ?? '');
        $secteur = trim($_POST['secteur'] ?? '');
        $nature_acte = trim($_POST['nature_acte'] ?? '');
        $fait_generateur = trim($_POST['fait_generateur'] ?? '');
        $periodicite = trim($_POST['periodicite'] ?? 'ponctuelle');
        $type_taux = trim($_POST['type_taux'] ?? 'fixe');
        $mode_calcul = trim($_POST['mode_calcul'] ?? $_POST['type_calcul'] ?? $type_taux);
        $taux_acte = (float)($_POST['taux_acte'] ?? 0);
        $frais_administratif = (float)($_POST['frais_administratif'] ?? 0);
        $frais_technique = (float)($_POST['frais_technique'] ?? 0);
        $unite = trim($_POST['unite'] ?? '');
        $devise_base = trim($_POST['devise_base'] ?? 'CDF');
        $direction_id = !empty($_POST['direction_id']) ? (int)$_POST['direction_id'] : null;
        $service_id = !empty($_POST['service_id']) ? (int)$_POST['service_id'] : null;
        $formule_personnalisee = trim($_POST['formule_personnalisee'] ?? '');

        $textCheck = mb_strtolower($code_article . ' ' . $nature_acte . ' ' . $secteur, 'UTF-8');

        if (
            $type_taux === 'pourcentage' ||
            $mode_calcul === 'pourcentage' ||
            str_contains($textCheck, 'irl') ||
            str_contains($textCheck, 'revenu locatif') ||
            str_contains($textCheck, 'retenu locative') ||
            str_contains($textCheck, 'retenue locative') ||
            preg_match('/\brl\b/i', $textCheck)
        ) {
            $type_taux = 'pourcentage';
            $mode_calcul = 'pourcentage';
            $devise_base = '%';
        }

        if ($code_article === '') {
            throw new Exception("Le code article est obligatoire.");
        }

        if ($nature_acte === '') {
            throw new Exception("La nature de l'acte est obligatoire.");
        }

        $data = [
            'code_article' => $code_article,
            'secteur' => $secteur,
            'nature_acte' => $nature_acte,
            'fait_generateur' => $fait_generateur,
            'periodicite' => $periodicite,
            'type_taux' => $type_taux,
            'mode_calcul' => $mode_calcul,
            'type_calcul' => $mode_calcul,
            'taux_acte' => $taux_acte,
            'frais_administratif' => $frais_administratif,
            'frais_technique' => $frais_technique,
            'unite' => $unite,
            'devise_base' => $devise_base,
            'direction_id' => $direction_id,
            'service_id' => $service_id,
            'formule_personnalisee' => $formule_personnalisee,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        updateDynamicNom($pdo, 'articles_budgetaires', $data, $id);

        header("Location: nomenclature.php?success=Article modifié avec succès");
        exit;

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

function selectedNom($a, $b): string
{
    return (string)$a === (string)$b ? 'selected' : '';
}

function valueNom($article, $key, $default = ''): string
{
    return htmlspecialchars((string)($article[$key] ?? $default), ENT_QUOTES, 'UTF-8');
}

$page_title = "Modifier article budgétaire";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Modifier nomenclature | cOllect_Pay</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="../../assets/css/admin.css">

<style>
body{
    background:#f4f7fb;
    font-family:'Segoe UI',Arial,sans-serif;
}
.form-card{
    background:white;
    border-radius:24px;
    padding:26px;
    box-shadow:0 18px 45px rgba(15,23,42,.08);
    border:1px solid #e5e7eb;
}
.page-header{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:15px;
    margin-bottom:22px;
}
.page-header h3{
    margin:0;
    font-weight:1000;
    color:#06152b;
}
.help-box{
    background:#eff6ff;
    border:1px solid #bfdbfe;
    color:#1e3a8a;
    padding:14px;
    border-radius:16px;
    font-weight:700;
    margin-bottom:18px;
}
label{
    font-weight:900;
    color:#0f172a;
    margin-bottom:7px;
}
.form-control,.form-select{
    border-radius:14px;
    padding:12px 14px;
}
.btn-save{
    background:linear-gradient(135deg,#0f3460,#06152b);
    border:none;
    color:white;
    border-radius:14px;
    padding:12px 20px;
    font-weight:1000;
}
.btn-back{
    background:#e5e7eb;
    color:#111827;
    border-radius:14px;
    padding:12px 20px;
    text-decoration:none;
    font-weight:900;
}
.badge-percent{
    background:#dcfce7;
    color:#166534;
    border-radius:999px;
    padding:7px 12px;
    font-weight:1000;
}
</style>
<link rel="stylesheet" href="../../assets/css/parametrage.css">
</head>

<body class="cp-parametrage-page">

<?php
$sidebar = __DIR__ . "/../../includes/sidebar.php";
$topbar = __DIR__ . "/../../includes/topbar.php";
?>

<div class="admin-layout">
    <?php if (file_exists($sidebar)) require_once $sidebar; ?>

    <main class="main-content">
        <?php if (file_exists($topbar)) require_once $topbar; ?>

        <div class="page-header">
            <div>
                <h3>Modifier un article budgétaire</h3>
                <p class="text-muted mb-0">
                    Mise à jour de la nomenclature fiscale.
                </p>
            </div>

            <a href="nomenclature.php" class="btn-back">← Retour</a>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="help-box">
            Pour les IRL/RL ou les taux en pourcentage, utilisez :
            <span class="badge-percent">Devise = %</span>
            avec un taux comme <strong>15</strong> pour 15%, <strong>10</strong> pour 10%, ou <strong>2</strong> pour 2%.
        </div>

        <div class="form-card">
            <form method="POST">
                <input type="hidden" name="id" value="<?= (int)$id ?>">

                <div class="row g-3">

                    <div class="col-md-3">
                        <label>Code article</label>
                        <input type="text" name="code_article" class="form-control" value="<?= valueNom($article, 'code_article') ?>" required>
                    </div>

                    <div class="col-md-3">
                        <label>Secteur</label>
                        <input type="text" name="secteur" class="form-control" value="<?= valueNom($article, 'secteur') ?>">
                    </div>

                    <div class="col-md-3">
                        <label>Direction</label>
                        <select name="direction_id" class="form-select">
                            <option value="">Non précisée</option>
                            <?php foreach ($directions as $d): ?>
                                <option value="<?= (int)$d['id'] ?>" <?= selectedNom($article['direction_id'] ?? '', $d['id']) ?>>
                                    <?= htmlspecialchars($d['nom'] ?? $d['libelle'] ?? $d['nom_direction'] ?? ('Direction #' . $d['id'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label>Service</label>
                        <select name="service_id" class="form-select">
                            <option value="">Non précisé</option>
                            <?php foreach ($services as $s): ?>
                                <option value="<?= (int)$s['id'] ?>" <?= selectedNom($article['service_id'] ?? '', $s['id']) ?>>
                                    <?= htmlspecialchars($s['nom'] ?? $s['libelle'] ?? $s['nom_service'] ?? ('Service #' . $s['id'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-12">
                        <label>Nature de l'acte</label>
                        <textarea name="nature_acte" id="nature_acte" rows="3" class="form-control" required><?= valueNom($article, 'nature_acte') ?></textarea>
                    </div>

                    <div class="col-md-12">
                        <label>Fait générateur</label>
                        <textarea name="fait_generateur" rows="2" class="form-control"><?= valueNom($article, 'fait_generateur') ?></textarea>
                    </div>

                    <div class="col-md-3">
                        <label>Périodicité</label>
                        <select name="periodicite" class="form-select">
                            <?php
                            $periodicites = [
                                'ponctuelle' => 'Ponctuelle',
                                'mensuelle' => 'Mensuelle',
                                'trimestrielle' => 'Trimestrielle',
                                'semestrielle' => 'Semestrielle',
                                'annuelle' => 'Annuelle'
                            ];
                            ?>
                            <?php foreach ($periodicites as $k => $v): ?>
                                <option value="<?= $k ?>" <?= selectedNom($article['periodicite'] ?? '', $k) ?>>
                                    <?= $v ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label>Type de taux</label>
                        <select name="type_taux" id="type_taux" class="form-select">
                            <option value="fixe" <?= selectedNom($article['type_taux'] ?? '', 'fixe') ?>>Fixe</option>
                            <option value="pourcentage" <?= selectedNom($article['type_taux'] ?? '', 'pourcentage') ?>>Pourcentage</option>
                            <option value="mixte" <?= selectedNom($article['type_taux'] ?? '', 'mixte') ?>>Mixte</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label>Mode de calcul</label>
                        <?php $modeValue = $article['mode_calcul'] ?? ($article['type_calcul'] ?? ($article['type_taux'] ?? 'fixe')); ?>
                        <select name="mode_calcul" id="mode_calcul" class="form-select">
                            <option value="fixe" <?= selectedNom($modeValue, 'fixe') ?>>Fixe</option>
                            <option value="par_unite" <?= selectedNom($modeValue, 'par_unite') ?>>Par unité</option>
                            <option value="pourcentage" <?= selectedNom($modeValue, 'pourcentage') ?>>Pourcentage</option>
                            <option value="mixte" <?= selectedNom($modeValue, 'mixte') ?>>Mixte</option>
                            <option value="irl" <?= selectedNom($modeValue, 'irl') ?>>IRL</option>
                            <option value="rl" <?= selectedNom($modeValue, 'rl') ?>>RL</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label>Devise / Unité du taux</label>
                        <select name="devise_base" id="devise_base" class="form-select">
                            <option value="CDF" <?= selectedNom($article['devise_base'] ?? '', 'CDF') ?>>CDF</option>
                            <option value="USD" <?= selectedNom($article['devise_base'] ?? '', 'USD') ?>>USD</option>
                            <option value="%" <?= selectedNom($article['devise_base'] ?? '', '%') ?>>%</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label>Taux de l'acte</label>
                        <input type="number" step="0.000001" name="taux_acte" class="form-control" value="<?= valueNom($article, 'taux_acte', 0) ?>" placeholder="Ex : 15 pour 15%">
                    </div>

                    <div class="col-md-3">
                        <label>Frais administratif</label>
                        <input type="number" step="0.000001" name="frais_administratif" class="form-control" value="<?= valueNom($article, 'frais_administratif', 0) ?>">
                    </div>

                    <div class="col-md-3">
                        <label>Frais technique</label>
                        <input type="number" step="0.000001" name="frais_technique" class="form-control" value="<?= valueNom($article, 'frais_technique', 0) ?>">
                    </div>

                    <div class="col-md-3">
                        <label>Unité</label>
                        <input type="text" name="unite" class="form-control" value="<?= valueNom($article, 'unite') ?>" placeholder="Ex : dossier, acte, m²...">
                    </div>

                    <div class="col-md-12">
                        <label>Formule personnalisée</label>
                        <textarea name="formule_personnalisee" rows="2" class="form-control"><?= valueNom($article, 'formule_personnalisee') ?></textarea>
                    </div>

                    <div class="col-12 d-flex gap-2 justify-content-end mt-4">
                        <a href="nomenclature.php" class="btn-back">Annuler</a>
                        <button type="submit" class="btn-save">💾 Enregistrer les modifications</button>
                    </div>

                </div>
            </form>
        </div>

    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const typeTaux = document.getElementById('type_taux');
    const modeCalcul = document.getElementById('mode_calcul');
    const devise = document.getElementById('devise_base');
    const nature = document.getElementById('nature_acte');

    function isLocatif() {
        const txt = (nature ? nature.value : '').toLowerCase();

        return txt.includes('irl') ||
               txt.includes('revenu locatif') ||
               txt.includes('retenu locative') ||
               txt.includes('retenue locative') ||
               /\brl\b/.test(txt);
    }

    function syncDevise() {
        const percent =
            (typeTaux && typeTaux.value === 'pourcentage') ||
            (modeCalcul && ['pourcentage', 'irl', 'rl'].includes(modeCalcul.value)) ||
            isLocatif();

        if (percent) {
            if (devise) devise.value = '%';
            if (typeTaux) typeTaux.value = 'pourcentage';

            if (isLocatif() && modeCalcul && modeCalcul.value === 'fixe') {
                modeCalcul.value = 'pourcentage';
            }
        }
    }

    [typeTaux, modeCalcul, devise, nature].forEach(el => {
        if (!el) return;
        el.addEventListener('change', syncDevise);
        el.addEventListener('keyup', syncDevise);
    });

    syncDevise();
});
</script>

</body>
</html>
