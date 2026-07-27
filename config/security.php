<?php
/*
|--------------------------------------------------------------------------
| cOllect_Pay - Sécurité centrale
|--------------------------------------------------------------------------
| Fichier : config/security.php
| Contient uniquement :
| - session
| - checkAuth
| - requireRole
| - requireNiveau
| - fonctions de rôle
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('checkAuth')) {
    function checkAuth()
    {
        if (empty($_SESSION['user_id'])) {
            header("Location: /collect_pay/login.php");
            exit;
        }

        return true;
    }
}

if (!function_exists('isLoggedIn')) {
    function isLoggedIn()
    {
        return !empty($_SESSION['user_id']);
    }
}

if (!function_exists('currentRoleName')) {
    function currentRoleName()
    {
        if (!empty($_SESSION['nom_role'])) {
            return $_SESSION['nom_role'];
        }

        if (!empty($_SESSION['role'])) {
            return $_SESSION['role'];
        }

        return '';
    }
}

if (!function_exists('currentRoleId')) {
    function currentRoleId()
    {
        return isset($_SESSION['role_id']) ? (int) $_SESSION['role_id'] : 0;
    }
}

if (!function_exists('renderAccessDenied')) {
    function renderAccessDenied($message = "Vous ne disposez pas des droits nécessaires pour accéder à cette page.")
    {
        $role = function_exists('currentRoleName') ? currentRoleName() : ($_SESSION['role'] ?? '');

        http_response_code(403);

        echo '
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <title>Accès refusé</title>
            <style>
                body{
                    font-family:Arial, sans-serif;
                    background:#f8fafc;
                    display:flex;
                    justify-content:center;
                    align-items:center;
                    min-height:100vh;
                    margin:0;
                    padding:20px;
                }
                .box{
                    background:white;
                    padding:40px;
                    border-radius:20px;
                    box-shadow:0 10px 30px rgba(0,0,0,.10);
                    text-align:center;
                    max-width:650px;
                    border:1px solid #fee2e2;
                }
                h1{color:#dc2626;margin-bottom:10px}
                p{color:#475569;line-height:1.6}
                .role{
                    background:#f8fafc;
                    border:1px solid #e5e7eb;
                    padding:10px;
                    border-radius:12px;
                    margin-top:15px;
                }
                a{
                    display:inline-block;
                    margin-top:20px;
                    background:#0f3460;
                    color:white;
                    padding:12px 20px;
                    border-radius:10px;
                    text-decoration:none;
                    font-weight:bold;
                }
            </style>
        </head>
        <body>
            <div class="box">
                <h1>⛔ Accès refusé</h1>
                <p>' . htmlspecialchars($message) . '</p>
                <div class="role">
                    Votre rôle actuel :
                    <strong>' . htmlspecialchars($role ?: '-') . '</strong>
                </div>
                <a href="/collect_pay/dashboard/index.php">Retour au tableau de bord</a>
            </div>
        </body>
        </html>';

        exit;
    }
}

if (!function_exists('requireRole')) {
    function requireRole($roles)
    {
        checkAuth();

        $role = currentRoleName();

        if (!in_array($role, (array) $roles, true)) {
            renderAccessDenied("Votre rôle ne vous permet pas d'accéder à cette page.");
        }

        return true;
    }
}

if (!function_exists('requireNiveau')) {
    function requireNiveau($niveaux)
    {
        checkAuth();

        $niveau = $_SESSION['niveau'] ?? '';

        if (!in_array($niveau, (array) $niveaux, true)) {
            renderAccessDenied("Accès interdit pour votre niveau d'accès.");
        }

        return true;
    }
}

if (!function_exists('isSuperAdmin')) {
    function isSuperAdmin()
    {
        $role = strtolower(trim(currentRoleName()));

        return in_array($role, [
            'super administrateur',
            'super_admin',
            'super admin',
            'administrateur principal'
        ], true);
    }
}

if (!function_exists('isAuditeur')) {
    function isAuditeur()
    {
        $role = strtolower(trim(currentRoleName()));

        return in_array($role, [
            'auditeur',
            'audit',
            'auditeur provincial',
            'auditeur national',
            'auditeur système'
        ], true);
    }
}

if (!function_exists('isInspecteur')) {
    function isInspecteur()
    {
        $role = strtolower(trim(currentRoleName()));

        return in_array($role, [
            'inspecteur',
            'inspection',
            'controleur',
            'contrôleur'
        ], true);
    }
}

if (!function_exists('isOrdonnateur')) {
    function isOrdonnateur()
    {
        $role = strtolower(trim(currentRoleName()));

        return in_array($role, [
            'ordonnateur',
            'ordonnancement'
        ], true);
    }
}

if (!function_exists('isControleur')) {
    function isControleur()
    {
        $role = strtolower(trim(currentRoleName()));

        return in_array($role, [
            'controleur',
            'contrôleur',
            'controle',
            'contrôle'
        ], true);
    }
}

if (!function_exists('isLiquidateur')) {
    function isLiquidateur()
    {
        $role = strtolower(trim(currentRoleName()));

        return in_array($role, [
            'liquidateur',
            'liquidation'
        ], true);
    }
}

if (!function_exists('isRecouvrement')) {
    function isRecouvrement()
    {
        $role = strtolower(trim(currentRoleName()));

        return in_array($role, [
            'recouvrement',
            'chef recouvrement',
            'receveur',
            'caissier'
        ], true);
    }
}

if (!function_exists('redirectIfAuthenticated')) {
    function redirectIfAuthenticated($target = "/collect_pay/dashboard/index.php")
    {
        if (!empty($_SESSION['user_id'])) {
            header("Location: " . $target);
            exit;
        }
    }
}

if (!function_exists('logoutUser')) {
    function logoutUser()
    {
        $_SESSION = [];

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        session_destroy();

        header("Location: /collect_pay/login.php");
        exit;
    }
}
