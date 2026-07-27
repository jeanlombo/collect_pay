<?php
require_once __DIR__ . "/config/database.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = "";

if (!isset($pdo) || !($pdo instanceof PDO)) {
    die("Connexion base de données indisponible. Vérifiez config/database.php.");
}

/*
|--------------------------------------------------------------------------
| Vérification des colonnes existantes
|--------------------------------------------------------------------------
*/
function loginColumnExists(PDO $pdo, string $table, string $column): bool
{
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $stmt->execute([$column]);
        return (bool)$stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
}

$hasActif  = loginColumnExists($pdo, "users", "actif");
$hasStatut = loginColumnExists($pdo, "users", "statut");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === "" || $password === "") {
        $error = "Veuillez saisir l’adresse e-mail et le mot de passe.";
    } else {

        try {

            $whereStatus = "";

            if ($hasActif) {
                $whereStatus = " AND u.actif = 1 ";
            } elseif ($hasStatut) {
                $whereStatus = " AND COALESCE(u.statut,'actif') = 'actif' ";
            }

            $stmt = $pdo->prepare("
                SELECT 
                    u.*,
                    r.nom_role
                FROM users u
                LEFT JOIN roles r ON u.role_id = r.id
                WHERE u.email = ?
                $whereStatus
                LIMIT 1
            ");

            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {

                $_SESSION['user_id']     = $user['id'];
                $_SESSION['nom']         = $user['nom'] ?? '';
                $_SESSION['email']       = $user['email'] ?? '';
                $_SESSION['role_id']     = $user['role_id'] ?? null;
                $_SESSION['role']        = $user['nom_role'] ?? '';
                $_SESSION['nom_role']    = $user['nom_role'] ?? '';
                $_SESSION['province_id'] = $user['province_id'] ?? null;
                $_SESSION['centre_id']   = $user['centre_id'] ?? null;
                $_SESSION['service_id']  = $user['service_id'] ?? null;
                $_SESSION['niveau']      = $user['niveau'] ?? null;

                if (loginColumnExists($pdo, "users", "derniere_connexion")) {
                    $up = $pdo->prepare("UPDATE users SET derniere_connexion = NOW() WHERE id = ?");
                    $up->execute([$user['id']]);
                }

                header("Location: dashboard/index.php");
                exit;

            } else {
                $error = "Email ou mot de passe incorrect.";
            }

        } catch (Exception $e) {
            $error = "Erreur connexion : " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Connexion | cOllect_Pay</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link rel="icon" type="image/png" href="assets/images/favicon.png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<style>
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

body{
    min-height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
    font-family:'Segoe UI',Arial,sans-serif;
    background:
        linear-gradient(rgba(3,12,32,.78), rgba(3,12,32,.82)),
        url("assets/images/bg-login.jpg");
    background-size:cover;
    background-position:center;
    padding:22px;
    overflow-x:hidden;
}

.auth-card{
    width:100%;
    max-width:470px;
    background:rgba(255,255,255,.13);
    backdrop-filter:blur(22px);
    -webkit-backdrop-filter:blur(22px);
    border:1px solid rgba(255,255,255,.22);
    border-radius:32px;
    padding:38px 38px 30px;
    box-shadow:0 30px 80px rgba(0,0,0,.42);
    color:white;
    animation:fadeIn .7s ease;
    position:relative;
    overflow:hidden;
}

.auth-card::before{
    content:"";
    position:absolute;
    top:-80px;
    right:-80px;
    width:190px;
    height:190px;
    background:rgba(255,215,0,.18);
    border-radius:50%;
    filter:blur(6px);
}

.auth-card::after{
    content:"";
    position:absolute;
    bottom:-90px;
    left:-90px;
    width:190px;
    height:190px;
    background:rgba(59,130,246,.18);
    border-radius:50%;
    filter:blur(8px);
}

.auth-content{
    position:relative;
    z-index:2;
}

@keyframes fadeIn{
    from{opacity:0;transform:translateY(35px)}
    to{opacity:1;transform:translateY(0)}
}

.header-logos{
    display:flex;
    justify-content:center;
    align-items:center;
    gap:14px;
    margin-bottom:18px;
}

.header-logos img{
    width:58px;
    height:58px;
    object-fit:contain;
    background:rgba(255,255,255,.92);
    padding:5px;
    border-radius:14px;
    box-shadow:0 10px 25px rgba(0,0,0,.22);
}

.brand-title{
    text-align:center;
    margin-bottom:24px;
}

.brand-title h1{
    font-size:36px;
    font-weight:1000;
    color:#FFD700;
    letter-spacing:-1px;
    margin:0;
    text-shadow:0 8px 25px rgba(0,0,0,.3);
}

.brand-title p{
    color:#e0f2fe;
    font-size:14.5px;
    line-height:1.35;
    margin:8px 0 0;
    font-weight:700;
}

.badge-secure{
    display:inline-block;
    margin-top:12px;
    padding:7px 14px;
    border-radius:999px;
    background:rgba(255,255,255,.16);
    color:white;
    font-size:12px;
    font-weight:900;
    border:1px solid rgba(255,255,255,.22);
}

.form-label{
    color:#fff;
    font-weight:900;
    margin-bottom:8px;
    font-size:14px;
}

.input-group{
    margin-bottom:18px;
    box-shadow:0 12px 30px rgba(0,0,0,.18);
    border-radius:15px;
}

.input-group-text{
    background:#FFD700;
    border:none;
    color:#0f172a;
    font-weight:900;
    width:50px;
    justify-content:center;
    border-radius:15px 0 0 15px;
}

.form-control{
    border:none;
    padding:14px 15px;
    font-size:15px;
    border-radius:0 15px 15px 0;
}

.form-control:focus{
    box-shadow:none;
}

.btn-login{
    width:100%;
    padding:14px;
    border:none;
    border-radius:16px;
    background:linear-gradient(135deg,#FFD700,#facc15);
    color:#0f172a;
    font-size:17px;
    font-weight:1000;
    transition:.3s;
    box-shadow:0 12px 28px rgba(255,215,0,.35);
}

.btn-login:hover{
    transform:translateY(-3px);
    box-shadow:0 18px 38px rgba(255,215,0,.48);
}

.btn-vitrine{
    display:block;
    width:100%;
    text-align:center;
    margin-top:14px;
    padding:13px;
    border-radius:16px;
    text-decoration:none;
    background:rgba(255,255,255,.08);
    border:2px solid rgba(255,255,255,.72);
    color:white;
    font-weight:1000;
    transition:.3s;
}

.btn-vitrine:hover{
    background:white;
    color:#0f3460;
    transform:translateY(-2px);
}

.footer-text{
    margin-top:22px;
    text-align:center;
    font-size:12.5px;
    color:#dbeafe;
    line-height:1.45;
}

.alert{
    border-radius:16px;
    font-weight:800;
}

@media(max-width:520px){
    .auth-card{
        padding:30px 22px 24px;
        border-radius:26px;
    }

    .brand-title h1{
        font-size:31px;
    }

    .header-logos img{
        width:52px;
        height:52px;
    }
}
</style>
</head>

<body>

<div class="auth-card">
    <div class="auth-content">

        <div class="header-logos">
            <?php if (file_exists("assets/images/logo.png")): ?>
                <img src="assets/images/logo.png" alt="Logo cOllect_Pay">
            <?php endif; ?>
        </div>
        <div class="auth-content">
            <p>
                <?php require_once __DIR__ . "/verification_widget.php"; ?>
            </p>

        <div class="brand-title">
            <h1>cOllect_Pay</h1>

            <p>
                Guichet Unique de Canalisation et Maximisation des Recettes Publiques
            </p>

            <span class="badge-secure">
                <i class="fa fa-shield-halved"></i>
                Accès sécurisé
            </span>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <i class="fa fa-triangle-exclamation"></i>
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="POST">

            <label class="form-label">Adresse e-mail</label>
            <div class="input-group">
                <span class="input-group-text">
                    <i class="fa fa-envelope"></i>
                </span>
                <input
                    type="email"
                    name="email"
                    class="form-control"
                    placeholder="Votre adresse e-mail"
                    required
                >
            </div>

            <label class="form-label">Mot de passe</label>
            <div class="input-group">
                <span class="input-group-text">
                    <i class="fa fa-lock"></i>
                </span>
                <input
                    type="password"
                    name="password"
                    class="form-control"
                    placeholder="Votre mot de passe"
                    required
                >
            </div>

            <button class="btn-login" type="submit">
                <i class="fa fa-right-to-bracket"></i>
                Se connecter
            </button>

        </form>

        <a href="index.php" class="btn-vitrine">
            <i class="fa fa-house"></i>
            Retour à la Vitrine
        </a>

        <div class="footer-text">
            © <?= date('Y') ?> cOllect_Pay — Tous droits réservés.
            <br>
            Plateforme Intégrée de Gestion des Recettes Publiques
        </div>

    </div>
</div>


</body>
</html>
