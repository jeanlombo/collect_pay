<?php
require_once "../../config/database.php";
require_once "../../config/security.php";

checkAuth();
requireRole([
    'SUPER_ADMIN',
    'LIQUIDATEUR'
]);

$numero_nd = $_GET['numero'] ?? null;
$decision  = $_GET['decision'] ?? 'conforme';

if (!$numero_nd) {
    die("Numéro ND manquant.");
}

$stmt = $pdo->prepare("SELECT * FROM notes_debit WHERE numero_nd = ?");
$stmt->execute([$numero_nd]);
$nd = $stmt->fetch();

if (!$nd) {
    die("ND introuvable.");
}

if (!in_array($nd['statut'], ['brouillon', 'en_controle'])) {
    die("Cette ND est déjà traitée.");
}

if (!in_array($decision, ['conforme', 'rejetee', 'corriger'])) {
    die("Décision invalide.");
}

if ($decision === 'conforme') {
    $statut = 'validee';
} elseif ($decision === 'rejetee') {
    $statut = 'rejete';
} else {
    $statut = 'en_controle';
}

$stmt = $pdo->prepare("
    UPDATE notes_debit
    SET 
        statut = ?,
        decision = ?,
        date_validation = NOW(),
        user_validateur_id = ?
    WHERE id = ?
");

$stmt->execute([
    $statut,
    $decision,
    $_SESSION['user_id'],
    $nd['id']
]);

if ($statut === 'validee') {
    header("Location: ../ordonnancement/np_create.php?numero_nd=" . urlencode($numero_nd));
    exit;
}

header("Location: nd_view.php?numero=" . urlencode($numero_nd));
exit;