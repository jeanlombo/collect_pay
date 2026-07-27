<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Connexion MySQL — local et Railway
|--------------------------------------------------------------------------
*/

$estRailway = getenv('RAILWAY_ENVIRONMENT') !== false;

// Variables personnalisées du service collect_pay
$host = getenv('DB_HOST');
$port = getenv('DB_PORT');
$nomBase = getenv('DB_NAME');
$utilisateur = getenv('DB_USER');
$motDePasse = getenv('DB_PASSWORD');

// Compatibilité directe avec les variables Railway MySQL
if (!$host) {
    $host = getenv('MYSQLHOST');
}

if (!$port) {
    $port = getenv('MYSQLPORT');
}

if (!$nomBase) {
    $nomBase = getenv('MYSQLDATABASE');
}

if (!$utilisateur) {
    $utilisateur = getenv('MYSQLUSER');
}

if ($motDePasse === false || $motDePasse === '') {
    $motDePasse = getenv('MYSQLPASSWORD');
}

// Valeurs locales uniquement hors Railway
if (!$estRailway) {
    $host = $host ?: '127.0.0.1';
    $port = $port ?: '3306';
    $nomBase = $nomBase ?: 'collect_pay';
    $utilisateur = $utilisateur ?: 'root';

    if ($motDePasse === false) {
        $motDePasse = '';
    }
}

// Sécurité : empêcher Railway d’utiliser localhost
if ($estRailway && (!$host || $host === 'localhost' || $host === '127.0.0.1')) {
    die(
        'Erreur configuration : DB_HOST n’est pas reliée au service MySQL Railway.'
    );
}

if (!$host || !$port || !$nomBase || !$utilisateur) {
    die(
        'Erreur configuration MySQL : une ou plusieurs variables sont absentes.'
    );
}

$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    $host,
    $port,
    $nomBase
);

try {
    $pdo = new PDO(
        $dsn,
        $utilisateur,
        (string) $motDePasse,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 10,
        ]
    );
} catch (PDOException $exception) {
    error_log('Connexion MySQL impossible : ' . $exception->getMessage());

    die(
        'Erreur connexion à la base de données. Vérifiez les variables MySQL Railway.'
    );
}