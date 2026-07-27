<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Healthcheck Railway
|--------------------------------------------------------------------------
| Ce fichier vérifie uniquement qu’Apache et PHP fonctionnent.
| Il ne charge volontairement pas la base de données.
|--------------------------------------------------------------------------
*/

http_response_code(200);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

echo json_encode(
    [
        'status' => 'ok',
        'application' => 'cOllect_Pay',
        'serveur' => 'actif',
        'php' => PHP_VERSION,
        'date' => date('Y-m-d H:i:s')
    ],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);

exit;