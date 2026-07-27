<?php
require_once "../../config/database.php";
require_once "../../config/security.php";



$numero_nt = $_GET['numero'] ?? null;

if (!$numero_nt) {
    die("Numéro NT manquant.");
}

$stmt = $pdo->prepare("SELECT * FROM notes_taxation WHERE numero_nt = ?");
$stmt->execute([$numero_nt]);
$nt = $stmt->fetch();

if (!$nt) {
    die("NT introuvable.");
}

if ($nt['statut'] !== 'brouillon') {
    die("Cette NT ne peut plus être soumise à liquidation.");
}

$stmt = $pdo->prepare("
    SELECT COUNT(*) AS total
    FROM notes_taxation_details
    WHERE note_taxation_id = ?
");
$stmt->execute([$nt['id']]);
$totalDetails = $stmt->fetch()['total'];

if ($totalDetails <= 0) {
    die("Impossible de soumettre une NT sans acte.");
}

$stmt = $pdo->prepare("
    UPDATE notes_taxation
    SET statut = 'en_attente_liquidation'
    WHERE id = ?
");
$stmt->execute([$nt['id']]);

header("Location: nt_view.php?numero=" . urlencode($numero_nt));
exit;