<?php
/*
|--------------------------------------------------------------------------
| cOllect_Pay - Auth Check Centralisé
|--------------------------------------------------------------------------
| Objectif :
| Utilisateur -> Rôle -> Permissions -> Menus + pages autorisées
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/app.php";

if (!isset($pdo) || !($pdo instanceof PDO)) {
    die("Connexion PDO introuvable dans config/database.php.");
}

if (!function_exists('cpDb')) {
    function cpDb(): PDO
    {
        global $pdo;

        if ($pdo instanceof PDO) {
            return $pdo;
        }

        throw new Exception("Connexion PDO indisponible.");
    }
}

if (!function_exists('checkAuth')) {
    function checkAuth(): void
    {
        if (!empty($_SESSION['user_id'])) {
            return;
        }

        $loginUrl = '';

        if (defined('APP_URL') && trim((string) APP_URL) !== '') {
            $baseUrl = rtrim(trim((string) APP_URL), '/');
            $baseUrl = preg_replace('#:(8080|80|443)$#', '', $baseUrl);
            $loginUrl = $baseUrl . '/login.php';
        }

        if ($loginUrl === '') {
            $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');

            if (stripos($script, '/collect_paye/') === 0) {
                $loginUrl = '/collect_paye/login.php';
            } elseif (stripos($script, '/collect_pay/') === 0) {
                $loginUrl = '/collect_pay/login.php';
            } elseif (stripos($script, '/cOllect_pay/') === 0) {
                $loginUrl = '/cOllect_pay/login.php';
            } else {
                $loginUrl = '/login.php';
            }
        }

        header('Location: ' . $loginUrl, true, 302);
        exit;
    }
}

if (!function_exists('cpCurrentRole')) {
    function cpCurrentRole(): string
    {
        return strtoupper(trim((string)(
            $_SESSION['role']
            ?? $_SESSION['nom_role']
            ?? $_SESSION['role_code']
            ?? ''
        )));
    }
}

if (!function_exists('cpCurrentRoleId')) {
    function cpCurrentRoleId(): int
    {
        return (int)($_SESSION['role_id'] ?? $_SESSION['id_role'] ?? 0);
    }
}

/*
|--------------------------------------------------------------------------
| Synchronisation centrale des alias de rôle
|--------------------------------------------------------------------------
| Certains anciens modules lisent role, d'autres user_role, role_code,
| role_nom ou nom_role. On les harmonise ici à chaque requête authentifiée.
| Ainsi le login historique reste intact et tous les modules voient le même
| rôle sans dépendre d'une clé de session particulière.
|--------------------------------------------------------------------------
*/
if (!function_exists('cpSyncRoleSession')) {
    function cpSyncRoleSession(): void
    {
        if (empty($_SESSION['user_id'])) {
            return;
        }

        $role = trim((string)(
            $_SESSION['role']
            ?? $_SESSION['nom_role']
            ?? $_SESSION['user_role']
            ?? $_SESSION['role_code']
            ?? $_SESSION['role_nom']
            ?? $_SESSION['user_role_code']
            ?? $_SESSION['user_role_nom']
            ?? ''
        ));

        /*
         * Si aucun nom de rôle n'est dans la session mais qu'un role_id existe,
         * on relit la source de vérité dans la table roles.
         */
        if ($role === '' && cpCurrentRoleId() > 0) {
            try {
                $stmt = cpDb()->prepare("SELECT nom_role FROM roles WHERE id = ? LIMIT 1");
                $stmt->execute([cpCurrentRoleId()]);
                $role = trim((string)($stmt->fetchColumn() ?: ''));
            } catch (Throwable $e) {
                $role = '';
            }
        }

        if ($role === '') {
            return;
        }

        $_SESSION['role'] = $role;
        $_SESSION['nom_role'] = $role;
        $_SESSION['user_role'] = $role;
        $_SESSION['role_code'] = $role;
        $_SESSION['role_nom'] = $role;
        $_SESSION['user_role_code'] = $role;
        $_SESSION['user_role_nom'] = $role;
    }
}

