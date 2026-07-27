<?php
require_once "../auth/check_auth.php";
require_once "../auth/permissions.php";

requirePermission('users', 'edit');

$db = cpDb();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $db->prepare("SELECT id, nom, statut FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("Utilisateur introuvable.");
}

$newStatus = (($user['statut'] ?? 'actif') === 'actif') ? 'inactif' : 'actif';

$stmt = $db->prepare("
    UPDATE users
    SET statut = ?,
        updated_at = NOW()
    WHERE id = ?
");
$stmt->execute([$newStatus, $id]);

logAction('users', 'toggle_status', "Changement statut utilisateur ".$user['nom']." => ".$newStatus);

header("Location: index.php?status=1");
exit;
