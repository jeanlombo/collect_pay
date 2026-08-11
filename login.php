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
    box-sizing:border-box;
}

:root{
    --cp-navy:#06182b;
    --cp-navy-2:#0b2b4b;
    --cp-blue:#0f5688;
    --cp-gold:#f5c518;
    --cp-gold-2:#ffd84a;
    --cp-white:#ffffff;
    --cp-soft:#f5f8fb;
    --cp-border:#dbe5ef;
    --cp-text:#17324a;
    --cp-muted:#6f8092;
}

html,body{
    min-height:100%;
}

body{
    margin:0;
    min-height:100vh;
    font-family:"Segoe UI",Arial,sans-serif;
    color:var(--cp-text);
    background:
        radial-gradient(circle at 8% 10%,rgba(245,197,24,.13),transparent 27%),
        radial-gradient(circle at 92% 85%,rgba(42,126,185,.20),transparent 30%),
        linear-gradient(135deg,#04111f 0%,#092847 48%,#0e466f 100%);
    padding:28px;
}

.login-shell{
    width:min(1180px,100%);
    min-height:690px;
    margin:auto;
    display:grid;
    grid-template-columns:1.08fr .92fr;
    background:#fff;
    border-radius:30px;
    overflow:hidden;
    border:1px solid rgba(255,255,255,.18);
    box-shadow:0 32px 90px rgba(0,0,0,.34);
}

/* ================================
   IDENTITÉ
================================ */
.brand-side{
    position:relative;
    padding:52px 54px 44px;
    color:#fff;
    overflow:hidden;
    background:
        linear-gradient(145deg,rgba(3,18,34,.92),rgba(7,46,78,.93)),
        url("assets/images/bg-login.jpg");
    background-size:cover;
    background-position:center;
}

.brand-side::before{
    content:"";
    position:absolute;
    width:350px;
    height:350px;
    border-radius:50%;
    background:rgba(245,197,24,.12);
    top:-170px;
    right:-140px;
}

.brand-side::after{
    content:"";
    position:absolute;
    width:310px;
    height:310px;
    border-radius:50%;
    border:1px solid rgba(255,255,255,.09);
    bottom:-150px;
    left:-130px;
}

.brand-inner{
    position:relative;
    z-index:2;
    height:100%;
    display:flex;
    flex-direction:column;
}

.institution-row{
    display:flex;
    align-items:center;
    gap:16px;
    margin-bottom:54px;
}

.institution-logo{
    width:82px;
    height:82px;
    flex:0 0 82px;
    display:flex;
    align-items:center;
    justify-content:center;
    background:#fff;
    border-radius:21px;
    padding:8px;
    box-shadow:0 15px 36px rgba(0,0,0,.22);
    overflow:hidden;
}

.institution-logo img{
    width:100%;
    height:100%;
    object-fit:contain;
}

.logo-fallback{
    font-size:27px;
    font-weight:1000;
    color:var(--cp-navy);
}

.institution-copy small{
    display:block;
    color:#bcd2e5;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.08em;
    font-size:10px;
    margin-bottom:5px;
}

.institution-copy strong{
    font-size:17px;
    line-height:1.25;
}

.brand-badge{
    width:max-content;
    max-width:100%;
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:8px 13px;
    border-radius:999px;
    border:1px solid rgba(255,255,255,.16);
    background:rgba(255,255,255,.08);
    color:#d9e9f6;
    font-size:11px;
    font-weight:900;
    letter-spacing:.04em;
    text-transform:uppercase;
    backdrop-filter:blur(8px);
}

.brand-name{
    margin:18px 0 0;
    font-size:56px;
    line-height:.98;
    letter-spacing:-2.7px;
    font-weight:1000;
}

.brand-name span{
    color:var(--cp-gold);
}

.brand-description{
    margin:18px 0 0;
    max-width:520px;
    font-size:16px;
    line-height:1.62;
    color:#dceaf5;
    font-weight:650;
}

.feature-grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:12px;
    margin-top:30px;
}

