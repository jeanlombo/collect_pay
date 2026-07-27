<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Configuration générale de cOllect_Pay
|--------------------------------------------------------------------------
*/

define('APP_NAME', 'cOllect_Pay');
define('APP_COMPANY', 'LUXORIA PUBLIC REVENUE SUITE');

/*
|--------------------------------------------------------------------------
| Détection de l'environnement Railway
|--------------------------------------------------------------------------
*/

$estRailway = getenv('RAILWAY_ENVIRONMENT') !== false
    || getenv('RAILWAY_PUBLIC_DOMAIN') !== false;

/*
|--------------------------------------------------------------------------
| URL publique de l'application
|--------------------------------------------------------------------------
|
| Sur Railway, il ne faut jamais ajouter le port interne 8080 à l'URL
| publique. Railway gère automatiquement le HTTPS et le routage.
|
*/

$urlVariable = trim((string) getenv('APP_URL'));

if ($urlVariable !== '') {
    $appUrl = $urlVariable;
} elseif ($estRailway) {
    $domaineRailway = trim(
        (string) getenv('RAILWAY_PUBLIC_DOMAIN')
    );

    if ($domaineRailway !== '') {
        $appUrl = 'https://' . $domaineRailway;
    } else {
        $appUrl = 'https://collectpay-production.up.railway.app';
    }
} else {
    $appUrl = 'http://localhost/collect_pay';
}

/*
|--------------------------------------------------------------------------
| Nettoyage de l'URL
|--------------------------------------------------------------------------
*/

$appUrl = rtrim($appUrl, '/');

/*
 * Supprimer les ports internes Railway éventuellement présents.
 */
$appUrl = preg_replace(
    '#:(8080|80|443)$#',
    '',
    $appUrl
);

define('APP_URL', $appUrl . '/');

define('APP_EMAIL', 'contact@collectpay.cd');
define('APP_PHONE', '+243 000 000 000');

date_default_timezone_set('Africa/Kinshasa');