<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../config/database.php";

$error = "";
$emailSaisi = "";

/*
|--------------------------------------------------------------------------
| Configuration de la sécurité de connexion
|--------------------------------------------------------------------------
*/

$maximumTentatives = 5;
$dureeBlocageMinutes = 15;

/*
|--------------------------------------------------------------------------
| Fonctions utilitaires
|--------------------------------------------------------------------------
*/

function h($v)
{
    return htmlspecialchars(
        $v ?? "",
        ENT_QUOTES,
        "UTF-8"
    );
}

function getClientIp(): string
{
    /*
    |--------------------------------------------------------------------------
    | REMOTE_ADDR reste la valeur la plus sûre.
    |--------------------------------------------------------------------------
    */

    return substr(
        (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'),
        0,
        45
    );
}

function tableLoginAttemptsExists(mysqli $conn): bool
{
    $result = $conn->query("
        SHOW TABLES LIKE 'login_attempts'
    ");

    return $result && $result->num_rows > 0;
}

function getLoginAttempt(
    mysqli $conn,
    string $identifiant,
    string $adresseIp
): ?array {
    $stmt = $conn->prepare("
        SELECT
            id,
            identifiant,
            adresse_ip,
            tentatives,
            derniere_tentative,
            bloque_jusqua
        FROM login_attempts
        WHERE identifiant = ?
          AND adresse_ip = ?
        LIMIT 1
    ");

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param(
        "ss",
        $identifiant,
        $adresseIp
    );

    $stmt->execute();

    $row = $stmt
        ->get_result()
        ->fetch_assoc();

    $stmt->close();

    return $row ?: null;
}

function getRemainingBlockingSeconds(?string $bloqueJusqua): int
{
    if (empty($bloqueJusqua)) {
        return 0;
    }

    $blockingTimestamp = strtotime($bloqueJusqua);

    if ($blockingTimestamp === false) {
        return 0;
    }

    return max(
        0,
        $blockingTimestamp - time()
    );
}

function formatRemainingTime(int $seconds): string
{
    if ($seconds <= 0) {
        return "quelques instants";
    }

    $minutes = (int)ceil($seconds / 60);

    if ($minutes <= 1) {
        return "moins d’une minute";
    }

    return $minutes . " minutes";
}

function resetExpiredBlocking(
    mysqli $conn,
    int $attemptId
): void {
    $stmt = $conn->prepare("
        UPDATE login_attempts
        SET
            tentatives = 0,
            derniere_tentative = NULL,
            bloque_jusqua = NULL
        WHERE id = ?
    ");

    if (!$stmt) {
        return;
    }

    $stmt->bind_param(
        "i",
        $attemptId
    );

    $stmt->execute();
    $stmt->close();
}

function registerFailedAttempt(
    mysqli $conn,
    string $identifiant,
    string $adresseIp,
    int $maximumTentatives,
    int $dureeBlocageMinutes
): array {
    $currentAttempt = getLoginAttempt(
        $conn,
        $identifiant,
        $adresseIp
    );

    if (!$currentAttempt) {
        $stmt = $conn->prepare("
            INSERT INTO login_attempts
            (
                identifiant,
                adresse_ip,
                tentatives,
                derniere_tentative,
                bloque_jusqua
            )
            VALUES (?, ?, 1, NOW(), NULL)
        ");

        if ($stmt) {
            $stmt->bind_param(
                "ss",
                $identifiant,
                $adresseIp
            );

            $stmt->execute();
            $stmt->close();
        }

        return [
            'tentatives' => 1,
            'bloque' => false,
            'bloque_jusqua' => null
        ];
    }

    $attemptId = (int)$currentAttempt['id'];

    /*
    |--------------------------------------------------------------------------
    | Si un ancien blocage est expiré, recommencer le compteur
    |--------------------------------------------------------------------------
    */

    $remainingSeconds = getRemainingBlockingSeconds(
        $currentAttempt['bloque_jusqua'] ?? null
    );

    if (
        !empty($currentAttempt['bloque_jusqua']) &&
        $remainingSeconds <= 0
    ) {
        resetExpiredBlocking(
            $conn,
            $attemptId
        );

        $currentAttempt['tentatives'] = 0;
        $currentAttempt['bloque_jusqua'] = null;
    }

    $newAttemptCount =
        (int)$currentAttempt['tentatives'] + 1;

    if ($newAttemptCount >= $maximumTentatives) {
        $stmt = $conn->prepare("
            UPDATE login_attempts
            SET
                tentatives = ?,
                derniere_tentative = NOW(),
                bloque_jusqua = DATE_ADD(
                    NOW(),
                    INTERVAL ? MINUTE
                )
            WHERE id = ?
        ");

        if ($stmt) {
            $stmt->bind_param(
                "iii",
                $newAttemptCount,
                $dureeBlocageMinutes,
                $attemptId
            );

            $stmt->execute();
            $stmt->close();
        }

        return [
            'tentatives' => $newAttemptCount,
            'bloque' => true,
            'bloque_jusqua' => date(
                'Y-m-d H:i:s',
                time() + ($dureeBlocageMinutes * 60)
            )
        ];
    }

    $stmt = $conn->prepare("
        UPDATE login_attempts
        SET
            tentatives = ?,
            derniere_tentative = NOW(),
            bloque_jusqua = NULL
        WHERE id = ?
    ");

    if ($stmt) {
        $stmt->bind_param(
            "ii",
            $newAttemptCount,
            $attemptId
        );

        $stmt->execute();
        $stmt->close();
    }

    return [
        'tentatives' => $newAttemptCount,
        'bloque' => false,
        'bloque_jusqua' => null
    ];
}

function clearLoginAttempts(
    mysqli $conn,
    string $identifiant,
    string $adresseIp
): void {
    $stmt = $conn->prepare("
        DELETE FROM login_attempts
        WHERE identifiant = ?
          AND adresse_ip = ?
    ");

    if (!$stmt) {
        return;
    }

    $stmt->bind_param(
        "ss",
        $identifiant,
        $adresseIp
    );

    $stmt->execute();
    $stmt->close();
}

/*
|--------------------------------------------------------------------------
| Message de session expirée
|--------------------------------------------------------------------------
*/

if (
    isset($_GET['expired']) &&
    (string)$_GET['expired'] === '1'
) {
    $error =
        "Votre session a expiré après une période d’inactivité. " .
        "Veuillez vous reconnecter.";
}

/*
|--------------------------------------------------------------------------
| Traitement du formulaire
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(
        trim(
            (string)($_POST['email'] ?? '')
        )
    );

    $emailSaisi = $email;

    $mot_de_passe =
        (string)($_POST['mot_de_passe'] ?? '');

    $adresseIp = getClientIp();

    if ($email === '' || $mot_de_passe === '') {
        $error = "Veuillez remplir tous les champs.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Veuillez saisir une adresse e-mail valide.";
    } else {
        /*
        |--------------------------------------------------------------------------
        | Vérification du module de limitation
        |--------------------------------------------------------------------------
        */

        $attemptTableAvailable =
            tableLoginAttemptsExists($conn);

        $isBlocked = false;

        if ($attemptTableAvailable) {
            $attempt = getLoginAttempt(
                $conn,
                $email,
                $adresseIp
            );

            if ($attempt) {
                $remainingSeconds =
                    getRemainingBlockingSeconds(
                        $attempt['bloque_jusqua'] ?? null
                    );

                if ($remainingSeconds > 0) {
                    $isBlocked = true;

                    $error =
                        "Trop de tentatives de connexion. " .
                        "Votre accès est temporairement bloqué. " .
                        "Réessayez dans " .
                        formatRemainingTime($remainingSeconds) .
                        ".";
                } elseif (
                    !empty($attempt['bloque_jusqua'])
                ) {
                    resetExpiredBlocking(
                        $conn,
                        (int)$attempt['id']
                    );
                }
            }
        }

        if (!$isBlocked) {
            $stmt = $conn->prepare("
                SELECT
                    u.*,
                    r.nom_role
                FROM utilisateurs u
                INNER JOIN roles r
                    ON r.id = u.role_id
                WHERE u.email = ?
                  AND u.statut = 'actif'
                LIMIT 1
            ");

            if (!$stmt) {
                $error =
                    "Une erreur technique est survenue. " .
                    "Veuillez réessayer.";
            } else {
                $stmt->bind_param(
                    "s",
                    $email
                );

                $stmt->execute();

                $user = $stmt
                    ->get_result()
                    ->fetch_assoc();

                $stmt->close();

                if (
                    $user &&
                    password_verify(
                        $mot_de_passe,
                        $user['mot_de_passe']
                    )
                ) {
                    /*
                    |--------------------------------------------------------------------------
                    | Suppression des anciennes tentatives
                    |--------------------------------------------------------------------------
                    */

                    if ($attemptTableAvailable) {
                        clearLoginAttempts(
                            $conn,
                            $email,
                            $adresseIp
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Protection contre la fixation de session
                    |--------------------------------------------------------------------------
                    */

                    session_regenerate_id(true);

                    /*
                    |--------------------------------------------------------------------------
                    | Sessions originales conservées
                    |--------------------------------------------------------------------------
                    */

                    $_SESSION['user_id'] =
                        (int)$user['id'];

                    $_SESSION['user_nom'] =
                        $user['nom'];

                    $_SESSION['nom'] =
                        $user['nom'];

                    $_SESSION['user_email'] =
                        $user['email'];

                    $_SESSION['email'] =
                        $user['email'];

                    /*
                    |--------------------------------------------------------------------------
                    | Synchronisation complète du rôle
                    |--------------------------------------------------------------------------
                    | Plusieurs anciens modules CollectPay utilisent des clés différentes
                    | pour lire le rôle. Toutes ces clés pointent maintenant vers le même
                    | nom de rôle afin d'éviter "aucun rôle" et les faux refus d'accès.
                    |--------------------------------------------------------------------------
                    */

                    $_SESSION['role_id'] =
                        (int)$user['role_id'];

                    $_SESSION['role'] =
                        $user['nom_role'];

                    $_SESSION['nom_role'] =
                        $user['nom_role'];

                    $_SESSION['user_role'] =
                        $user['nom_role'];

                    $_SESSION['role_code'] =
                        $user['nom_role'];

                    $_SESSION['role_nom'] =
                        $user['nom_role'];

                    $_SESSION['user_role_code'] =
                        $user['nom_role'];

                    $_SESSION['user_role_nom'] =
                        $user['nom_role'];

                    /*
                    |--------------------------------------------------------------------------
                    | Informations de sécurité de session
                    |--------------------------------------------------------------------------
                    */

                    $_SESSION['LAST_ACTIVITY'] = time();
                    $_SESSION['CREATED'] = time();
                    $_SESSION['login_ip'] = $adresseIp;
                    $_SESSION['login_time'] = date(
                        'Y-m-d H:i:s'
                    );

                    header(
                        "Location: ../dashboard/index.php"
                    );

                    exit;
                }

                /*
                |--------------------------------------------------------------------------
                | Mauvais identifiants
                |--------------------------------------------------------------------------
                */

                if ($attemptTableAvailable) {
                    $failedAttempt =
                        registerFailedAttempt(
                            $conn,
                            $email,
                            $adresseIp,
                            $maximumTentatives,
                            $dureeBlocageMinutes
                        );

                    if ($failedAttempt['bloque']) {
                        $error =
                            "Trop de tentatives incorrectes. " .
                            "Votre accès est bloqué pendant " .
                            $dureeBlocageMinutes .
                            " minutes.";
                    } else {
                        $remainingAttempts =
                            $maximumTentatives -
                            (int)$failedAttempt['tentatives'];

                        if ($remainingAttempts > 0) {
                            $error =
                                "Email ou mot de passe incorrect. " .
                                "Il vous reste " .
                                $remainingAttempts .
                                " tentative(s).";
                        } else {
                            $error =
                                "Email ou mot de passe incorrect.";
                        }
                    }
                } else {
                    /*
                    |--------------------------------------------------------------------------
                    | Le login continue même si la table n’a pas encore été créée
                    |--------------------------------------------------------------------------
                    */

                    $error =
                        "Email ou mot de passe incorrect.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Connexion | <?= APP_NAME ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>
*{box-sizing:border-box}
body{
    min-height:100vh;margin:0;font-family:Segoe UI,Arial,sans-serif;
    background:linear-gradient(135deg,#eef5ff,#ffffff);
    display:flex;align-items:center;justify-content:center;
}
.login-wrapper{
    width:88%;max-width:1100px;min-height:620px;background:white;
    border-radius:24px;overflow:hidden;
    box-shadow:0 25px 55px rgba(15,23,42,.18);
    display:grid;grid-template-columns:1.05fr .95fr;
}
.brand-side{
    background:linear-gradient(rgba(0,55,130,.92),rgba(0,20,70,.96)),url("../assets/img/login-bg.jpg");
    background-size:cover;background-position:center;color:white;
    padding:35px 45px;display:flex;flex-direction:column;justify-content:center;
}
.back-link{
    color:#dbeafe;text-decoration:none;font-weight:900;margin-bottom:18px;display:inline-block;
}
.back-link:hover{color:white}
.logo-box{
    background:white;border-radius:24px;padding:18px;width:90%;max-width:430px;
    margin:0 auto 25px;display:flex;align-items:center;justify-content:center;
    box-shadow:0 18px 40px rgba(0,0,0,.30);
}
.logo-box img{width:100%;height:110px;object-fit:contain}
.gold-line{width:130px;height:4px;background:#f6bd00;border-radius:50px;margin:10px auto 25px}
.brand-title{text-align:center}
.brand-title h1{font-size:34px;font-weight:900;color:white;margin-bottom:10px}
.brand-title h4{font-size:18px;font-weight:900;color:white}
.brand-title p{margin-top:20px;font-size:15px;line-height:1.55;color:white}
.features{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-top:30px}
.feature{text-align:center;background:rgba(255,255,255,.08);padding:14px 8px;border-radius:16px}
.feature i{color:white;font-size:28px}.feature strong{display:block;margin-top:8px;color:white;font-size:12px}.feature span{color:#dbeafe;font-size:11px}
.form-side{padding:45px 55px;display:flex;align-items:center;justify-content:center}
.form-box{width:100%;max-width:430px}
.user-icon{width:65px;height:65px;background:#eaf1ff;color:#0b57d0;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:34px;margin:0 auto 20px}
.form-box h2{text-align:center;font-weight:900;color:#0f172a;font-size:30px}
.form-box p{text-align:center;color:#64748b;margin-bottom:28px;font-size:15px}
.input-group-text{background:white;border-radius:13px 0 0 13px;padding:12px 15px}
.form-control{border-radius:0 13px 13px 0;padding:12px;font-size:14px}
.form-label{font-weight:800;color:#1e293b;font-size:14px}
.btn-login{width:100%;padding:13px;border:none;border-radius:13px;background:linear-gradient(135deg,#0b57d0,#003fa3);color:white;font-weight:900;margin-top:12px}
.btn-login:hover{color:white;opacity:.95}
.footer-login{text-align:center;margin-top:40px;color:#64748b;font-size:13px}
.alert{border-radius:13px;padding:10px 14px}

#loader{
    position:fixed;inset:0;background:rgba(255,255,255,.92);
    display:none;align-items:center;justify-content:center;z-index:9999;
}
.spinner{
    width:65px;height:65px;border:6px solid #dbeafe;border-top:6px solid #0b57d0;
    border-radius:50%;animation:spin 1s linear infinite;margin:auto;
}
.loader-box{text-align:center}.loader-box p{margin-top:18px;font-weight:900;color:#0f172a}
@keyframes spin{to{transform:rotate(360deg)}}

.mobile-back{display:none;text-align:center;margin-bottom:18px}
.mobile-back a{color:#0b57d0;font-weight:900;text-decoration:none}

@media(max-width:900px){
    body{padding:18px}.login-wrapper{width:100%;grid-template-columns:1fr;min-height:auto}
    .brand-side{display:none}.form-side{padding:35px 22px}.mobile-back{display:block}
}
</style>
</head>

<body>

<div id="loader">
    <div class="loader-box">
        <div class="spinner"></div>
        <p>Connexion en cours...</p>
    </div>
</div>

<div class="login-wrapper">

    <div class="brand-side">
        <a href="../vitrine/index.php" class="back-link">← Retour à la vitrine</a>

        <div class="logo-box">
            <img src="../assets/img/logo_drc_gold.jpg" alt="DRC Gold Trading SA">
        </div>

        <div class="gold-line"></div>

        <div class="brand-title">
            <h1>EASYCOMPTA</h1>
            <h4>ERP COMPTABLE OHADA PREMIUM</h4>
            <p>La solution complète pour une gestion comptable fiable, sécurisée et conforme aux normes OHADA.</p>
        </div>

        <div class="features">
            <div class="feature"><i class="bi bi-shield-check"></i><strong>SÉCURISÉ</strong><span>Données protégées</span></div>
            <div class="feature"><i class="bi bi-bar-chart-line"></i><strong>PERFORMANT</strong><span>Rapports en temps réel</span></div>
            <div class="feature"><i class="bi bi-check-circle"></i><strong>CONFORME</strong><span>Normes OHADA</span></div>
            <div class="feature"><i class="bi bi-headset"></i><strong>SUPPORT</strong><span>Assistance dédiée</span></div>
        </div>
    </div>

    <div class="form-side">
        <div class="form-box">

            <div class="mobile-back">
                <a href="../vitrine/index.php">← Retour à la vitrine</a>
            </div>

            <div class="user-icon"><i class="bi bi-person-fill"></i></div>

            <h2>Connexion</h2>
            <p>Veuillez saisir vos identifiants pour accéder à votre espace.</p>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= h($error) ?></div>
            <?php endif; ?>

            <form method="POST" onsubmit="showLoader()">
                <div class="mb-3">
                    <label class="form-label">Adresse e-mail</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input
                            type="email"
                            name="email"
                            class="form-control"
                            placeholder="Entrez votre email"
                            value="<?= h($emailSaisi) ?>"
                            autocomplete="email"
                            required
                        >
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Mot de passe</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input
                            type="password"
                            name="mot_de_passe"
                            id="password"
                            class="form-control"
                            placeholder="Votre mot de passe"
                            autocomplete="current-password"
                            required
                        >
                        <span class="input-group-text" onclick="togglePassword()" style="cursor:pointer"><i class="bi bi-eye"></i></span>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3" style="font-size:14px">
                    <label><input type="checkbox"> Se souvenir de moi</label>
                    <a href="#" class="text-decoration-none fw-bold">Mot de passe oublié ?</a>
                </div>

                <button class="btn btn-login">
                    <i class="bi bi-lock-fill me-2"></i> Se connecter
                </button>
            </form>

            <div class="footer-login">
                © <?= date("Y") ?> EasyCompta. Tous droits réservés.
            </div>
        </div>
    </div>

</div>

<script>
function showLoader(){
    document.getElementById("loader").style.display = "flex";
}

function togglePassword(){
    const input = document.getElementById("password");
    input.type = input.type === "password" ? "text" : "password";
}
</script>

</body>
</html>
```
