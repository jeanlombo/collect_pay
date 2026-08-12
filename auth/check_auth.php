<?php
/*
|--------------------------------------------------------------------------
| cOllect_Pay - Auth Check Centralisé V2
|--------------------------------------------------------------------------
| Correctif :
| - SUPER_ADMIN et "Super Administrateur" = accès total
| - normalisation espaces / tirets / underscores
| - contrôle possible via le vrai rôle enregistré en base
| - compatibilité avec les anciens requireRole()
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

        throw new RuntimeException("Connexion PDO indisponible.");
    }
}

if (!function_exists('cpNormalizeRole')) {
    function cpNormalizeRole(?string $role): string
    {
        $role = trim((string)$role);

        if ($role === '') {
            return '';
        }

        $role = mb_strtoupper($role, 'UTF-8');

        // Supprimer les accents pour fiabiliser les comparaisons.
        $from = ['À','Â','Ä','Á','Ã','Å','Ç','È','É','Ê','Ë','Ì','Í','Î','Ï','Ò','Ó','Ô','Ö','Õ','Ù','Ú','Û','Ü','Ý'];
        $to   = ['A','A','A','A','A','A','C','E','E','E','E','I','I','I','I','O','O','O','O','O','U','U','U','U','Y'];
        $role = str_replace($from, $to, $role);

        // Même représentation pour "SUPER ADMIN", "SUPER-ADMIN", "SUPER_ADMIN".
        $role = preg_replace('/[^A-Z0-9]+/u', '_', $role) ?? $role;
        $role = preg_replace('/_+/', '_', $role) ?? $role;

        return trim($role, '_');
    }
}

if (!function_exists('cpCurrentRoleId')) {
    function cpCurrentRoleId(): int
    {
        return (int)(
            $_SESSION['role_id']
            ?? $_SESSION['id_role']
            ?? 0
        );
    }
}

if (!function_exists('cpCurrentRole')) {
    function cpCurrentRole(): string
    {
        $roleSession = (string)(
            $_SESSION['role']
            ?? $_SESSION['nom_role']
            ?? $_SESSION['role_code']
            ?? $_SESSION['role_nom']
            ?? $_SESSION['user_role']
            ?? ''
        );

        return cpNormalizeRole($roleSession);
    }
}

if (!function_exists('cpRoleNameFromDb')) {
    function cpRoleNameFromDb(?int $roleId = null): string
    {
        $roleId = $roleId ?? cpCurrentRoleId();

        if ($roleId <= 0) {
            return '';
        }

        try {
            $stmt = cpDb()->prepare("
                SELECT nom_role
                FROM roles
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$roleId]);

            return cpNormalizeRole((string)($stmt->fetchColumn() ?: ''));
        } catch (Throwable $e) {
            return '';
        }
    }
}

if (!function_exists('cpIsSuperAdmin')) {
    function cpIsSuperAdmin(): bool
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $superRoles = [
            'SUPER_ADMIN',
            'SUPER_ADMINISTRATEUR',
            'SUPERADMIN',
            'SUPERADMINISTRATEUR'
        ];

        // 1. Valeurs disponibles dans la session.
        $sessionCandidates = [
            $_SESSION['role'] ?? '',
            $_SESSION['nom_role'] ?? '',
            $_SESSION['role_code'] ?? '',
            $_SESSION['role_nom'] ?? '',
            $_SESSION['user_role'] ?? ''
        ];

        foreach ($sessionCandidates as $candidate) {
            if (in_array(cpNormalizeRole((string)$candidate), $superRoles, true)) {
                return $cache = true;
            }
        }

        // 2. Source de vérité : nom du rôle enregistré dans la base.
        $roleDb = cpRoleNameFromDb();

        if ($roleDb !== '' && in_array($roleDb, $superRoles, true)) {
            // Harmoniser aussi la session pour les anciennes pages.
            $_SESSION['role'] = $roleDb;
            $_SESSION['nom_role'] = $roleDb;
            $_SESSION['role_code'] = $roleDb;

            return $cache = true;
        }

        return $cache = false;
    }
}

if (!function_exists('checkAuth')) {
    function checkAuth(): void
    {
        if (!empty($_SESSION['user_id'])) {
            return;
        }

        $loginUrl = '';

        if (defined('APP_URL') && trim((string)APP_URL) !== '') {
            $baseUrl = rtrim(trim((string)APP_URL), '/');
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

if (!function_exists('cpAccessDenied')) {
    function cpAccessDenied(?string $detail = null): never
    {
        http_response_code(403);

        $role = cpRoleNameFromDb();
        if ($role === '') {
            $role = cpCurrentRole();
        }

        $roleAffiche = str_replace('_', ' ', $role ?: 'NON DÉFINI');

        die("
            <div style='font-family:Arial,sans-serif;background:#f8fafc;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;box-sizing:border-box;'>
                <div style='background:white;border-radius:18px;padding:28px;box-shadow:0 10px 30px rgba(0,0,0,.10);max-width:540px;text-align:center;'>
                    <h2 style='color:#991b1b;margin-top:0;'>⛔ Accès refusé</h2>
                    <p>Votre rôle ne vous permet pas d'accéder à cette page.</p>
                    <p style='color:#64748b;font-size:13px;'>Votre rôle actuel : <strong>"
                    . htmlspecialchars($roleAffiche, ENT_QUOTES, 'UTF-8')
                    . "</strong></p>"
                    . ($detail ? "<p style='color:#94a3b8;font-size:11px;'>".htmlspecialchars($detail, ENT_QUOTES, 'UTF-8')."</p>" : "")
                    . "<a href='javascript:history.back()' style='display:inline-block;margin-top:12px;background:#0f3460;color:white;padding:11px 16px;border-radius:10px;text-decoration:none;font-weight:bold;'>Retour</a>
                </div>
            </div>
        ");
    }
}

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
            // Un problème de journalisation ne doit jamais bloquer l'utilisateur.
        }
    }
}

/*
|--------------------------------------------------------------------------
| Charger le moteur de permissions
|--------------------------------------------------------------------------
*/
require_once __DIR__ . "/permissions.php";

