<?php

require_once __DIR__ . '/auth/check_auth.php';

header('Content-Type: text/plain; charset=UTF-8');

echo "=== DIAGNOSTIC SESSION COLLECTPAY ===\n\n";

echo "USER ID : ";
var_dump($_SESSION['user_id'] ?? null);

echo "\nNOM : ";
var_dump($_SESSION['user_nom'] ?? $_SESSION['nom'] ?? null);

echo "\nEMAIL : ";
var_dump($_SESSION['user_email'] ?? $_SESSION['email'] ?? null);

echo "\nROLE ID : ";
var_dump($_SESSION['role_id'] ?? null);

echo "\nROLE : ";
var_dump($_SESSION['role'] ?? null);

echo "\nUSER ROLE : ";
var_dump($_SESSION['user_role'] ?? null);

echo "\nNOM ROLE : ";
var_dump($_SESSION['nom_role'] ?? null);

echo "\nROLE CODE : ";
var_dump($_SESSION['role_code'] ?? null);

echo "\n\n=== SESSION COMPLETE ===\n";
print_r($_SESSION);