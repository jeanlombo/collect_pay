<?php
require_once __DIR__ . "/config/database.php";

$password = password_hash("admin123", PASSWORD_DEFAULT);

$role = $pdo->query("SELECT id FROM roles WHERE nom_role='SUPER_ADMIN' LIMIT 1")->fetch();

$province = $pdo->query("SELECT id FROM provinces LIMIT 1")->fetch();

$centre = $pdo->query("SELECT id FROM centres LIMIT 1")->fetch();

if (!$role) die("Rôle SUPER_ADMIN introuvable.");
if (!$province) die("Province introuvable.");
if (!$centre) die("Centre introuvable. Crée d'abord un centre.");

$stmt = $pdo->prepare("
    INSERT INTO users
    (nom, email, password, role_id, province_id, centre_id, niveau)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");

$stmt->execute([
    "Administrateur Principal",
    "admin@collectpay.cd",
    $password,
    $role['id'],
    $province['id'],
    $centre['id'],
    "national"
]);

echo "Admin créé : admin@collectpay.cd / admin123";