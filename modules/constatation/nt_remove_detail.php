<?php
require_once "../../config/database.php";
require_once "../../config/security.php";


$numero_nt = $_GET['numero'] ?? null;
$detail_id = (int)($_GET['detail_id'] ?? 0);

if (!$numero_nt || $detail_id <= 0) {
    die("Paramètres invalides.");
}

function tableColumnsNTRemove($pdo, $table)
{
    $cols = [];
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM $table");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $cols[] = $c['Field'];
        }
    } catch (Exception $e) {}
    return $cols;
}

function updateTotalNTRemove($pdo, $noteTaxationId)
{
    $stmt = $pdo->prepare("
        SELECT IFNULL(SUM(total_ligne_cdf),0) AS total_cdf
        FROM notes_taxation_details
        WHERE note_taxation_id = ?
    ");
    $stmt->execute([$noteTaxationId]);
    $totalActes = (float)$stmt->fetch()['total_cdf'];

    $stmt = $pdo->prepare("
        SELECT 
            IFNULL(penalite_assiette,0) AS penalite_assiette,
            IFNULL(penalite_recouvrement,0) AS penalite_recouvrement
        FROM notes_taxation
        WHERE id = ?
    ");
    $stmt->execute([$noteTaxationId]);
    $nt = $stmt->fetch();

    $totalGeneral =
        $totalActes
        + (float)($nt['penalite_assiette'] ?? 0)
        + (float)($nt['penalite_recouvrement'] ?? 0);

    $cols = tableColumnsNTRemove($pdo, "notes_taxation");

    $sets = [];
    $values = [];

    foreach ([
        'total_estime' => $totalGeneral,
        'montant_total' => $totalGeneral,
        'montant' => $totalGeneral
    ] as $col => $val) {
        if (in_array($col, $cols)) {
            $sets[] = "$col = ?";
            $values[] = $val;
        }
    }

    if (!empty($sets)) {
        $values[] = $noteTaxationId;
        $stmt = $pdo->prepare("
            UPDATE notes_taxation
            SET " . implode(',', $sets) . "
            WHERE id = ?
        ");
        $stmt->execute($values);
    }
}

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

if (($nt['statut'] ?? 'brouillon') !== 'brouillon') {
    die("Impossible de retirer un acte : cette NT n'est plus en brouillon.");
}

$stmt = $pdo->prepare("
    SELECT *
    FROM notes_taxation_details
    WHERE id = ?
    AND note_taxation_id = ?
    LIMIT 1
");
$stmt->execute([$detail_id, $nt['id']]);
$detail = $stmt->fetch();

if (!$detail) {
    die("Détail introuvable.");
}

$pdo->beginTransaction();

try {
    $stmt = $pdo->prepare("
        DELETE FROM notes_taxation_details
        WHERE id = ?
        AND note_taxation_id = ?
    ");
    $stmt->execute([$detail_id, $nt['id']]);

    updateTotalNTRemove($pdo, $nt['id']);

    $pdo->commit();

    header("Location: nt_view.php?numero=" . urlencode($numero_nt) . "&detail_removed=1");
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    die("Erreur suppression acte : " . $e->getMessage());
}
?>