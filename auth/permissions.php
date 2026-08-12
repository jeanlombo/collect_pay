<?php
/*
|--------------------------------------------------------------------------
| cOllect_Pay - Permissions Dynamiques Safe Columns
|--------------------------------------------------------------------------
| Corrige :
| - Unknown column 'label'
| - Permissions cochées mais non actives
| - Gestion dynamique selon la structure réelle de la table permissions
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('cpDb')) {
    function cpDb(): PDO
    {
        global $pdo;

        if ($pdo instanceof PDO) {
            return $pdo;
        }

        $dbFile = __DIR__ . "/../config/database.php";
        if (file_exists($dbFile)) {
            require_once $dbFile;
        }

        if (isset($pdo) && $pdo instanceof PDO) {
            return $pdo;
        }

        throw new Exception("Connexion PDO indisponible.");
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
        return (int)(
            $_SESSION['role_id']
            ?? $_SESSION['id_role']
            ?? 0
        );
    }
}

if (!function_exists('cpPermTableExists')) {
    function cpPermTableExists(PDO $db, string $table): bool
    {
        try {
            $stmt = $db->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            return (bool)$stmt->fetch(PDO::FETCH_NUM);
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('cpPermColumns')) {
    function cpPermColumns(PDO $db, string $table): array
    {
        try {
            $stmt = $db->query("SHOW COLUMNS FROM `$table`");
            return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('cpEnsurePermissionsTable')) {
    function cpEnsurePermissionsTable(PDO $db): void
    {
        if (!cpPermTableExists($db, 'permissions')) {
            $db->exec("
                CREATE TABLE IF NOT EXISTS permissions (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    role_id INT NOT NULL,
                    module VARCHAR(100) NOT NULL,
                    action VARCHAR(100) NOT NULL,
                    autorise TINYINT(1) NOT NULL DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uniq_role_module_action (role_id, module, action)
                )
            ");
        }
    }
}

/*
|--------------------------------------------------------------------------
| Matrice officielle
|--------------------------------------------------------------------------
*/
if (!function_exists('collectPayPermissionMatrix')) {
    function collectPayPermissionMatrix(): array
    {
        return [
            'dashboard' => [
                'label' => 'Tableau de bord',
                'actions' => [
                    'view' => 'Voir le tableau de bord',
                ]
            ],

            'contribuables' => [
                'label' => 'Contribuables',
                'actions' => [
                    'view' => 'Voir contribuables',
                    'add' => 'Ajouter contribuable',
                    'edit' => 'Modifier contribuable',
                    'delete' => 'Supprimer contribuable',
                    'import' => 'Importer contribuables',
                ]
            ],

            'constatation' => [
                'label' => 'Constatation',
                'actions' => [
                    'view' => 'Voir NT',
                    'add' => 'Créer NT',
                    'edit' => 'Modifier NT',
                    'delete' => 'Supprimer NT',
                    'submit' => 'Soumettre NT',
                    'print' => 'Imprimer NT',
                ]
            ],

            'liquidation' => [
                'label' => 'Liquidation',
                'actions' => [
                    'view' => 'Voir liquidation',
                    'create_nd' => 'Créer ND',
                    'validate_nd' => 'Valider ND',
                    'reject_nd' => 'Rejeter ND',
                    'print_nd' => 'Imprimer ND',
                ]
            ],

            'controle' => [
                'label' => 'Contrôle',
                'actions' => [
                    'view' => 'Voir contrôle',
                    'validate' => 'Valider contrôle',
                    'reject' => 'Rejeter contrôle',
                    'observe' => 'Ajouter observation',
                ]
            ],

            'ordonnancement' => [
                'label' => 'Ordonnancement',
                'actions' => [
                    'view' => 'Voir ordonnancement',
                    'create_np' => 'Créer NP',
                    'fractionner_np' => 'Fractionner NP',
                    'avis_fractionnement' => 'Créer avis de fractionnement',
                    'print_np' => 'Imprimer NP',
                ]
            ],

            'paiements' => [
                'label' => 'Paiements',
                'actions' => [
                    'view' => 'Voir paiements',
                    'add_np' => 'Payer NP',
                    'add_npf' => 'Payer NPF',
                    'edit' => 'Modifier paiement',
                    'print' => 'Imprimer reçu',
                ]
            ],

            'recouvrement' => [
                'label' => 'Recouvrement',
                'actions' => [
                    'view' => 'Voir recouvrement',
                    'apurement' => 'Apurer NP / NPF',
                    'quittance' => 'Générer quittance',
                    'amr' => 'Générer AMR',
                    'print' => 'Imprimer documents',
                ]
            ],

            'apurement' => [
                'label' => 'Apurement',
                'actions' => [
                    'view' => 'Voir apurements',
                    'create' => 'Créer apurement',
                    'validate' => 'Valider apurement',
                    'print' => 'Imprimer apurement',
                ]
            ],

            'amr' => [
                'label' => 'AMR',
                'actions' => [
                    'view' => 'Voir AMR',
                    'create' => 'Créer AMR',
                    'print' => 'Imprimer AMR',
                ]
            ],

            'quittances' => [
                'label' => 'Quittances',
                'actions' => [
                    'view' => 'Voir quittances',
                    'create' => 'Créer quittance',
                    'print' => 'Imprimer quittance',
                ]
            ],

            'penalites' => [
                'label' => 'Pénalités',
                'actions' => [
                    'view' => 'Voir pénalités',
                    'manage' => 'Gérer barème pénalités',
                    'history' => 'Voir historique pénalités',
                ]
            ],

            'inspection' => [
                'label' => 'Inspection / QR',
                'actions' => [
                    'view' => 'Voir inspection',
                    'scan' => 'Scanner QR',
                    'verify' => 'Vérifier document',
                    'revoke' => 'Révoquer document',
                    'fraud' => 'Voir fraudes suspectes',
                    'alerts' => 'Voir alertes',
                ]
            ],

            'corrections' => [
                'label' => 'Corrections Documents',
                'actions' => [
                    'view' => 'Voir corrections',
                    'create' => 'Créer correction',
                    'validate' => 'Valider correction',
                    'history' => 'Historique corrections',
                ]
            ],

            'parametrage' => [
                'label' => 'Paramétrage',
                'actions' => [
                    'view' => 'Voir paramètres',
                    'manage' => 'Gérer paramètres',
                    'nomenclature' => 'Gérer nomenclature',
                    'directions' => 'Gérer directions',
                    'services' => 'Gérer services',
                    'periodes' => 'Gérer périodes',
                    'taux_change' => 'Gérer taux change',
                ]
            ],

            'users' => [
                'label' => 'Utilisateurs',
                'actions' => [
                    'view' => 'Voir utilisateurs',
                    'add' => 'Ajouter utilisateur',
                    'edit' => 'Modifier utilisateur',
                    'delete' => 'Supprimer utilisateur',
                    'status' => 'Activer / désactiver utilisateur',
                    'password' => 'Changer mot de passe',
                ]
            ],

            'roles' => [
                'label' => 'Rôles',
                'actions' => [
                    'view' => 'Voir rôles',
                    'add' => 'Ajouter rôle',
                    'edit' => 'Modifier rôle',
                    'delete' => 'Supprimer rôle',
                    'permissions' => 'Gérer permissions',
                ]
            ],

            'administration' => [
                'label' => 'Administration',
                'actions' => [
                    'view' => 'Voir administration',
                    'logs' => 'Voir journaux',
                    'backup' => 'Sauvegardes',
                    'settings' => 'Paramètres système',
                ]
            ],

            'pwa' => [
                'label' => 'PWA Mobile',
                'actions' => [
                    'view' => 'Voir PWA',
                    'sync' => 'Synchronisation PWA',
                    'backup' => 'Sauvegarde PWA',
                    'agents' => 'Agents terrain',
                    'reports' => 'Rapports PWA',
                ]
            ],
        ];
    }
}

