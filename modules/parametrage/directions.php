<?php
require_once "../../config/database.php";
require_once "../../config/security.php";
require_once "../../core/functions.php";

checkAuth();

$page_title = "Directions / Ministères";
$message = "";
$error = "";

function cpColumnExistsDirection(PDO $pdo, string $table, string $column): bool
{
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $stmt->execute([$column]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return false;
    }
}

$hasVisibleTaxation = cpColumnExistsDirection($pdo, 'directions', 'visible_taxation');
$hasVisiblePwa = cpColumnExistsDirection($pdo, 'directions', 'visible_pwa');

/*
 * Les migrations SQL ne doivent pas être exécutées depuis une page Web.
 * Les colonnes sont seulement détectées ici pour rester compatible avec
 * différentes versions de la base.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? 'create';

        if ($action === 'create') {
            $nom_direction = trim($_POST['nom_direction'] ?? '');
            $code_direction = strtoupper(trim($_POST['code_direction'] ?? ''));

            if (!$nom_direction || !$code_direction) {
                throw new Exception("Le nom et le code de la direction sont obligatoires.");
            }

            $visible_taxation = isset($_POST['visible_taxation']) ? 1 : 0;
            $visible_pwa = isset($_POST['visible_pwa']) ? 1 : 0;

            $stmt = $pdo->prepare("
                INSERT INTO directions
                (nom_direction, code_direction, actif, visible_taxation, visible_pwa)
                VALUES (?, ?, 1, ?, ?)
                ON DUPLICATE KEY UPDATE
                    nom_direction = VALUES(nom_direction),
                    actif = 1,
                    visible_taxation = VALUES(visible_taxation),
                    visible_pwa = VALUES(visible_pwa)
            ");
            $stmt->execute([
                $nom_direction,
                $code_direction,
                $visible_taxation,
                $visible_pwa
            ]);

            $message = "Direction enregistrée avec succès.";
        }

        if ($action === 'bulk_update') {
            $ids = $_POST['direction_ids'] ?? [];
            $visibleTaxationList = $_POST['visible_taxation'] ?? [];
            $visiblePwaList = $_POST['visible_pwa'] ?? [];
            $actifList = $_POST['actif'] ?? [];

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                UPDATE directions
                SET visible_taxation = ?, visible_pwa = ?, actif = ?
                WHERE id = ?
            ");

            foreach ($ids as $id) {
                $id = (int)$id;

                if ($id <= 0) {
                    continue;
                }

                $visibleTaxation = isset($visibleTaxationList[$id]) ? 1 : 0;
                $visiblePwa = isset($visiblePwaList[$id]) ? 1 : 0;
                $actif = isset($actifList[$id]) ? 1 : 0;

                $stmt->execute([
                    $visibleTaxation,
                    $visiblePwa,
                    $actif,
                    $id
                ]);
            }

            $pdo->commit();

            $message = "Visibilité des directions mise à jour avec succès.";
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $error = $e->getMessage();
    }
}

$search = trim($_GET['q'] ?? '');
$filter = $_GET['filter'] ?? '';

$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(nom_direction LIKE ? OR code_direction LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($filter === 'pwa') {
    $where[] = "visible_pwa = 1";
}

if ($filter === 'taxation') {
    $where[] = "visible_taxation = 1";
}

if ($filter === 'hidden_pwa') {
    $where[] = "visible_pwa = 0";
}

$sql = "SELECT * FROM directions";

if ($where) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY nom_direction ASC, id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$directions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalDirections = count($directions);
$totalTaxation = 0;
$totalPwa = 0;

foreach ($directions as $d) {
    if (!empty($d['visible_taxation'])) {
        $totalTaxation++;
    }

    if (!empty($d['visible_pwa'])) {
        $totalPwa++;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($page_title) ?> | cOllect_Pay</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../../assets/css/admin.css">

<style>
.page-grid{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:16px;
    margin-bottom:22px;
}
.stat-card{
    background:#fff;
    border-radius:22px;
    padding:20px;
    box-shadow:0 15px 35px rgba(15,23,42,.08);
    border-left:6px solid #0f3460;
}
.stat-card span{
    color:#64748b;
    font-weight:800;
}
.stat-card h2{
    margin:8px 0 0;
    color:#06152b;
    font-size:30px;
    font-weight:1000;
}
.alert-ok{
    background:#dcfce7;
    color:#166534;
    padding:12px 14px;
    border-radius:14px;
    font-weight:900;
    margin-bottom:14px;
}
.alert-error{
    background:#fee2e2;
    color:#991b1b;
    padding:12px 14px;
    border-radius:14px;
    font-weight:900;
    margin-bottom:14px;
}
.form-grid{
    display:grid;
    grid-template-columns:1fr 220px;
    gap:14px;
}
.check-wrap{
    display:flex;
    gap:16px;
    flex-wrap:wrap;
    margin:10px 0;
}
.check-line{
    display:flex;
    align-items:center;
    gap:8px;
    background:#f8fafc;
    border:1px solid #e5e7eb;
    padding:10px 12px;
    border-radius:14px;
    font-weight:900;
    color:#0f172a;
}
.check-line input{
    width:auto;
}
.toolbar{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    flex-wrap:wrap;
    margin-bottom:15px;
}
.toolbar form{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    align-items:center;
}
.toolbar input,
.toolbar select{
    padding:11px 13px;
    border:1px solid #d1d5db;
    border-radius:13px;
    min-width:230px;
}
.btn-premium{
    background:linear-gradient(135deg,#0f3460,#06152b);
    color:#fff!important;
    border:none;
    padding:12px 16px;
    border-radius:14px;
    font-weight:1000;
    cursor:pointer;
    text-decoration:none;
    display:inline-block;
}
.btn-save{
    background:#16a34a;
}
.badge-ok{
    background:#dcfce7;
    color:#166534;
    padding:6px 10px;
    border-radius:999px;
    font-weight:900;
    font-size:12px;
}
.badge-no{
    background:#fee2e2;
    color:#991b1b;
    padding:6px 10px;
    border-radius:999px;
    font-weight:900;
    font-size:12px;
}
.switch-cell{
    text-align:center;
}
.switch-cell input{
    transform:scale(1.35);
    cursor:pointer;
}
.table-premium td,
.table-premium th{
    vertical-align:middle;
}
.quick-note{
    background:#eff6ff;
    color:#1e3a8a;
    padding:12px 14px;
    border-radius:14px;
    margin-bottom:16px;
    font-weight:800;
}
@media(max-width:900px){
    .page-grid{grid-template-columns:1fr}
    .form-grid{grid-template-columns:1fr}
}
</style>
<link rel="stylesheet" href="../../assets/css/parametrage.css">
</head>

<body class="cp-parametrage-page">

<div class="admin-layout">
    <?php require_once "../../includes/sidebar.php"; ?>

    <main class="main-content">
        <?php require_once "../../includes/topbar.php"; ?>

        <div class="page-grid">
            <div class="stat-card">
                <span>Total directions</span>
                <h2><?= (int)$totalDirections ?></h2>
            </div>

            <div class="stat-card">
                <span>Visibles Taxation Web</span>
                <h2><?= (int)$totalTaxation ?></h2>
            </div>

            <div class="stat-card">
                <span>Synchronisées PWA</span>
                <h2><?= (int)$totalPwa ?></h2>
            </div>
        </div>

        <div class="panel cp-parametrage-panel">
            <h3>Ajouter une Direction / Ministère</h3>

            <?php if ($message): ?>
                <div class="alert-ok"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="action" value="create">

                <div class="form-grid">
                    <input type="text" name="nom_direction" placeholder="Nom direction ex: Direction Impôt" required>
                    <input type="text" name="code_direction" placeholder="Code ex: IMPOT" required>
                </div>

                <div class="check-wrap">
                    <label class="check-line">
                        <input type="checkbox" name="visible_taxation" checked>
                        Visible dans la taxation Web
                    </label>

                    <label class="check-line">
                        <input type="checkbox" name="visible_pwa">
                        Visible dans la PWA Mobile
                    </label>
                </div>

                <button type="submit" class="btn-premium">Enregistrer</button>
            </form>
        </div>

        <div class="panel cp-parametrage-panel">
            <div class="toolbar">
                <div>
                    <h3>Gestion des Directions / Ministères</h3>
                    <p class="quick-note">
                        Coche les ministères à afficher dans la taxation Web et ceux à synchroniser dans la PWA.
                    </p>
                </div>

                <form method="GET">
                    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Recherche direction ou code">

                    <select name="filter">
                        <option value="">Tous</option>
                        <option value="taxation" <?= $filter === 'taxation' ? 'selected' : '' ?>>Visible Taxation</option>
                        <option value="pwa" <?= $filter === 'pwa' ? 'selected' : '' ?>>Visible PWA</option>
                        <option value="hidden_pwa" <?= $filter === 'hidden_pwa' ? 'selected' : '' ?>>Non visible PWA</option>
                    </select>

                    <button class="btn-premium" type="submit">Filtrer</button>
                </form>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="bulk_update">

                <table class="table-premium cp-parametrage-table">
                    <tr>
                        <th>ID</th>
                        <th>Code</th>
                        <th>Direction / Ministère</th>
                        <th>Taxation Web</th>
                        <th>PWA Mobile</th>
                        <th>Actif</th>
                        <th>État</th>
                    </tr>

                    <?php if (!$directions): ?>
                        <tr>
                            <td colspan="7" style="text-align:center;color:#64748b;font-weight:900;">
                                Aucune direction trouvée.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach($directions as $d): ?>
                        <?php $id = (int)$d['id']; ?>
                        <tr>
                            <td>
                                <?= $id ?>
                                <input type="hidden" name="direction_ids[]" value="<?= $id ?>">
                            </td>

                            <td>
                                <strong><?= htmlspecialchars($d['code_direction'] ?? '') ?></strong>
                            </td>

                            <td><?= htmlspecialchars($d['nom_direction'] ?? '') ?></td>

                            <td class="switch-cell">
                                <input type="checkbox"
                                       name="visible_taxation[<?= $id ?>]"
                                       value="1"
                                       <?= !empty($d['visible_taxation']) ? 'checked' : '' ?>>
                            </td>

                            <td class="switch-cell">
                                <input type="checkbox"
                                       name="visible_pwa[<?= $id ?>]"
                                       value="1"
                                       <?= !empty($d['visible_pwa']) ? 'checked' : '' ?>>
                            </td>

                            <td class="switch-cell">
                                <input type="checkbox"
                                       name="actif[<?= $id ?>]"
                                       value="1"
                                       <?= !empty($d['actif']) ? 'checked' : '' ?>>
                            </td>

                            <td>
                                <?= !empty($d['visible_taxation']) ? '<span class="badge-ok">Taxation</span>' : '<span class="badge-no">Taxation masquée</span>' ?>
                                <?= !empty($d['visible_pwa']) ? '<span class="badge-ok">PWA</span>' : '<span class="badge-no">PWA non</span>' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>

                <br>

                <button type="submit" class="btn-premium btn-save">
                    💾 Enregistrer les modifications
                </button>
            </form>
        </div>

    </main>
</div>

</body>
</html>
