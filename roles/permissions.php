<?php
require_once "../auth/check_auth.php";
require_once "../auth/permissions.php";

requirePermission('roles', 'permissions');

$db = cpDb();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $db->prepare("SELECT * FROM roles WHERE id=? LIMIT 1");
$stmt->execute([$id]);
$role = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$role) {
    die("Rôle introuvable.");
}

/*
|--------------------------------------------------------------------------
| Sécurisation matrice permissions
|--------------------------------------------------------------------------
*/
$matrix = collectPayPermissionMatrix();

if (!is_array($matrix)) {
    $matrix = [];
}

/*
|--------------------------------------------------------------------------
| Normalisation permissions existantes
|--------------------------------------------------------------------------
| Corrige :
| Cannot access offset of type string on string
|--------------------------------------------------------------------------
*/
function cpNormalizeRolePermissions($permissions): array
{
    if (is_string($permissions)) {
        $decoded = json_decode($permissions, true);
        $permissions = is_array($decoded) ? $decoded : [];
    }

    if (!is_array($permissions)) {
        return [];
    }

    $clean = [];

    foreach ($permissions as $module => $actions) {
        if (is_string($actions)) {
            $decodedActions = json_decode($actions, true);
            $actions = is_array($decodedActions) ? $decodedActions : [];
        }

        if (!is_array($actions)) {
            $actions = [];
        }

        foreach ($actions as $action => $value) {
            if (is_array($value)) {
                $value = $value['autorise'] ?? $value['allowed'] ?? $value['value'] ?? 0;
            }

            $clean[(string)$module][(string)$action] = (int)$value;
        }
    }

    return $clean;
}

function cpIsPermissionChecked(array $permissions, string $module, string $action): bool
{
    return isset($permissions[$module][$action]) && (int)$permissions[$module][$action] === 1;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($matrix as $module => $info) {
        if (empty($info['actions']) || !is_array($info['actions'])) {
            continue;
        }

        $ordre = 0;

        foreach ($info['actions'] as $action => $label) {
            $ordre++;

            $key = $module . '__' . $action;
            $autorise = isset($_POST['permissions'][$key]) ? 1 : 0;

            setPermission($id, $module, $action, $autorise, $label, $ordre);
        }
    }

    logAction('roles', 'permissions', "Mise à jour permissions rôle : " . ($role['nom_role'] ?? ''));
    header("Location: permissions.php?id=" . $id . "&saved=1");
    exit;
}

$currentRaw = getRolePermissions($id);
$current = cpNormalizeRolePermissions($currentRaw);

$page_title = "Permissions rôle";
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Permissions rôle | cOllect_Pay</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="../assets/css/admin.css">

<style>
.permissions-page{
    max-width:1200px;
    margin:0 auto;
}
.permissions-header{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:16px;
    margin-bottom:18px;
}
.permissions-header h2{
    margin:0;
    color:#06152b;
    font-size:26px;
    font-weight:1000;
}
.permissions-header p{
    margin:5px 0 0;
    color:#64748b;
    font-weight:750;
}
.card-premium{
    background:#fff;
    padding:24px;
    border-radius:26px;
    box-shadow:0 18px 45px rgba(15,23,42,.09);
    border:1px solid #e5e7eb;
}
.module{
    border:1px solid #e5e7eb;
    border-radius:20px;
    padding:18px;
    margin-bottom:16px;
    background:#f8fafc;
}
.module h3{
    margin:0 0 14px;
    color:#0f3460;
    font-size:17px;
    font-weight:1000;
}
.perm-grid{
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:12px;
}
.perm{
    background:white;
    border:1px solid #e5e7eb;
    border-radius:15px;
    padding:12px;
    font-weight:850;
    display:flex;
    align-items:center;
    gap:10px;
    cursor:pointer;
}
.perm:hover{
    background:#eff6ff;
    border-color:#bfdbfe;
}
.perm input{
    width:18px;
    height:18px;
    cursor:pointer;
}
.btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    margin-top:16px;
    padding:12px 18px;
    border-radius:15px;
    text-decoration:none;
    font-weight:1000;
    border:none;
    cursor:pointer;
}
.btn-primary{
    background:linear-gradient(135deg,#0f3460,#06152b);
    color:white;
}
.btn-secondary{
    background:#e5e7eb;
    color:#111827;
}
.success{
    background:#dcfce7;
    color:#166534;
    padding:13px 15px;
    border-radius:16px;
    margin-bottom:16px;
    font-weight:900;
    border:1px solid #bbf7d0;
}
.actions{
    display:flex;
    gap:12px;
    flex-wrap:wrap;
    justify-content:flex-end;
    border-top:1px solid #e5e7eb;
    padding-top:16px;
}
.quick-actions{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    margin-bottom:16px;
}
.quick-actions button{
    border:none;
    border-radius:14px;
    padding:10px 13px;
    font-weight:900;
    cursor:pointer;
    background:#eff6ff;
    color:#1e3a8a;
}
@media(max-width:900px){
    .perm-grid{
        grid-template-columns:1fr;
    }
    .permissions-header{
        flex-direction:column;
        align-items:flex-start;
    }
}
</style>
</head>

<body>
<?php require_once "../includes/sidebar.php"; ?>

<main class="main-content">
    <?php
    $topbar = __DIR__ . "/../includes/topbar.php";
    if (file_exists($topbar)) {
        require_once $topbar;
    }
    ?>

    <div class="permissions-page">
        <div class="permissions-header">
            <div>
                <h2>Permissions : <?= htmlspecialchars($role['nom_role'] ?? '') ?></h2>
                <p>Cochez les actions autorisées pour ce rôle.</p>
            </div>

            <a class="btn btn-secondary" href="index.php">← Retour rôles</a>
        </div>

        <div class="card-premium">

            <?php if(isset($_GET['saved'])): ?>
                <div class="success">✅ Permissions enregistrées avec succès.</div>
            <?php endif; ?>

            <div class="quick-actions">
                <button type="button" onclick="cpCheckAllPermissions(true)">Tout cocher</button>
                <button type="button" onclick="cpCheckAllPermissions(false)">Tout décocher</button>
            </div>

            <form method="post">
                <?php foreach($matrix as $module => $info): ?>
                    <?php
                    if (empty($info['actions']) || !is_array($info['actions'])) {
                        continue;
                    }
                    ?>
                    <div class="module">
                        <h3><?= htmlspecialchars($info['label'] ?? $module) ?></h3>

                        <div class="perm-grid">
                            <?php foreach($info['actions'] as $action => $label): ?>
                                <?php
                                    $checked = cpIsPermissionChecked($current, (string)$module, (string)$action);
                                    $key = $module . '__' . $action;
                                ?>
                                <label class="perm">
                                    <input type="checkbox"
                                           class="perm-check"
                                           name="permissions[<?= htmlspecialchars($key) ?>]"
                                           value="1"
                                           <?= $checked ? 'checked' : '' ?>>
                                    <span><?= htmlspecialchars($label) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="actions">
                    <a class="btn btn-secondary" href="index.php">Retour</a>
                    <button class="btn btn-primary" type="submit">💾 Enregistrer les permissions</button>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
function cpCheckAllPermissions(state){
    document.querySelectorAll('.perm-check').forEach(function(input){
        input.checked = state;
    });
}
</script>

</body>
</html>
