<?php
require_once "../../config/database.php";
require_once "../../config/security.php";

checkAuth();
requireRole([
    'SUPER_ADMIN',
    'RECOUVREMENT',
    'CHEF_RECOUVREMENT',
    'CAISSIER'
]);

$numero_fraction = trim((string)($_GET['numero_fraction'] ?? ''));

if ($numero_fraction === '') {
    die("Fraction manquante.");
}

/*
|--------------------------------------------------------------------------
| Compatibilité avec l'ancien système de fractions
|--------------------------------------------------------------------------
| L'ancien fichier insérait des colonnes qui n'existent plus dans la table
| paiements actuelle (penalite_appliquee, user_encaisseur_id) et enregistrait
| un paiement sans mode de paiement.
|
| On ne crée donc plus de paiement automatique incomplet.
| Si la fraction existe aussi comme NPF moderne dans notes_perception,
| elle est envoyée vers le formulaire de paiement complet.
*/
$stmt = $pdo->prepare("
    SELECT numero_np
    FROM notes_perception
    WHERE numero_np = ?
    LIMIT 1
");
$stmt->execute([$numero_fraction]);
$npf = $stmt->fetch(PDO::FETCH_ASSOC);

if ($npf) {
    header("Location: paiement_create.php?numero=" . urlencode($npf['numero_np']));
    exit;
}

die(
    "Cette fraction appartient à l'ancien mécanisme de fractionnement. " .
    "Utilisez la NPF correspondante depuis Ordonnancement afin d'enregistrer " .
    "un paiement complet avec mode de paiement et référence transactionnelle."
);