/*
|--------------------------------------------------------------------------
| Lecture permissions
|--------------------------------------------------------------------------
*/
if (!function_exists('getRolePermissions')) {
    function getRolePermissions(int $roleId): array
    {
        if ($roleId <= 0) {
            return [];
        }

        try {
            $db = cpDb();
            cpEnsurePermissionsTable($db);

            $cols = cpPermColumns($db, 'permissions');

            $roleCol = in_array('role_id', $cols, true) ? 'role_id' : null;
            $moduleCol = in_array('module', $cols, true) ? 'module' : null;
            $actionCol = in_array('action', $cols, true) ? 'action' : null;

            $allowedCol = null;
            foreach (['autorise', 'allowed', 'value', 'statut'] as $c) {
                if (in_array($c, $cols, true)) {
                    $allowedCol = $c;
                    break;
                }
            }

            if (!$roleCol || !$moduleCol || !$actionCol || !$allowedCol) {
                return [];
            }

            $stmt = $db->prepare("
                SELECT `$moduleCol` AS module, `$actionCol` AS action, `$allowedCol` AS autorise
                FROM permissions
                WHERE `$roleCol` = ?
            ");
            $stmt->execute([$roleId]);

            $out = [];

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $module = (string)($row['module'] ?? '');
                $action = (string)($row['action'] ?? '');

                if ($module === '' || $action === '') {
                    continue;
                }

                $value = $row['autorise'] ?? 0;

                if (is_string($value) && in_array(strtolower($value), ['actif', 'active', 'yes', 'oui', 'true'], true)) {
                    $value = 1;
                }

                $out[$module][$action] = (int)$value;
            }

            return $out;

        } catch (Throwable $e) {
            return [];
        }
    }
}

