<?php

define('APP_NAME', 'cOllect_Pay');
define('APP_VERSION', '1.0.0-railway');

/**
 * URL publique : définir APP_URL dans Railway, par exemple
 * https://mon-service.up.railway.app/collect_pay
 */
$configuredUrl = rtrim((string) (getenv('APP_URL') ?: ''), '/');
if ($configuredUrl !== '') {
    $appUrl = $configuredUrl;
} elseif (PHP_SAPI !== 'cli' && !empty($_SERVER['HTTP_HOST'])) {
    $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
    $httpsEnabled = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower((string) $forwardedProto) === 'https';
    $scheme = $httpsEnabled ? 'https' : 'http';
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $basePath = stripos($script, '/collect_pay/') === 0 ? '/collect_pay' : '';
    $appUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . $basePath;
} else {
    $appUrl = 'http://localhost/collect_pay';
}

define('APP_URL', rtrim($appUrl, '/'));
define('QR_VERIFY_BASE_URL', APP_URL . '/verify.php');

// En production, créer ces variables dans Railway.
define('QR_SECRET_KEY', getenv('QR_SECRET_KEY') ?: 'COLLECT_PAY_SIGNATURE_SECRET_KEY_2026_TSHOPO_DGRT_CHANGE_ME');
define('QR_ENCRYPTION_KEY', getenv('QR_ENCRYPTION_KEY') ?: '12345678901234567890123456789012');

define('MAX_DUPLICATA', 2);
define('PROVINCE_NAME', 'PROVINCE DE LA TSHOPO');
define('REGIE_NAME', 'DIRECTION GENERALE DES RECETTES DE LA TSHOPO');
define('CURRENCY_DEFAULT', 'CDF');
define('DEFAULT_ECHEANCE_NP', 8);