/*
|--------------------------------------------------------------------------
| Compatibilité anciens fichiers avec requireRole()
|--------------------------------------------------------------------------
*/
if (!function_exists('requireRole')) {
    function requireRole(array $roles): void
    {
        checkAuth();

        // Le Super Administrateur passe TOUJOURS.
        if (cpIsSuperAdmin()) {
            return;
        }

        $role = cpCurrentRole();

        $rolesNormalises = array_map(
            static fn($r) => cpNormalizeRole((string)$r),
            $roles
        );

        if (in_array($role, $rolesNormalises, true)) {
            return;
        }

        /*
         * Compatibilité historique du recouvrement.
         * Les permissions granulaires peuvent donner l'accès à une ancienne
         * page qui utilisait seulement requireRole().
         */
        if (
            in_array('RECOUVREMENT', $rolesNormalises, true)
            || in_array('CHEF_RECOUVREMENT', $rolesNormalises, true)
            || in_array('DIRECTEUR_RECOUVREMENT', $rolesNormalises, true)
            || in_array('CAISSIER', $rolesNormalises, true)
            || in_array('APUREUR', $rolesNormalises, true)
        ) {
            if (
                hasPermission('apurement', 'view')
                || hasPermission('apurement', 'create')
                || hasPermission('apurement', 'voir')
                || hasPermission('apurement', 'creer')
                || hasPermission('recouvrement', 'view')
                || hasPermission('recouvrement', 'voir')
                || hasPermission('recouvrement', 'apurement')
                || hasPermission('quittances', 'view')
                || hasPermission('quittances', 'voir')
                || hasPermission('paiements', 'view')
                || hasPermission('paiements', 'voir')
            ) {
                return;
            }
        }

        cpAccessDenied('Contrôle requireRole');
    }
}

checkAuth();
