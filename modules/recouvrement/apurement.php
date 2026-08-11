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

if (!function_exists('cpRecouvrementCurrentUserId')) {
    function cpRecouvrementCurrentUserId(PDO $pdo): int
    {
        $id = (int)($_SESSION['user_id'] ?? 0);
        if ($id > 0) return $id;

        $email = trim((string)($_SESSION['email'] ?? $_SESSION['user_email'] ?? ''));
        if ($email !== '') {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $id = (int)($row['id'] ?? 0);
            if ($id > 0) {
                $_SESSION['user_id'] = $id;
                return $id;
            }
        }
        return 0;
    }
}

$type = strtoupper(trim((string)($_GET['type'] ?? '')));
$id   = (int)($_GET['id'] ?? 0);

if (!in_array($type, ['NP', 'FRACTION'], true) || $id <= 0) {
    die("Référence d'apurement invalide.");
}

if ($type === 'NP') {
    $stmt = $pdo->prepare("SELECT * FROM notes_perception WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);
    $montant_du = (float)($doc['montant_initial'] ?? $doc['montant_total'] ?? 0);
    $paymentColumn = 'note_perception_id';
} else {
    $stmt = $pdo->prepare("SELECT * FROM notes_perception_fractions WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);
    $montant_du = (float)($doc['montant_fraction'] ?? 0);
    $paymentColumn = 'fraction_id';
}

if (!$doc) {
    die("Document introuvable.");
}

$stmt = $pdo->prepare("SELECT IFNULL(SUM(montant_converti_cdf), 0) total FROM paiements WHERE {$paymentColumn} = ? AND statut <> 'annule'");
$stmt->execute([$id]);
$total_paye = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

$stmt = $pdo->prepare("
    SELECT IFNULL(SUM(montant_penalite), 0) total
    FROM penalites_historique
    WHERE reference_type = ?
      AND reference_id = ?
      AND statut = 'validee'
");
$stmt->execute([$type, $id]);
$penalite_validee = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

$solde = max(0, ($montant_du + $penalite_validee) - $total_paye);
$statut = ($solde <= 0.01) ? 'total' : 'partiel';
$user_id = cpRecouvrementCurrentUserId($pdo);

$stmt = $pdo->prepare("
    SELECT id
    FROM apurements
    WHERE reference_type = ?
      AND reference_id = ?
    LIMIT 1
");
$stmt->execute([$type, $id]);
$exist = $stmt->fetch(PDO::FETCH_ASSOC);

if ($exist) {
    $stmt = $pdo->prepare("
        UPDATE apurements
        SET montant_du = ?,
            montant_paye = ?,
            penalite_validee = ?,
            solde_restant = ?,
            statut = ?,
            date_apurement = CURDATE(),
            user_apurement_id = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $montant_du, $total_paye, $penalite_validee, $solde,
        $statut, $user_id ?: null, (int)$exist['id']
    ]);
    $apurement_id = (int)$exist['id'];
} else {
    $stmt = $pdo->prepare("
        INSERT INTO apurements
        (
            reference_type, reference_id, montant_du, montant_paye,
            penalite_validee, solde_restant, statut,
            date_apurement, user_apurement_id
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), ?)
    ");
    $stmt->execute([
        $type, $id, $montant_du, $total_paye,
        $penalite_validee, $solde, $statut, $user_id ?: null
    ]);
    $apurement_id = (int)$pdo->lastInsertId();
}

if ($statut === 'total') {
    if ($type === 'NP') {
        $pdo->prepare("UPDATE notes_perception SET statut = 'payee', solde_restant = 0 WHERE id = ?")
            ->execute([$id]);
    } else {
        $pdo->prepare("UPDATE notes_perception_fractions SET statut = 'payee' WHERE id = ?")
            ->execute([$id]);
    }

    header("Location: quittance_generate.php?apurement_id=" . urlencode((string)$apurement_id));
    exit;
}

header("Location: apurement_list.php?success=1");
exit;
