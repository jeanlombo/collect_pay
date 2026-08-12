<?php
/*
|--------------------------------------------------------------------------
| cOllect_Pay - Moteur central des permissions V2
|--------------------------------------------------------------------------
| Principe :
| 1. Super Administrateur = TRUE systématiquement.
| 2. Autres rôles = lecture de permissions(role_id,module,action,autorise).
| 3. Compatible avec :
|    hasPermission('module','action')
|    hasPermission('DASHBOARD_VIEW')
|    requirePermission('module','action')
|    requirePermission('DASHBOARD_VIEW')
|    canDo('module','action')
|--------------------------------------------------------------------------
*/

if (!function_exists('cpPermissionNormalize')) {
    function cpPermissionNormalize(?string $value): string
    {
        $value = mb_strtolower(trim((string)$value), 'UTF-8');
        $value = strtr($value, [
            'à'=>'a','â'=>'a','ä'=>'a','á'=>'a',
            'ç'=>'c',
            'è'=>'e','é'=>'e','ê'=>'e','ë'=>'e',
            'ì'=>'i','í'=>'i','î'=>'i','ï'=>'i',
            'ò'=>'o','ó'=>'o','ô'=>'o','ö'=>'o',
            'ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u',
            'ý'=>'y'
        ]);

        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? $value;
        $value = preg_replace('/_+/', '_', $value) ?? $value;

        return trim($value, '_');
    }
}

if (!function_exists('cpPermissionActionVariants')) {
    function cpPermissionActionVariants(string $action): array
    {
        $a = cpPermissionNormalize($action);

        $groups = [
            ['view','voir','liste','list','consult','consulter','access'],
            ['create','creer','add','ajouter','new'],
            ['edit','modifier','update','mettre_a_jour'],
            ['delete','supprimer','remove'],
            ['validate','valider','validation'],
            ['print','imprimer','pdf'],
            ['manage','gerer','gestion'],
            ['history','historique'],
            ['export','exporter'],
            ['scan','scanner'],
        ];

        $variants = [$a];

        foreach ($groups as $group) {
            if (in_array($a, $group, true)) {
                $variants = array_merge($variants, $group);
            }
        }

        return array_values(array_unique(array_filter($variants)));
    }
}

if (!function_exists('cpPermissionFromCode')) {
    function cpPermissionFromCode(string $code): array
    {
        $code = cpPermissionNormalize($code);

        if ($code === '') {
            return ['', ''];
        }

        // Ex. DASHBOARD_VIEW -> dashboard / view
        //     UTILISATEURS_CREATE -> utilisateurs / create
        $actionsConnues = [
            'view','voir','create','creer','add','ajouter','edit','modifier',
            'delete','supprimer','validate','valider','print','imprimer',
            'manage','gerer','history','historique','export','scan','access'
        ];

        foreach ($actionsConnues as $action) {
            $suffix = '_' . $action;

            if (str_ends_with($code, $suffix)) {
                return [
                    substr($code, 0, -strlen($suffix)),
                    $action
                ];
            }
        }

        /*
         * Si le code n'a pas de suffixe d'action, on l'utilise comme module
         * et on essaiera aussi l'action access/view.
         */
        return [$code, 'access'];
    }
}

if (!function_exists('cpPermissionExists')) {
    function cpPermissionExists(int $roleId, string $module, string $action): bool
    {
        if ($roleId <= 0) {
            return false;
        }

        $module = cpPermissionNormalize($module);
        $actions = cpPermissionActionVariants($action);

        if ($module === '' || !$actions) {
            return false;
        }

        try {
            $placeholders = implode(',', array_fill(0, count($actions), '?'));

            $sql = "
                SELECT 1
                FROM permissions
                WHERE role_id = ?
                  AND LOWER(REPLACE(REPLACE(module,'-','_'),' ','_')) = ?
                  AND LOWER(REPLACE(REPLACE(action,'-','_'),' ','_')) IN ($placeholders)
                  AND COALESCE(autorise,1) = 1
                LIMIT 1
            ";

            $params = array_merge([$roleId, $module], $actions);
            $stmt = cpDb()->prepare($sql);
            $stmt->execute($params);

            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            // Fail closed pour les rôles ordinaires.
            return false;
        }
    }
}

if (!function_exists('hasPermission')) {
    function hasPermission(string $moduleOrCode, ?string $action = null): bool
    {
        checkAuth();

        // RÈGLE ABSOLUE : le Super Administrateur ne dépend pas des lignes permissions.
        if (function_exists('cpIsSuperAdmin') && cpIsSuperAdmin()) {
            return true;
        }

        $roleId = function_exists('cpCurrentRoleId')
            ? cpCurrentRoleId()
            : (int)($_SESSION['role_id'] ?? 0);

        if ($roleId <= 0) {
            return false;
        }

        if ($action === null || trim($action) === '') {
            [$module, $actionResolved] = cpPermissionFromCode($moduleOrCode);

            // Première forme dérivée depuis CODE_ACTION.
            if (cpPermissionExists($roleId, $module, $actionResolved)) {
                return true;
            }

            /*
             * Compatibilité avec des permissions enregistrées en action
             * complète ou module complet.
             */
            $code = cpPermissionNormalize($moduleOrCode);

            if (cpPermissionExists($roleId, $code, 'access')) {
                return true;
            }

            if (cpPermissionExists($roleId, $code, 'view')) {
                return true;
            }

            if (cpPermissionExists($roleId, $code, 'voir')) {
                return true;
            }

            return false;
        }

        return cpPermissionExists(
            $roleId,
            $moduleOrCode,
            $action
        );
    }
}

if (!function_exists('canDo')) {
    function canDo(string $module, string $action): bool
    {
        return hasPermission($module, $action);
    }
}

if (!function_exists('canAccess')) {
    function canAccess(string $module, string $action = 'view'): bool
    {
        return hasPermission($module, $action);
    }
}

if (!function_exists('hasAnyPermission')) {
    function hasAnyPermission(array $permissions): bool
    {
        if (function_exists('cpIsSuperAdmin') && cpIsSuperAdmin()) {
            return true;
        }

        foreach ($permissions as $permission) {
            if (is_array($permission)) {
                $module = (string)($permission[0] ?? '');
                $action = (string)($permission[1] ?? 'view');

                if ($module !== '' && hasPermission($module, $action)) {
                    return true;
                }
            } else {
                if (hasPermission((string)$permission)) {
                    return true;
                }
            }
        }

        return false;
    }
}

if (!function_exists('requirePermission')) {
    function requirePermission(string $moduleOrCode, ?string $action = null): void
    {
        checkAuth();

        if (function_exists('cpIsSuperAdmin') && cpIsSuperAdmin()) {
            return;
        }

        if (hasPermission($moduleOrCode, $action)) {
            return;
        }

        $detail = $action !== null
            ? $moduleOrCode . ' / ' . $action
            : $moduleOrCode;

        if (function_exists('cpAccessDenied')) {
            cpAccessDenied('Permission requise : ' . $detail);
        }

        http_response_code(403);
        exit('Accès refusé.');
    }
}
