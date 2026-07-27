<?php
require_once "../../config/database.php";
require_once "../../config/security.php";



$numero_nt = $_POST['numero_nt'] ?? null;

if (!$numero_nt) {
    die("Numéro NT manquant.");
}

$penalite_assiette = (float)($_POST['penalite_assiette'] ?? 0);
$penalite_recouvrement = (float)($_POST['penalite_recouvrement'] ?? 0);

$stmt = $pdo->prepare("
    SELECT *
    FROM notes_taxation
    WHERE numero_nt = ?
    LIMIT 1
");
$stmt->execute([$numero_nt]);
$nt = $stmt->fetch();

if (!$nt) {
    die("NT introuvable.");
}

if ($nt['statut'] !== 'brouillon') {
    die("Impossible de modifier les pénalités : la NT est déjà soumise.");
}

$stmt = $pdo->prepare("
    UPDATE notes_taxation
    SET
        penalite_assiette = ?,
        penalite_recouvrement = ?
    WHERE id = ?
");
$stmt->execute([
    $penalite_assiette,
    $penalite_recouvrement,
    $nt['id']
]);

header("Location: nt_view.php?numero=" . urlencode($numero_nt));
exit;