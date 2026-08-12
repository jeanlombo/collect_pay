<?php
/*
|--------------------------------------------------------------------------
| cOllect_Pay - Administration des permissions V2
|--------------------------------------------------------------------------
| Correctif :
| - aucun Duplicate entry sur (role_id,module,action)
| - ON DUPLICATE KEY UPDATE
| - Super Administrateur protégé : il possède automatiquement tous les accès
| - fonctionnement PDO conforme au projet CollectPay
|--------------------------------------------------------------------------
*/

require_once "../../auth/check_auth.php";

checkAuth();
requirePermission('administration', 'permissions');

$db = cpDb();

$page_title = "Gestion des permissions";
$message = "";
$error = "";

function eAdminPerm($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function normaliserPermissionAdmin(string $value): string
{
    $value = mb_strtolower(trim($value), 'UTF-8');
    $value = preg_replace('/[^a-z0-9_ -]+/u', '', $value) ?? $value;
    return trim($value);
}

function roleEstSuperAdminAdmin(PDO $db, int $roleId): bool
{
    if ($roleId <= 0) {
        return false;
    }

    $stmt = $db->prepare("SELECT nom_role FROM roles WHERE id=? LIMIT 1");
    $stmt->execute([$roleId]);
    $nom = (string)($stmt->fetchColumn() ?: '');

    if (function_exists('cpNormalizeRole')) {
        $nom = cpNormalizeRole($nom);
    } else {
        $nom = strtoupper(trim($nom));
    }

    return in_array($nom, [
        'SUPER_ADMIN',
        'SUPER_ADMINISTRATEUR',
        'SUPERADMIN',
        'SUPERADMINISTRATEUR'
    ], true);
}

/*
|--------------------------------------------------------------------------
| Rôle sélectionné
|--------------------------------------------------------------------------
*/
$role_id = (int)($_GET['role_id'] ?? $_POST['role_id'] ?? 0);

$rolesStmt = $db->query("
    SELECT id, nom_role, description, statut
    FROM roles
    WHERE statut='actif'
    ORDER BY nom_role ASC
");
$roles = $rolesStmt->fetchAll(PDO::FETCH_ASSOC);

if ($role_id <= 0 && $roles) {
    $role_id = (int)$roles[0]['id'];
}

$roleSelectionne = null;

if ($role_id > 0) {
    $stmtRole = $db->prepare("
        SELECT id, nom_role, description, statut
        FROM roles
        WHERE id=?
        LIMIT 1
    ");
    $stmtRole->execute([$role_id]);
    $roleSelectionne = $stmtRole->fetch(PDO::FETCH_ASSOC) ?: null;
}

$isSelectedSuperAdmin = $roleSelectionne
    ? roleEstSuperAdminAdmin($db, (int)$roleSelectionne['id'])
    : false;

/*
|--------------------------------------------------------------------------
| Ajouter / réactiver une permission
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actionForm = (string)($_POST['form_action'] ?? 'save_permission');

    try {
        if ($actionForm === 'save_permission') {
            $roleIdPost = (int)($_POST['role_id'] ?? 0);
            $module = normaliserPermissionAdmin((string)($_POST['module'] ?? ''));
            $action = normaliserPermissionAdmin((string)($_POST['action_permission'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $autorise = isset($_POST['autorise']) ? 1 : 0;
            $ordre = max(0, (int)($_POST['ordre'] ?? 0));

            if ($roleIdPost <= 0 || $module === '' || $action === '') {
                throw new RuntimeException("Rôle, module et action sont obligatoires.");
            }

            $checkRole = $db->prepare("SELECT id FROM roles WHERE id=? LIMIT 1");
            $checkRole->execute([$roleIdPost]);

            if (!$checkRole->fetchColumn()) {
                throw new RuntimeException("Rôle introuvable.");
            }

            /*
             * Le Super Administrateur n'a pas besoin de permissions explicites.
             * On n'empêche pas l'affichage du rôle mais on évite de créer
             * des lignes inutiles qui pourraient ensuite prêter à confusion.
             */
            if (roleEstSuperAdminAdmin($db, $roleIdPost)) {
                $message = "Le Super Administrateur possède automatiquement tous les accès. Aucune permission individuelle n’est nécessaire.";
                $role_id = $roleIdPost;
            } else {
                $sql = "
                    INSERT INTO permissions
                    (
                        role_id,
                        module,
                        action,
                        description,
                        autorise,
                        ordre,
                        created_at,
                        updated_at
                    )
                    VALUES
                    (
                        ?, ?, ?, ?, ?, ?, NOW(), NOW()
                    )
                    ON DUPLICATE KEY UPDATE
                        description = VALUES(description),
                        autorise = VALUES(autorise),
                        ordre = VALUES(ordre),
                        updated_at = NOW()
                ";

                $stmt = $db->prepare($sql);
                $stmt->execute([
                    $roleIdPost,
                    $module,
                    $action,
                    $description !== '' ? $description : null,
                    $autorise,
                    $ordre
                ]);

                $message = "Permission enregistrée avec succès.";
                $role_id = $roleIdPost;
            }
        }

        if ($actionForm === 'toggle_permission') {
            $permissionId = (int)($_POST['permission_id'] ?? 0);
            $roleIdPost = (int)($_POST['role_id'] ?? 0);

            if ($permissionId <= 0 || $roleIdPost <= 0) {
                throw new RuntimeException("Permission invalide.");
            }

            if (roleEstSuperAdminAdmin($db, $roleIdPost)) {
                $message = "Le Super Administrateur possède automatiquement tous les accès.";
                $role_id = $roleIdPost;
            } else {
                $stmt = $db->prepare("
                    UPDATE permissions
                    SET autorise = IF(COALESCE(autorise,1)=1,0,1),
                        updated_at = NOW()
                    WHERE id=?
                      AND role_id=?
                ");
                $stmt->execute([$permissionId, $roleIdPost]);

                $message = "Statut de la permission mis à jour.";
                $role_id = $roleIdPost;
            }
        }

        if ($actionForm === 'delete_permission') {
            $permissionId = (int)($_POST['permission_id'] ?? 0);
            $roleIdPost = (int)($_POST['role_id'] ?? 0);

            if ($permissionId <= 0 || $roleIdPost <= 0) {
                throw new RuntimeException("Permission invalide.");
            }

            if (roleEstSuperAdminAdmin($db, $roleIdPost)) {
                $message = "Le Super Administrateur possède automatiquement tous les accès.";
                $role_id = $roleIdPost;
            } else {
                $stmt = $db->prepare("
                    DELETE FROM permissions
                    WHERE id=?
                      AND role_id=?
                ");
                $stmt->execute([$permissionId, $roleIdPost]);

                $message = "Permission supprimée.";
                $role_id = $roleIdPost;
            }
        }
    } catch (Throwable $e) {
        $error = "Erreur : " . $e->getMessage();
    }

    // Recharger le rôle après POST.
    if ($role_id > 0) {
        $stmtRole = $db->prepare("
            SELECT id, nom_role, description, statut
            FROM roles
            WHERE id=?
            LIMIT 1
        ");
        $stmtRole->execute([$role_id]);
        $roleSelectionne = $stmtRole->fetch(PDO::FETCH_ASSOC) ?: null;
        $isSelectedSuperAdmin = $roleSelectionne
            ? roleEstSuperAdminAdmin($db, (int)$roleSelectionne['id'])
            : false;
    }
}

/*
|--------------------------------------------------------------------------
| Permissions du rôle sélectionné
|--------------------------------------------------------------------------
*/
$permissions = [];

if ($role_id > 0) {
    $stmtPermissions = $db->prepare("
        SELECT
            id,
            role_id,
            module,
            action,
            description,
            COALESCE(autorise,1) AS autorise,
            COALESCE(ordre,0) AS ordre,
            created_at,
            updated_at
        FROM permissions
        WHERE role_id=?
        ORDER BY module ASC, ordre ASC, action ASC
    ");
    $stmtPermissions->execute([$role_id]);
    $permissions = $stmtPermissions->fetchAll(PDO::FETCH_ASSOC);
}

$modules = [];
foreach ($permissions as $permission) {
    $modules[$permission['module']][] = $permission;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= eAdminPerm($page_title) ?> | cOllect_Pay</title>
<link rel="stylesheet" href="../../assets/css/admin.css">
<style>
.permissions-page{width:min(1250px,calc(100% - 28px));margin:20px auto 40px}
.permissions-hero{background:linear-gradient(135deg,#071b31,#104c79);color:#fff;padding:22px 24px;border-radius:18px;margin-bottom:14px;box-shadow:0 12px 28px rgba(10,45,75,.14)}
.permissions-hero h2{margin:0 0 6px;font-size:25px}.permissions-hero p{margin:0;color:#d4e5f1}
.permission-alert{padding:12px 14px;border-radius:12px;margin-bottom:12px;font-weight:800;font-size:13px}.permission-alert.success{background:#dcfce7;color:#166534;border:1px solid #bbf7d0}.permission-alert.error{background:#fee2e2;color:#991b1b;border:1px solid #fecaca}.permission-alert.info{background:#eaf4fb;color:#155779;border:1px solid #c9e3f3}
.permission-panel{background:#fff;border:1px solid #e0e7ee;border-radius:17px;padding:17px;margin-bottom:14px;box-shadow:0 7px 20px rgba(15,23,42,.045)}
.permission-filter{display:flex;gap:10px;align-items:end}.permission-filter label{flex:1;font-size:12px;font-weight:900;color:#415b70}.permission-filter select,.permission-grid-form input,.permission-grid-form textarea{width:100%;box-sizing:border-box;border:1px solid #ccd9e3;border-radius:11px;padding:11px 12px;margin-top:6px;font:inherit}
.permission-filter button,.permission-btn{border:0;border-radius:11px;padding:11px 15px;background:#0f4f7d;color:#fff;font-weight:900;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center}
.permission-grid-form{display:grid;grid-template-columns:1fr 1fr 2fr .65fr auto;gap:10px;align-items:end}.permission-grid-form label{font-size:11px;font-weight:900;color:#526b7e}.permission-checkbox{display:flex!important;gap:7px;align-items:center;height:42px}.permission-checkbox input{width:auto!important;margin:0!important}.permission-table{width:100%;border-collapse:collapse}.permission-table th{background:#edf4f8;text-align:left;padding:10px;font-size:10px;text-transform:uppercase;color:#526b7e}.permission-table td{padding:10px;border-top:1px solid #edf1f4;font-size:12px;vertical-align:middle}.permission-badge{display:inline-block;padding:5px 8px;border-radius:999px;font-size:9px;font-weight:900}.permission-badge.on{background:#dcfce7;color:#166534}.permission-badge.off{background:#fee2e2;color:#991b1b}.permission-actions{display:flex;gap:6px}.permission-actions form{margin:0}.permission-actions button{border:0;border-radius:8px;padding:7px 9px;font-size:10px;font-weight:900;cursor:pointer}.toggle-btn{background:#eaf4fb;color:#155779}.delete-btn{background:#fee2e2;color:#991b1b}.permission-module-title{margin:18px 0 8px;color:#163c57;font-size:15px}.permission-empty{text-align:center;padding:30px;color:#718497;border:1px dashed #ccd9e3;border-radius:14px}
@media(max-width:900px){.permission-grid-form{grid-template-columns:1fr 1fr}.permission-filter{display:grid;grid-template-columns:1fr}.permission-table-wrap{overflow-x:auto}.permission-table{min-width:800px}}
@media(max-width:560px){.permission-grid-form{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="admin-layout">
<?php require_once "../../includes/sidebar.php"; ?>
<main class="main-content">
<?php require_once "../../includes/topbar.php"; ?>

<div class="permissions-page">
    <section class="permissions-hero">
        <h2>Permissions & rôles</h2>
        <p>Gestion centralisée des droits d'accès de cOllect_Pay.</p>
    </section>

    <?php if ($message): ?>
        <div class="permission-alert success"><?= eAdminPerm($message) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="permission-alert error"><?= eAdminPerm($error) ?></div>
    <?php endif; ?>

    <section class="permission-panel">
        <form method="GET" class="permission-filter">
            <label>
                Rôle à configurer
                <select name="role_id">
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= (int)$r['id'] ?>" <?= $role_id === (int)$r['id'] ? 'selected' : '' ?>>
                            <?= eAdminPerm($r['nom_role']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit">Afficher le rôle</button>
        </form>
    </section>

    <?php if ($roleSelectionne): ?>
        <?php if ($isSelectedSuperAdmin): ?>
            <div class="permission-alert info">
                🛡️ <strong><?= eAdminPerm($roleSelectionne['nom_role']) ?></strong>
                possède automatiquement tous les accès. Il n'est pas nécessaire de lui attribuer des permissions une par une.
            </div>
        <?php else: ?>

            <section class="permission-panel">
                <h3>Ajouter ou mettre à jour une permission</h3>

                <form method="POST" class="permission-grid-form">
                    <input type="hidden" name="form_action" value="save_permission">
                    <input type="hidden" name="role_id" value="<?= (int)$role_id ?>">

                    <label>
                        Module
                        <input type="text" name="module" placeholder="Ex. parametrage" required>
                    </label>

                    <label>
                        Action
                        <input type="text" name="action_permission" placeholder="Ex. nomenclature" required>
                    </label>

                    <label>
                        Description
                        <input type="text" name="description" placeholder="Ex. Gérer la nomenclature fiscale">
                    </label>

                    <label>
                        Ordre
                        <input type="number" min="0" name="ordre" value="0">
                    </label>

                    <div>
                        <label class="permission-checkbox">
                            <input type="checkbox" name="autorise" value="1" checked>
                            Autorisé
                        </label>
                        <button class="permission-btn" type="submit">Enregistrer</button>
                    </div>
                </form>
            </section>

            <section class="permission-panel">
                <h3>Permissions attribuées</h3>

                <?php if (!$modules): ?>
                    <div class="permission-empty">
                        Aucune permission enregistrée pour ce rôle.
                    </div>
                <?php else: ?>
                    <?php foreach ($modules as $module => $items): ?>
                        <h4 class="permission-module-title"><?= eAdminPerm($module) ?></h4>
                        <div class="permission-table-wrap">
                            <table class="permission-table">
                                <thead>
                                    <tr>
                                        <th>Action</th>
                                        <th>Description</th>
                                        <th>État</th>
                                        <th>Ordre</th>
                                        <th>Mise à jour</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($items as $p): ?>
                                    <tr>
                                        <td><strong><?= eAdminPerm($p['action']) ?></strong></td>
                                        <td><?= eAdminPerm($p['description'] ?: '-') ?></td>
                                        <td>
                                            <span class="permission-badge <?= (int)$p['autorise'] === 1 ? 'on' : 'off' ?>">
                                                <?= (int)$p['autorise'] === 1 ? 'AUTORISÉ' : 'BLOQUÉ' ?>
                                            </span>
                                        </td>
                                        <td><?= (int)$p['ordre'] ?></td>
                                        <td><?= eAdminPerm($p['updated_at'] ?: $p['created_at']) ?></td>
                                        <td>
                                            <div class="permission-actions">
                                                <form method="POST">
                                                    <input type="hidden" name="form_action" value="toggle_permission">
                                                    <input type="hidden" name="role_id" value="<?= (int)$role_id ?>">
                                                    <input type="hidden" name="permission_id" value="<?= (int)$p['id'] ?>">
                                                    <button class="toggle-btn" type="submit">
                                                        <?= (int)$p['autorise'] === 1 ? 'Désactiver' : 'Activer' ?>
                                                    </button>
                                                </form>

                                                <form method="POST" onsubmit="return confirm('Supprimer cette permission ?');">
                                                    <input type="hidden" name="form_action" value="delete_permission">
                                                    <input type="hidden" name="role_id" value="<?= (int)$role_id ?>">
                                                    <input type="hidden" name="permission_id" value="<?= (int)$p['id'] ?>">
                                                    <button class="delete-btn" type="submit">Supprimer</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>

        <?php endif; ?>
    <?php endif; ?>
</div>

</main>
</div>
</body>
</html>