if (!function_exists('cpIsSuperAdmin')) {
    function cpIsSuperAdmin(): bool
    {
        cpSyncRoleSession();

        $role = strtoupper(trim((string)(
            $_SESSION['role']
            ?? $_SESSION['nom_role']
            ?? $_SESSION['user_role']
            ?? ''
        )));

        $roleNormalise = str_replace([' ', '-'], '_', $role);
        $roleNormalise = preg_replace('/_+/', '_', $roleNormalise) ?? $roleNormalise;

        return in_array($roleNormalise, [
            'SUPER_ADMIN',
            'SUPER_ADMINISTRATEUR'
        ], true);
    }
}

/* Synchroniser immédiatement après chargement de l'authentification. */
cpSyncRoleSession();

if (!function_exists('logAction')) {
    function logAction(string $module, string $action, string $description = ''): void
    {
        try {
            $db = cpDb();
            $tables = array_map('current', $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_NUM));

            $table = in_array('logs', $tables, true)
                ? 'logs'
                : (in_array('journal_audit', $tables, true) ? 'journal_audit' : null);

            if (!$table) {
                return;
            }

            $cols = array_column(
                $db->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC),
                'Field'
            );

            $data = [];

            foreach ([
                'user_id' => $_SESSION['user_id'] ?? null,
                'module' => $module,
                'action' => $action,
                'description' => $description,
                'created_at' => date('Y-m-d H:i:s')
            ] as $k => $v) {
                if (in_array($k, $cols, true)) {
                    $data[$k] = $v;
                }
            }

            if (!$data) {
                return;
            }

            $sql = "INSERT INTO `$table` (`"
                . implode("`,`", array_keys($data))
                . "`) VALUES ("
                . implode(",", array_fill(0, count($data), "?"))
                . ")";

            $db->prepare($sql)->execute(array_values($data));

        } catch (Throwable $e) {
        }
    }
}

/*
|--------------------------------------------------------------------------
| Charger permissions dynamiques
|--------------------------------------------------------------------------
*/
require_once __DIR__ . "/permissions.php";

/*
|--------------------------------------------------------------------------
| Compatibilité anciens fichiers avec requireRole()
|--------------------------------------------------------------------------
| On ne bloque plus uniquement par rôle fixe.
| SUPER_ADMIN passe tout.
| Sinon on laisse requirePermission() gérer les pages modernes.
| Pour les anciens fichiers, on autorise si le rôle courant est dans la liste.
|--------------------------------------------------------------------------
*/
if (!function_exists('requireRole')) {
    function requireRole(array $roles): void
    {
        $role = cpCurrentRole();

        if (cpIsSuperAdmin()) {
            return;
        }

        $roles = array_map(fn($r) => strtoupper(trim((string)$r)), $roles);

        if (in_array($role, $roles, true)) {
            return;
        }

        if (
            in_array('RECOUVREMENT', $roles, true)
            || in_array('CHEF_RECOUVREMENT', $roles, true)
            || in_array('CAISSIER', $roles, true)
            || in_array('APUREUR', $roles, true)
        ) {
            if (
                hasPermission('apurement', 'view')
                || hasPermission('apurement', 'create')
                || hasPermission('recouvrement', 'view')
                || hasPermission('recouvrement', 'apurement')
                || hasPermission('quittances', 'view')
                || hasPermission('paiements', 'view')
            ) {
                return;
            }
        }

        http_response_code(403);
        die("
            <div style='font-family:Arial;background:#f8fafc;min-height:100vh;display:flex;align-items:center;justify-content:center;'>
                <div style='background:white;border-radius:18px;padding:28px;box-shadow:0 10px 30px rgba(0,0,0,.10);max-width:520px;text-align:center;'>
                    <h2 style='color:#991b1b;margin-top:0;'>⛔ Accès refusé</h2>
                    <p>Votre rôle ne vous permet pas d'accéder à cette page.</p>
                    <p style='color:#64748b;font-size:13px;'>Votre rôle actuel : <strong>"
                    . htmlspecialchars($role, ENT_QUOTES, 'UTF-8')
                    . "</strong></p>
                    <a href='javascript:history.back()' style='display:inline-block;margin-top:12px;background:#0f3460;color:white;padding:11px 16px;border-radius:10px;text-decoration:none;font-weight:bold;'>Retour</a>
                </div>
            </div>
        ");
    }
}

checkAuth();
