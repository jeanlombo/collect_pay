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

$type = $_GET['type']; // NP ou FRACTION
$id   = $_GET['id'];

if ($type == "NP") {

    $stmt = $pdo->prepare("SELECT * FROM notes_perception WHERE id=?");
    $stmt->execute([$id]);
    $doc = $stmt->fetch();

    $montant_du = $doc['montant_total'];

} elseif ($type == "FRACTION") {

    $stmt = $pdo->prepare("SELECT * FROM notes_perception_fractions WHERE id=?");
    $stmt->execute([$id]);
    $doc = $stmt->fetch();

    $montant_du = $doc['montant_fraction'];
}

if (!$doc) die("Document introuvable");

// Total payé
$stmt = $pdo->prepare("
    SELECT SUM(montant_paye) total
    FROM paiements
    WHERE ".($type=="NP"?"note_perception_id":"fraction_id")."=?
");
$stmt->execute([$id]);
$total_paye = $stmt->fetch()['total'] ?? 0;

// Calcul pénalité si retard
$stmt = $pdo->prepare("
    SELECT SUM(montant_penalite) total
    FROM penalites_historique
    WHERE reference_type=?
    AND reference_id=?
    AND statut='validee'
");
$stmt->execute([$type, $id]);

$penalite_validee = $stmt->fetch()['total'] ?? 0;
$solde = ($montant_du + $penalite_validee) - $total_paye;
// Enregistrer apurement
$stmt = $pdo->prepare("
    INSERT INTO apurements
    (reference_type, reference_id, montant_du, montant_paye,
     penalite, solde_restant, statut, date_apurement, user_apurement_id)
    VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), ?)
");

$stmt->execute([
    $type,
    $id,
    $montant_du,
    $total_paye,
    $penalite,
    $solde,
    $statut,
    $_SESSION['user_id']
]);

// Mise à jour statut
if ($statut == "total") {

    if ($type == "NP") {
        $pdo->prepare("UPDATE notes_perception SET statut='payee' WHERE id=?")
            ->execute([$id]);
    } else {
        $pdo->prepare("UPDATE notes_perception_fractions SET statut='payee' WHERE id=?")
            ->execute([$id]);
    }
}

header("Location: quittance_generate.php?type=$type&id=$id");
exit;
?>