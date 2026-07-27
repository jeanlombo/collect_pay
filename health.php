<?php
header('Content-Type: application/json; charset=utf-8');

$result = [
    'status' => 'ok',
    'application' => 'cOllect_Pay',
    'php' => PHP_VERSION,
    'database' => 'non_verifiee',
];

try {
    require __DIR__ . '/config/database.php';
    $pdo->query('SELECT 1');
    $result['database'] = 'connectee';
} catch (Throwable $e) {
    http_response_code(503);
    $result['status'] = 'degrade';
    $result['database'] = 'indisponible';
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