/*
|--------------------------------------------------------------------------
| Enregistrement permissions - compatible avec colonnes existantes
|--------------------------------------------------------------------------
*/
if (!function_exists('setPermission')) {
    function setPermission(
        int $roleId,
        string $module,
        string $action,
        int $autorise,
        string $label = '',
        int $ordre = 0
    ): void {
        $db = cpDb();
        cpEnsurePermissionsTable($db);

        $cols = cpPermColumns($db, 'permissions');

        /*
        |--------------------------------------------------------------------------
        | Recherche si permission existe déjà
        |--------------------------------------------------------------------------
        */
        $stmt = $db->prepare("
            SELECT id
            FROM permissions
            WHERE role_id = ?
              AND module = ?
              AND action = ?
            LIMIT 1
        ");
        $stmt->execute([$roleId, $module, $action]);
        $existingId = (int)($stmt->fetchColumn() ?: 0);

        $data = [
            'role_id' => $roleId,
            'module' => $module,
            'action' => $action,
            'autorise' => $autorise ? 1 : 0,
            'allowed' => $autorise ? 1 : 0,
            'value' => $autorise ? 1 : 0,
            'label' => $label,
            'libelle' => $label,
            'description' => $label,
            'ordre' => $ordre,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $valid = [];

        foreach ($data as $col => $val) {
            if (in_array($col, $cols, true)) {
                $valid[$col] = $val;
            }
        }

        if ($existingId > 0) {
            unset($valid['id']);
            unset($valid['role_id']);
            unset($valid['module']);
            unset($valid['action']);
            unset($valid['created_at']);

            $sets = [];
            $values = [];

            foreach ($valid as $col => $val) {
                $sets[] = "`$col` = ?";
                $values[] = $val;
            }

            if (!$sets) {
                return;
            }

            $values[] = $existingId;

            $sql = "UPDATE permissions SET " . implode(", ", $sets) . " WHERE id = ?";
            $db->prepare($sql)->execute($values);
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Insertion nouvelle ligne
        |--------------------------------------------------------------------------
        */
        $insert = [];

        foreach ($valid as $col => $val) {
            if ($col === 'updated_at') {
                continue;
            }

            $insert[$col] = $val;
        }

        if (!isset($insert['role_id']) || !isset($insert['module']) || !isset($insert['action'])) {
            throw new Exception("La table permissions doit contenir role_id, module et action.");
        }

        $sql = "INSERT INTO permissions (`" . implode("`,`", array_keys($insert)) . "`)
                VALUES (" . implode(",", array_fill(0, count($insert), "?")) . ")";
        $db->prepare($sql)->execute(array_values($insert));
    }
}

/*
|--------------------------------------------------------------------------
| Vérification permission
|--------------------------------------------------------------------------
*/
if (!function_exists('hasPermission')) {
    function hasPermission(string $module, string $action = 'view'): bool
    {
        $role = cpCurrentRole();

        if ($role === 'SUPER_ADMIN') {
            return true;
        }

        $roleId = cpCurrentRoleId();

        if ($roleId <= 0) {
            return false;
        }

        $permissions = getRolePermissions($roleId);

        return isset($permissions[$module][$action])
            && (int)$permissions[$module][$action] === 1;
    }
}

if (!function_exists('canDo')) {
    function canDo(string $module, string $action = 'view'): bool
    {
        return hasPermission($module, $action);
    }
}

if (!function_exists('requirePermission')) {
    function requirePermission(string $module, string $action = 'view'): void
    {
        if (hasPermission($module, $action)) {
            return;
        }

        http_response_code(403);

        die("
            <div style='font-family:Arial;background:#f8fafc;min-height:100vh;display:flex;align-items:center;justify-content:center;'>
                <div style='background:white;border-radius:18px;padding:28px;box-shadow:0 10px 30px rgba(0,0,0,.10);max-width:520px;text-align:center;'>
                    <h2 style='color:#991b1b;margin-top:0;'>⛔ Accès interdit</h2>
                    <p>Vous n'avez pas la permission nécessaire pour accéder à cette page.</p>
                    <p style='color:#64748b;font-size:13px;'>Permission requise : <strong>" . htmlspecialchars($module) . " / " . htmlspecialchars($action) . "</strong></p>
                    <a href='javascript:history.back()' style='display:inline-block;margin-top:12px;background:#0f3460;color:white;padding:11px 16px;border-radius:10px;text-decoration:none;font-weight:bold;'>Retour</a>
                </div>
            </div>
        ");
    }
}

/*
|--------------------------------------------------------------------------
| Menu dynamique
|--------------------------------------------------------------------------
*/
if (!function_exists('canAccessMenu')) {
    function canAccessMenu($menu): bool
    {
        if (cpCurrentRole() === 'SUPER_ADMIN') {
            return true;
        }

        $menu = strtoupper(trim((string)$menu));

        $map = [
            'ACCUEIL' => ['dashboard', 'view'],
            'DASHBOARD' => ['dashboard', 'view'],

            'CONTRIBUABLES' => ['contribuables', 'view'],
            'CONSTATATION' => ['constatation', 'view'],
            'LIQUIDATION' => ['liquidation', 'view'],
            'CONTROLE' => ['controle', 'view'],
            'ORDONNANCEMENT' => ['ordonnancement', 'view'],

            'PAIEMENT' => ['paiements', 'view'],
            'PAIEMENTS' => ['paiements', 'view'],
            'RECOUVREMENT' => ['recouvrement', 'view'],
            'APUREMENT' => ['apurement', 'view'],
            'AMR' => ['amr', 'view'],
            'QUITTANCES' => ['quittances', 'view'],

            'PENALITES' => ['penalites', 'view'],
            'INSPECTION' => ['inspection', 'view'],
            'CORRECTIONS' => ['corrections', 'view'],
            'PARAMETRAGE' => ['parametrage', 'view'],
            'ADMINISTRATION' => ['administration', 'view'],
            'USERS' => ['users', 'view'],
            'ROLES' => ['roles', 'view'],
            'PWA' => ['pwa', 'view'],
        ];

        if (!isset($map[$menu])) {
            return false;
        }

        return hasPermission($map[$menu][0], $map[$menu][1]);
    }
}