.feature{
    min-height:82px;
    padding:15px 16px;
    border-radius:17px;
    border:1px solid rgba(255,255,255,.13);
    background:rgba(255,255,255,.075);
    backdrop-filter:blur(8px);
}

.feature i{
    color:var(--cp-gold);
    font-size:17px;
    margin-bottom:9px;
}

.feature strong{
    display:block;
    color:#fff;
    font-size:13px;
    margin-bottom:3px;
}

.feature span{
    color:#bcd1e2;
    font-size:11.5px;
    line-height:1.35;
}

.brand-foot{
    margin-top:auto;
    padding-top:28px;
    color:#a8c0d4;
    font-size:11.5px;
    display:flex;
    align-items:center;
    gap:9px;
}

/* ================================
   CONNEXION
================================ */
.auth-side{
    background:
        linear-gradient(180deg,#ffffff 0%,#f8fbfd 100%);
    padding:44px 48px;
    display:flex;
    align-items:center;
}

.auth-wrap{
    width:100%;
    max-width:450px;
    margin:0 auto;
}

.mobile-brand{
    display:none;
}

.login-eyebrow{
    display:inline-flex;
    gap:7px;
    align-items:center;
    color:#1d648f;
    background:#eaf5fc;
    border:1px solid #d2e8f6;
    border-radius:999px;
    padding:7px 11px;
    font-size:10.5px;
    font-weight:900;
    text-transform:uppercase;
    letter-spacing:.06em;
}

.auth-title{
    margin:14px 0 7px;
    color:#102f4b;
    font-weight:1000;
    font-size:31px;
    letter-spacing:-.8px;
}

.auth-subtitle{
    margin:0 0 25px;
    color:var(--cp-muted);
    font-size:14px;
    line-height:1.55;
}

.alert{
    border:0;
    border-radius:14px;
    font-weight:750;
    font-size:13px;
    padding:12px 14px;
}

.form-label{
    color:#264760;
    font-size:12px;
    font-weight:900;
    margin:0 0 7px;
}

.field-wrap{
    position:relative;
    margin-bottom:17px;
}

.field-icon{
    position:absolute;
    left:15px;
    top:50%;
    transform:translateY(-50%);
    color:#7b91a4;
    z-index:2;
}

.form-control.cp-field{
    width:100%;
    min-height:52px;
    padding:12px 46px;
    border:1px solid #cedce8;
    border-radius:14px;
    background:#fff;
    color:#1d364b;
    font-size:14px;
    box-shadow:0 5px 16px rgba(21,64,96,.035);
    transition:.2s ease;
}

.form-control.cp-field:focus{
    border-color:#4b98ca;
    box-shadow:0 0 0 4px rgba(40,132,193,.10);
    background:#fff;
}

.password-toggle{
    position:absolute;
    top:50%;
    right:10px;
    transform:translateY(-50%);
    border:0;
    width:34px;
    height:34px;
    border-radius:9px;
    background:#edf3f7;
    color:#4b6477;
    display:flex;
    align-items:center;
    justify-content:center;
    cursor:pointer;
}

.btn-login{
    width:100%;
    min-height:52px;
    border:0;
    border-radius:14px;
    background:linear-gradient(135deg,#f0ba09,#ffd444);
    color:#10283b;
    font-size:14px;
    font-weight:1000;
    box-shadow:0 12px 27px rgba(226,171,0,.22);
    transition:.2s ease;
}

.btn-login:hover{
    transform:translateY(-1px);
    box-shadow:0 16px 32px rgba(226,171,0,.28);
}

.security-note{
    margin:16px 0 0;
    display:flex;
    gap:9px;
    align-items:flex-start;
    padding:11px 12px;
    background:#f1f7fb;
    color:#557085;
    border:1px solid #dbe8f1;
    border-radius:12px;
    font-size:11.5px;
    line-height:1.45;
}

.security-note i{
    color:#1b779f;
    margin-top:2px;
}

/* ================================
   VÉRIFICATION + VITRINE
================================ */
.public-actions{
    margin-top:19px;
    padding-top:18px;
    border-top:1px solid #e3eaf0;
}

.public-actions-title{
    color:#6b7f91;
    font-size:10.5px;
    text-transform:uppercase;
    font-weight:900;
    letter-spacing:.065em;
    margin-bottom:10px;
}

.login-verification-slot .verif-box{
    margin:0 0 10px!important;
    text-align:left!important;
}

.login-verification-slot .verif-small-text{
    display:none!important;
}

.login-verification-slot .verif-btn-open{
    width:100%!important;
    min-height:48px!important;
    border-radius:13px!important;
    padding:11px 14px!important;
    background:linear-gradient(135deg,#0e3658,#145d8b)!important;
    box-shadow:none!important;
    font-size:13px!important;
}

.btn-vitrine{
    min-height:48px;
    width:100%;
    display:flex;
    align-items:center;
    justify-content:center;
    gap:9px;
    border-radius:13px;
    border:1px solid #c9d9e5;
    background:#fff;
    color:#284b64;
    text-decoration:none;
    font-size:13px;
    font-weight:900;
    transition:.2s ease;
}

.btn-vitrine:hover{
    background:#f5f9fc;
    color:#123b58;
    border-color:#9fbed3;
}

.footer-text{
    margin-top:19px;
    text-align:center;
    color:#8695a3;
    font-size:10.5px;
    line-height:1.5;
}

/* Widget déjà existant : on garde sa logique */
.verif-modal-bg{
    backdrop-filter:blur(5px);
}


/* ================================
   ANIMATIONS PREMIUM V2
================================ */
body::before,
body::after{
    content:"";
    position:fixed;
    border-radius:50%;
    pointer-events:none;
    filter:blur(10px);
    z-index:0;
}
body::before{
    width:280px;
    height:280px;
    left:-90px;
    top:16%;
    background:rgba(245,197,24,.08);
    animation:cpBodyOrbA 8s ease-in-out infinite;
}
body::after{
    width:360px;
    height:360px;
    right:-120px;
    bottom:-80px;
    background:rgba(36,145,214,.10);
    animation:cpBodyOrbB 10s ease-in-out infinite;
}
@keyframes cpBodyOrbA{
    0%,100%{transform:translate3d(0,0,0) scale(1)}
    50%{transform:translate3d(35px,-22px,0) scale(1.12)}
}
@keyframes cpBodyOrbB{
    0%,100%{transform:translate3d(0,0,0) scale(1)}
    50%{transform:translate3d(-28px,-35px,0) scale(1.08)}
}

.login-shell{
    position:relative;
    z-index:1;
    animation:cpShellIn .85s cubic-bezier(.2,.8,.2,1) both;
}
@keyframes cpShellIn{
    from{opacity:0;transform:translateY(22px) scale(.985)}
    to{opacity:1;transform:none}
}

/* subtle animated grid on the institutional panel */
.brand-side::marker{display:none}
.brand-inner::before{
    content:"";
    position:absolute;
    inset:-55px;
    z-index:-1;
    opacity:.14;
    background-image:
        linear-gradient(rgba(255,255,255,.08) 1px,transparent 1px),
        linear-gradient(90deg,rgba(255,255,255,.08) 1px,transparent 1px);
    background-size:42px 42px;
    mask-image:linear-gradient(to bottom,rgba(0,0,0,.85),transparent 88%);
    animation:cpGridDrift 18s linear infinite;
}
@keyframes cpGridDrift{
    from{transform:translate3d(0,0,0)}
    to{transform:translate3d(42px,42px,0)}
}

.institution-row{
    animation:cpSlideRight .75s .08s both;
}
.brand-badge{
    animation:cpSlideRight .75s .16s both;
}
.brand-name{
    animation:cpSlideRight .8s .24s both;
}
.brand-description{
    animation:cpSlideRight .8s .32s both;
}
.feature-grid{
    animation:cpSlideRight .8s .40s both;
}
.brand-foot{
    animation:cpFadeIn .9s .52s both;
}
@keyframes cpSlideRight{
    from{opacity:0;transform:translateX(-24px)}
    to{opacity:1;transform:none}
}
@keyframes cpFadeIn{
    from{opacity:0}
    to{opacity:1}
}

/* logo pulse */
.institution-logo{
    position:relative;
    animation:cpLogoFloat 4.5s ease-in-out infinite;
}
.institution-logo::after{
    content:"";
    position:absolute;
    inset:-7px;
    border-radius:26px;
    border:1px solid rgba(245,197,24,.32);
    opacity:.45;
    animation:cpLogoPulse 2.6s ease-out infinite;
}
@keyframes cpLogoFloat{
    0%,100%{transform:translateY(0)}
    50%{transform:translateY(-6px)}
}
@keyframes cpLogoPulse{
    0%{transform:scale(.96);opacity:.55}
    70%{transform:scale(1.14);opacity:0}
    100%{transform:scale(1.14);opacity:0}
}

/* animated gold highlight on application name */
.brand-name span{
    position:relative;
    display:inline-block;
    background:linear-gradient(90deg,#f5c518 0%,#fff1a6 48%,#f5c518 100%);
    background-size:220% 100%;
    -webkit-background-clip:text;
    background-clip:text;
    color:transparent;
    animation:cpGoldSweep 3.8s linear infinite;
}
@keyframes cpGoldSweep{
    from{background-position:220% 0}
    to{background-position:-40% 0}
}

/* cards come alive */
.feature{
    transition:transform .28s ease,background .28s ease,border-color .28s ease,box-shadow .28s ease;
}
.feature:hover{
    transform:translateY(-5px);
    background:rgba(255,255,255,.11);
    border-color:rgba(245,197,24,.30);
    box-shadow:0 12px 30px rgba(0,0,0,.12);
}
.feature i{
    transition:transform .25s ease;
}
.feature:hover i{
    transform:scale(1.14) rotate(-5deg);
}

/* auth panel entrance */
.login-eyebrow{
    animation:cpSlideLeft .65s .16s both;
}
.auth-title{
    animation:cpSlideLeft .7s .23s both;
}
.auth-subtitle{
    animation:cpSlideLeft .7s .30s both;
}
.auth-wrap form{
    animation:cpSlideLeft .75s .38s both;
}
.security-note{
    animation:cpSlideLeft .75s .46s both;
}
.public-actions{
    animation:cpSlideLeft .75s .54s both;
}
.footer-text{
    animation:cpFadeIn .8s .66s both;
}
@keyframes cpSlideLeft{
    from{opacity:0;transform:translateX(24px)}
    to{opacity:1;transform:none}
}

/* field micro-interactions */
.field-wrap{
    transition:transform .22s ease;
}
.field-wrap:focus-within{
    transform:translateY(-2px);
}
.field-wrap:focus-within .field-icon{
    color:#176ca5;
    transform:translateY(-50%) scale(1.08);
}
.field-icon{
    transition:color .2s ease,transform .2s ease;
}

/* login button animated sheen */
.btn-login{
    position:relative;
    overflow:hidden;
}
.btn-login::before{
    content:"";
    position:absolute;
    top:-35%;
    left:-35%;
    width:34%;
    height:170%;
    transform:rotate(18deg);
    background:linear-gradient(90deg,transparent,rgba(255,255,255,.55),transparent);
    animation:cpButtonSheen 3.4s ease-in-out infinite;
}
@keyframes cpButtonSheen{
    0%,58%{left:-40%}
    82%,100%{left:120%}
}
.btn-login:active{
    transform:translateY(0) scale(.99);
}

/* verification and vitrine buttons */
.login-verification-slot .verif-btn-open,
.btn-vitrine{
    position:relative;
    overflow:hidden;
    transition:transform .25s ease,box-shadow .25s ease,border-color .25s ease;
}
.login-verification-slot .verif-btn-open:hover,
.btn-vitrine:hover{
    transform:translateY(-2px);
}
.login-verification-slot .verif-btn-open::after,
.btn-vitrine::after{
    content:"";
    position:absolute;
    inset:0;
    background:linear-gradient(120deg,transparent 0%,rgba(255,255,255,.16) 50%,transparent 100%);
    transform:translateX(-120%);
    transition:transform .55s ease;
}
.login-verification-slot .verif-btn-open:hover::after,
.btn-vitrine:hover::after{
    transform:translateX(120%);
}

/* animated secure status */
.security-note i{
    animation:cpShieldPulse 2.2s ease-in-out infinite;
}
@keyframes cpShieldPulse{
    0%,100%{transform:scale(1)}
    50%{transform:scale(1.13)}
}

/* decorative moving lines on brand panel */
.brand-side .cp-line{
    position:absolute;
    height:1px;
    width:45%;
    background:linear-gradient(90deg,transparent,rgba(93,202,255,.30),transparent);
    z-index:1;
    animation:cpLineMove 6s linear infinite;
}
.brand-side .cp-line.one{top:28%;left:-20%}
.brand-side .cp-line.two{top:64%;right:-24%;animation-delay:-3s}
@keyframes cpLineMove{
    0%{transform:translateX(0) rotate(-10deg);opacity:0}
    20%{opacity:.7}
    80%{opacity:.7}
    100%{transform:translateX(190%) rotate(-10deg);opacity:0}
}

/* respect users who prefer reduced motion */
@media (prefers-reduced-motion: reduce){
    *,
    *::before,
    *::after{
        animation-duration:.001ms!important;
        animation-iteration-count:1!important;
        scroll-behavior:auto!important;
        transition-duration:.001ms!important;
    }
}

/* ================================
   RESPONSIVE
================================ */
@media(max-width:960px){
    body{
        padding:16px;
    }

    .login-shell{
        grid-template-columns:1fr;
        min-height:auto;
        max-width:650px;
    }

    .brand-side{
        padding:30px 32px;
    }

    .institution-row{
        margin-bottom:24px;
    }

    .feature-grid,
    .brand-foot{
        display:none;
    }

    .brand-name{
        font-size:40px;
    }

    .brand-description{
        font-size:14px;
    }

    .auth-side{
        padding:34px 32px;
    }
}

@media(max-width:560px){
    body{
        padding:0;
        background:#f6f9fc;
    }

    .login-shell{
        border-radius:0;
        min-height:100vh;
        box-shadow:none;
    }

    .brand-side{
        padding:25px 22px 28px;
    }

    .institution-logo{
        width:62px;
        height:62px;
        flex-basis:62px;
        border-radius:16px;
    }

    .institution-copy strong{
        font-size:14px;
    }

    .brand-name{
        font-size:35px;
    }

    .brand-description{
        margin-top:12px;
    }

    .auth-side{
        padding:28px 22px 32px;
    }

    .auth-title{
        font-size:27px;
    }
}
</style>
</head>

<body>

<div class="login-shell">

    <!-- IDENTITÉ INSTITUTIONNELLE -->
    <section class="brand-side">
        <span class="cp-line one"></span>
        <span class="cp-line two"></span>
        <div class="brand-inner">

            <div class="institution-row">
                <div class="institution-logo">
                    <?php if (file_exists(__DIR__ . "/assets/images/logo.png")): ?>
                        <img src="assets/images/logo.png" alt="Logo officiel">
                    <?php else: ?>
                        <span class="logo-fallback">CP</span>
                    <?php endif; ?>
                </div>

                <div class="institution-copy">
                    <small>Plateforme officielle</small>
                    <strong>Guichet Unique Digital<br>des Recettes Publiques</strong>
                </div>
            </div>

            <div>
                <span class="brand-badge">
                    <i class="fa-solid fa-shield-halved"></i>
                    Plateforme fiscale sécurisée
                </span>

                <h1 class="brand-name">
                    cOllect_<span>Pay</span>
                </h1>

                <p class="brand-description">
                    Système intégré de canalisation, de mobilisation,
                    de sécurisation et de maximisation des recettes publiques.
                </p>

                <div class="feature-grid">
                    <div class="feature">
                        <i class="fa-solid fa-file-circle-check"></i>
                        <strong>Documents sécurisés</strong>
                        <span>NT, ND, NP, NPF, AMR et quittances vérifiables.</span>
                    </div>

                    <div class="feature">
                        <i class="fa-solid fa-qrcode"></i>
                        <strong>Contrôle QR</strong>
                        <span>Vérification et traçabilité anti-fraude.</span>
                    </div>

                    <div class="feature">
                        <i class="fa-solid fa-building-columns"></i>
                        <strong>Recettes centralisées</strong>
                        <span>Suivi des paiements et apurements en temps réel.</span>
                    </div>

                    <div class="feature">
                        <i class="fa-solid fa-chart-line"></i>
                        <strong>Pilotage décisionnel</strong>
                        <span>Rapports fiscaux et statistiques consolidées.</span>
                    </div>
                </div>
            </div>

            <div class="brand-foot">
                <i class="fa-solid fa-lock"></i>
                Accès réservé aux utilisateurs autorisés.
            </div>
        </div>
    </section>

    <!-- CONNEXION -->
    <section class="auth-side">
        <div class="auth-wrap">

            <span class="login-eyebrow">
                <i class="fa-solid fa-user-shield"></i>
                Accès sécurisé
            </span>

            <h2 class="auth-title">Connexion à votre espace</h2>
            <p class="auth-subtitle">
                Saisissez vos identifiants pour accéder au Guichet Unique CollectPay.
            </p>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i>
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form method="POST" autocomplete="on">

                <label class="form-label">Adresse e-mail</label>
                <div class="field-wrap">
                    <i class="fa-solid fa-envelope field-icon"></i>
                    <input
                        type="email"
                        name="email"
                        class="form-control cp-field"
                        placeholder="Votre adresse e-mail"
                        autocomplete="email"
                        required
                        autofocus
                    >
                </div>

                <label class="form-label">Mot de passe</label>
                <div class="field-wrap">
                    <i class="fa-solid fa-lock field-icon"></i>
                    <input
                        type="password"
                        name="password"
                        id="password"
                        class="form-control cp-field"
                        placeholder="Votre mot de passe"
                        autocomplete="current-password"
                        required
                    >

                    <button
                        type="button"
                        class="password-toggle"
                        onclick="togglePassword()"
                        aria-label="Afficher ou masquer le mot de passe"
                    >
                        <i class="fa-solid fa-eye" id="passwordEye"></i>
                    </button>
                </div>

                <button class="btn-login" type="submit">
                    <i class="fa-solid fa-right-to-bracket me-1"></i>
                    Se connecter
                </button>

            </form>

            <div class="security-note">
                <i class="fa-solid fa-shield-check"></i>
                <span>
                    Connexion protégée. Les accès et opérations sont contrôlés
                    selon le rôle de l’utilisateur.
                </span>
            </div>

            <div class="public-actions">
                <div class="public-actions-title">
                    Services publics
                </div>

                <!-- Le formulaire existant est conservé -->
                <div class="login-verification-slot">
                    <?php require_once __DIR__ . "/verification_widget.php"; ?>
                </div>

                <!-- Le lien existant vers la vitrine est conservé -->
                <a href="index.php" class="btn-vitrine">
                    <i class="fa-solid fa-house"></i>
                    Retour à la Vitrine
                </a>
            </div>

            <div class="footer-text">
                © <?= date('Y') ?> cOllect_Pay — Tous droits réservés.<br>
                Plateforme Intégrée de Gestion des Recettes Publiques
            </div>

        </div>
    </section>

</div>

<script>
function togglePassword(){
    const input = document.getElementById('password');
    const icon = document.getElementById('passwordEye');

    if(!input){
        return;
    }

    const visible = input.type === 'text';
    input.type = visible ? 'password' : 'text';

    if(icon){
        icon.classList.toggle('fa-eye', visible);
        icon.classList.toggle('fa-eye-slash', !visible);
    }
}
</script>

</body>
</html>
