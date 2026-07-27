<?php
require_once "../../config/database.php";
require_once "../../config/security.php";

checkAuth();
requireRole(['SUPER_ADMIN','RECOUVREMENT','CHEF_RECOUVREMENT','CAISSIER','APUREUR']);

$numero = $_GET['numero'] ?? null;
if (!$numero) {
    die("Numéro NP / NPF obligatoire.");
}

$stmt = $pdo->prepare("\n    SELECT *\n    FROM notes_perception\n    WHERE numero_np = ?\n    LIMIT 1\n");
$stmt->execute([$numero]);
$np = $stmt->fetch();

if (!$np) {
    die("NP / NPF introuvable.");
}

$referenceType = (($np['type_np'] ?? '') === 'fractionnee') ? 'FRACTION' : 'NP';
$montantDu = (float)($np['montant_initial'] ?? 0);
$montantPaye = (float)($np['montant_paye'] ?? 0);
$solde = (float)($np['solde_restant'] ?? 0);
$statut = ($solde <= 0.01 && ($np['statut'] ?? '') === 'payee') ? 'total' : 'partiel';

$stmt = $pdo->prepare("\n    SELECT id\n    FROM apurements\n    WHERE reference_type = ?\n    AND reference_id = ?\n    LIMIT 1\n");
$stmt->execute([$referenceType, $np['id']]);
$exist = $stmt->fetch();

if ($exist) {
    $stmt = $pdo->prepare("\n        UPDATE apurements\n        SET montant_du = ?, montant_paye = ?, solde_restant = ?, statut = ?, date_apurement = CURDATE(), user_apurement_id = ?\n        WHERE id = ?\n    ");
    $stmt->execute([$montantDu, $montantPaye, $solde, $statut, $_SESSION['user_id'] ?? null, $exist['id']]);
    $apurement_id = (int)$exist['id'];
} else {
    $stmt = $pdo->prepare("\n        INSERT INTO apurements\n        (reference_type, reference_id, montant_du, montant_paye, penalite_validee, solde_restant, statut, date_apurement, user_apurement_id)\n        VALUES (?, ?, ?, ?, 0, ?, ?, CURDATE(), ?)\n    ");
    $stmt->execute([$referenceType, $np['id'], $montantDu, $montantPaye, $solde, $statut, $_SESSION['user_id'] ?? null]);
    $apurement_id = (int)$pdo->lastInsertId();
}

if ($statut === 'total') {
    if ($referenceType === 'FRACTION') {
        header("Location: apurement_list.php?success=1");
        exit;
    }
    header("Location: quittance_generate.php?apurement_id=" . urlencode($apurement_id));
    exit;
}

header("Location: apurement_list.php?success=1");
exit;
?>
