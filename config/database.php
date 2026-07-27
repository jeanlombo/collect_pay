<?php
/**
 * Connexion MySQL compatible local, AwardSpace et Railway.
 * Railway expose généralement les variables MYSQLHOST, MYSQLPORT,
 * MYSQLUSER, MYSQLPASSWORD et MYSQLDATABASE.
 */
$host = getenv('DB_HOST') ?: (getenv('MYSQLHOST') ?: 'localhost');
$port = (int) (getenv('DB_PORT') ?: (getenv('MYSQLPORT') ?: 3306));
$dbname = getenv('DB_NAME') ?: (getenv('MYSQLDATABASE') ?: 'collect_pay');
$user = getenv('DB_USER') ?: (getenv('MYSQLUSER') ?: 'root');
$pass = getenv('DB_PASSWORD') ?: (getenv('MYSQLPASSWORD') ?: '123456@@@');

try {
    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    $isProduction = strtolower((string) (getenv('APP_ENV') ?: 'local')) === 'production';
    die($isProduction
        ? 'Connexion à la base de données impossible.'
        : 'Erreur connexion : ' . $e->getMessage());
}
