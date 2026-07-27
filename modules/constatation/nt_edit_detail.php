<?php
require_once "../../config/database.php";
require_once "../../config/security.php";



$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$numero = $_GET['numero'] ?? '';

if ($action !== 'delete' || $id <= 0 || !$numero) {
    die("Action invalide.");
}

function cpColumnsEditNT(PDO $pdo, string $table): array
{
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `$table`");
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
    } catch (Exception $e) {
        return [];
    }
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT * FROM notes_taxation WHERE numero_nt = ? LIMIT 1");
    $stmt->execute([$numero]);
    $nt = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$nt) {
        throw new Exception("NT introuvable.");
    }

    $stmt = $pdo->prepare("DELETE FROM notes_taxation_details WHERE id = ? AND note_taxation_id = ?");
    $stmt->execute([$id, (int)$nt['id']]);

    $stmt = $pdo->prepare("SELECT IFNULL(SUM(total_ligne_cdf),0) AS total_cdf FROM notes_taxation_details WHERE note_taxation_id = ?");
    $stmt->execute([(int)$nt['id']]);
    $total = (float)($stmt->fetch()['total_cdf'] ?? 0);

    $cols = cpColumnsEditNT($pdo, 'notes_taxation');
    $sets = [];
    $values = [];

    if (in_array('montant_total', $cols, true)) {
        $sets[] = "montant_total = ?";
        $values[] = $total;
    }

    if (in_array('total_cdf', $cols, true)) {
        $sets[] = "total_cdf = ?";
        $values[] = $total;
    }

    if (in_array('updated_at', $cols, true)) {
        $sets[] = "updated_at = ?";
        $values[] = date('Y-m-d H:i:s');
    }

    if ($sets) {
        $values[] = (int)$nt['id'];
        $sql = "UPDATE notes_taxation SET " . implode(",", $sets) . " WHERE id = ?";
        $pdo->prepare($sql)->execute($values);
    }

    $pdo->commit();

    header("Location: nt_view.php?numero=" . urlencode($numero));
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    die("Erreur suppression détail : " . $e->getMessage());
}
