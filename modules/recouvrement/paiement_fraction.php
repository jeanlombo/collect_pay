<?php
require_once("../../config/database.php");
require_once("../../config/security.php");
require_once("../../core/penalite_engine.php");

checkAuth();
requireRole([
    'SUPER_ADMIN',
    'RECOUVREMENT',
    'CHEF_RECOUVREMENT',
    'CAISSIER'
]);

$numero_fraction = $_GET['numero_fraction'] ?? null;
if (!$numero_fraction) die("Fraction manquante");

$stmt = $pdo->prepare("SELECT * FROM notes_perception_fractions WHERE numero_fraction=?");
$stmt->execute([$numero_fraction]);
$fraction = $stmt->fetch();

if (!$fraction) die("Fraction introuvable");

$jours_retard = floor((strtotime(date('Y-m-d')) - strtotime($fraction['date_echeance'])) / 86400);

$penalite = calculerPenaliteProgressive(
    $fraction['montant_fraction'],
    $jours_retard,
    'recouvrement',
    $pdo
);

$stmt = $pdo->prepare("
    INSERT INTO paiements
    (fraction_id, montant_paye, penalite_appliquee, date_paiement, user_encaisseur_id)
    VALUES (?, ?, ?, CURDATE(), ?)
");

$stmt->execute([
    $fraction['id'],
    $fraction['montant_fraction'],
    $penalite,
    $_SESSION['user_id']
]);

$pdo->prepare("
    UPDATE notes_perception_fractions
    SET statut='payee'
    WHERE id=?
")->execute([$fraction['id']]);

header("Location: apurement.php?type=FRACTION&id=".$fraction['id']);
exit;
?>