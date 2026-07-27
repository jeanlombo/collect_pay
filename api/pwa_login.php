<?php
/*
|--------------------------------------------------------------------------
| cOllect_Pay Mobile - API Login PWA
|--------------------------------------------------------------------------
| Compatible :
| - Localhost : /collect_pay/api/pwa_login.php
| - AwardSpace : /api/pwa_login.php
|--------------------------------------------------------------------------
*/

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

header('Content-Type: application/json; charset=utf-8');

function jsonResponse($success, $message, $extra = [])
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    echo json_encode(
        array_merge([
            'success' => $success,
            'message' => $message
        ], $extra),
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

try {
    $dbFile = __DIR__ . '/../config/database.php';

    if (!file_exists($dbFile)) {
        jsonResponse(false, "Fichier config/database.php introuvable.");
    }

    require_once $dbFile;

    if (!isset($pdo) || !($pdo instanceof PDO)) {
        jsonResponse(false, "Connexion PDO indisponible.");
    }

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!is_array($data)) {
        jsonResponse(false, "Requête JSON invalide.");
    }

    $email = trim($data['email'] ?? '');
    $password = (string)($data['password'] ?? '');

    if ($email === '' || $password === '') {
        jsonResponse(false, "Veuillez saisir l’identifiant et le mot de passe.");
    }

    /*
    |--------------------------------------------------------------------------
    | Vérification réelle de la table users
    |--------------------------------------------------------------------------
    */
    $columnsStmt = $pdo->query("SHOW COLUMNS FROM users");
    $columnsRows = $columnsStmt->fetchAll(PDO::FETCH_ASSOC);

    $columns = [];
    foreach ($columnsRows as $col) {
        $columns[] = $col['Field'];
    }

    if (!in_array('email', $columns, true)) {
        jsonResponse(false, "Colonne email introuvable dans users.");
    }

    if (!in_array('password', $columns, true)) {
        jsonResponse(false, "Colonne password introuvable dans users.");
    }

    $whereStatus = "";

    if (in_array('actif', $columns, true)) {
        $whereStatus .= " AND COALESCE(u.actif,1) = 1 ";
    }

    if (in_array('statut', $columns, true)) {
        $whereStatus .= " AND COALESCE(u.statut,'actif') = 'actif' ";
    }

    $sql = "
        SELECT 
            u.id,
            u.nom,
            u.email,
            u.telephone,
            u.password,
            u.role_id,
            u.province_id,
            u.centre_id,
            u.service_id,
            u.niveau,
            u.actif,
            u.statut,
            COALESCE(r.nom_role, '') AS nom_role
        FROM users u
        LEFT JOIN roles r ON r.id = u.role_id
        WHERE u.email = ?
        $whereStatus
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        jsonResponse(false, "Identifiant ou mot de passe incorrect.");
    }

    if (!password_verify($password, $user['password'])) {
        jsonResponse(false, "Identifiant ou mot de passe incorrect.");
    }

    if (in_array('derniere_connexion', $columns, true)) {
        $up = $pdo->prepare("UPDATE users SET derniere_connexion = NOW() WHERE id = ?");
        $up->execute([$user['id']]);
    }

    jsonResponse(true, "Connexion réussie.", [
        'user' => [
            'user_id'     => (int)$user['id'],
            'nom'         => $user['nom'] ?? '',
            'email'       => $user['email'] ?? '',
            'telephone'   => $user['telephone'] ?? '',
            'role_id'     => $user['role_id'] ?? null,
            'role'        => $user['nom_role'] ?: 'AGENT_TERRAIN',
            'province_id' => $user['province_id'] ?? null,
            'centre_id'   => $user['centre_id'] ?? null,
            'service_id'  => $user['service_id'] ?? null,
            'niveau'      => $user['niveau'] ?? 'centre',
            'matricule'   => 'USR-' . (int)$user['id']
        ]
    ]);

} catch (Throwable $e) {
    jsonResponse(false, "Erreur API PWA : " . $e->getMessage());
}
