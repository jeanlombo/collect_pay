<?php
require_once "../../config/database.php";
require_once "../../config/security.php";
require_once "../../core/signature_engine.php";

checkAuth();
requirePermission('penalites', 'validate');

requireRole([
    'SUPER_ADMIN',
    'CHEF_RECOUVREMENT'
]);

$page_title = "Validation pénalité";

function penaliteCurrentUserId(PDO $pdo): ?int
{
    $id = (int)($_SESSION['user_id'] ?? 0);
    if ($id > 0) return $id;

    $email = trim((string)($_SESSION['email'] ?? $_SESSION['user_email'] ?? ''));
    if ($email !== '') {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $id = (int)($row['id'] ?? 0);
        if ($id > 0) {
            $_SESSION['user_id'] = $id;
            return $id;
        }
    }
    return null;
}

$penalite_id = $_GET['id'] ?? ($_POST['penalite_id'] ?? null);

if (!$penalite_id) {
    die("ID pénalité obligatoire.");
}

$stmt = $pdo->prepare("
    SELECT *
    FROM penalites_historique
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$penalite_id]);
$penalite = $stmt->fetch();

if (!$penalite) {
    die("Pénalité introuvable.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $justification = trim($_POST['justification'] ?? '');

    if ($justification === '') {
        die("Justification obligatoire.");
    }

    if (($penalite['statut'] ?? '') !== 'proposee') {
        die("Cette pénalité a déjà été traitée.");
    }

    $data = [
        'id' => $penalite['id'],
        'reference_type' => $penalite['reference_type'],
        'reference_id' => $penalite['reference_id'],
        'montant' => $penalite['montant_penalite'],
        'justification' => $justification,
        'date' => date('Y-m-d H:i:s')
    ];

    $hash_document = genererHashDocument($data);
    $signature = hash('sha256', $hash_document . CLE_SIGNATURE_DG);

    $stmt = $pdo->prepare("
        UPDATE penalites_historique
        SET statut = 'validee',
            justification = ?,
            signature_hash = ?,
            user_validation_id = ?,
            date_validation = NOW()
        WHERE id = ?
    ");

    $stmt->execute([
        $justification,
        $signature,
        penaliteCurrentUserId($pdo),
        $penalite_id
    ]);

    header("Location: historique.php?validated=1");
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($page_title) ?> | cOllect_Pay</title>
<link rel="stylesheet" href="../../assets/css/admin.css">

<style>
.hero{
    background:linear-gradient(135deg,#06152b,#0f3460);
    color:white;
    padding:24px;
    border-radius:24px;
    margin-bottom:22px;
}
.hero h2{margin:0;font-weight:900}
.hero p{margin:8px 0 0;color:#dbeafe}
.amount{
    font-size:24px;
    font-weight:900;
    color:#0f3460;
}
.warning-box{
    background:#fff7ed;
    color:#9a3412;
    padding:14px;
    border-radius:14px;
    font-weight:800;
    margin-bottom:18px;
}
</style>
<link rel="stylesheet" href="../../assets/css/penalites.css">
</head>

<body class="cp-penalites-page">
<div class="admin-layout">

<?php require_once "../../includes/sidebar.php"; ?>

<main class="main-content">
<?php require_once "../../includes/topbar.php"; ?>

<div class="hero">
    <h2>Validation de pénalité</h2>
    <p>Validation numérique par le Chef de Recouvrement.</p>
</div>

<div class="panel cp-penalites-panel">
    <div class="warning-box">
        Cette action signera numériquement la pénalité et la rendra officielle dans le système.
    </div>

    <table class="table-premium cp-penalites-table">
        <tr>
            <th>Référence</th>
            <td><?= htmlspecialchars($penalite['reference_type']) ?> #<?= htmlspecialchars($penalite['reference_id']) ?></td>
        </tr>
        <tr>
            <th>Type pénalité</th>
            <td><?= strtoupper(htmlspecialchars($penalite['type_penalite'] ?? '-')) ?></td>
        </tr>
        <tr>
            <th>Montant base</th>
            <td><?= number_format($penalite['montant_base'] ?? 0, 2, ',', ' ') ?> CDF</td>
        </tr>
        <tr>
            <th>Montant pénalité</th>
            <td><span class="amount"><?= number_format($penalite['montant_penalite'] ?? 0, 2, ',', ' ') ?> CDF</span></td>
        </tr>
        <tr>
            <th>Jours retard</th>
            <td><?= htmlspecialchars($penalite['jours_retard'] ?? 0) ?> jour(s)</td>
        </tr>
        <tr>
            <th>Statut actuel</th>
            <td><?= strtoupper(htmlspecialchars($penalite['statut'] ?? '-')) ?></td>
        </tr>
    </table>

    <form method="POST">
        <input type="hidden" name="penalite_id" value="<?= (int)$penalite['id'] ?>">

        <label>Justification de validation</label>
        <textarea name="justification" required placeholder="Ex : pénalité conforme au barème de recouvrement applicable..."></textarea>

        <button type="submit">
            Valider et signer numériquement
        </button>
    </form>
</div>

</main>
</div>
</body>
</html>