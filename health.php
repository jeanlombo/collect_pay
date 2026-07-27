<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

/*
|--------------------------------------------------------------------------
| Contrôle de santé Railway
|--------------------------------------------------------------------------
| Cette page doit toujours répondre avec le code HTTP 200 lorsque PHP et
| Apache fonctionnent. La base de données est vérifiée séparément, mais son
| indisponibilité ne doit pas faire échouer le déploiement Railway.
|--------------------------------------------------------------------------
*/

$resultat = [
    'status'      => 'ok',
    'application' => 'cOllect_Pay',
    'php'         => PHP_VERSION,
    'serveur'     => 'actif',
    'database'    => 'non_configuree',
    'date'        => date('Y-m-d H:i:s'),
];

try {
    $fichierDatabase = __DIR__ . '/config/database.php';

    if (is_file($fichierDatabase)) {
        require $fichierDatabase;

        if (isset($pdo) && $pdo instanceof PDO) {
            $pdo->query('SELECT 1');
            $resultat['database'] = 'connectee';
        } elseif (isset($conn) && $conn instanceof mysqli) {
            $conn->query('SELECT 1');
            $resultat['database'] = 'connectee';
        } elseif (isset($mysqli) && $mysqli instanceof mysqli) {
            $mysqli->query('SELECT 1');
            $resultat['database'] = 'connectee';
        } else {
            $resultat['database'] = 'connexion_non_detectee';
        }
    } else {
        $resultat['database'] = 'fichier_configuration_absent';
    }
} catch (Throwable $exception) {
    /*
     * Ne pas retourner HTTP 503 ici.
     * Railway doit pouvoir valider Apache et PHP avant la création de MySQL.
     */
    $resultat['database'] = 'indisponible';
    $resultat['database_message'] = 'La base de données sera configurée après le déploiement.';
}

http_response_code(200);

echo json_encode(
    $resultat,
    JSON_UNESCAPED_UNICODE |
    JSON_UNESCAPED_SLASHES |
    JSON_PRETTY_PRINT
);