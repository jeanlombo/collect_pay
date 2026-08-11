<?php
require_once "../../config/database.php";
require_once "../../config/security.php";

checkAuth();

if (!function_exists('cpRecouvrementCurrentUserId')) {
    function cpRecouvrementCurrentUserId(PDO $pdo): int
    {
        $id = (int)($_SESSION['user_id'] ?? 0);

        if ($id > 0) {
            return $id;
        }

        $email = trim((string)($_SESSION['email'] ?? $_SESSION['user_email'] ?? ''));

        if ($email !== '') {
            $stmtUser = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmtUser->execute([$email]);
            $rowUser = $stmtUser->fetch(PDO::FETCH_ASSOC);
            $id = (int)($rowUser['id'] ?? 0);

            if ($id > 0) {
                $_SESSION['user_id'] = $id;
                return $id;
            }
        }

        return 0;
    }
}


requireRole([
    'SUPER_ADMIN',
    'CHEF_RECOUVREMENT',
    'DIRECTION_FINANCIERE'
]);

$page_title = "Validation AMR";

$id = $_GET['id'] ?? ($_POST['id'] ?? null);

if (!$id) {
    die("ID AMR obligatoire.");
}

$stmt = $pdo->prepare("
    SELECT *
    FROM amr
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$amr = $stmt->fetch();

if (!$amr) {
    die("AMR introuvable.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $decision = $_POST['decision'] ?? '';
    $motif = trim($_POST['motif'] ?? '');

    if (!in_array($decision, ['valide', 'rejete'])) {
        die("Décision invalide.");
    }

    if ($motif === '') {
        die("Motif obligatoire.");
    }

    $stmt = $pdo->prepare("
        UPDATE amr
        SET statut = ?,
            motif = ?,
            user_validation_id = ?,
            date_validation = NOW()
        WHERE id = ?
    ");

    $stmt->execute([
        $decision,
        $motif,
        cpRecouvrementCurrentUserId($pdo),
        $id
    ]);

    header("Location: amr_list.php?validated=1");
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($page_title) ?> | cOllect_Pay</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../../assets/css/admin.css">

<style>
.hero-amr{
    background:linear-gradient(135deg,#06152b,#0f3460);
    color:white;
    padding:24px;
    border-radius:24px;
    margin-bottom:22px;
}
.amount{
    font-size:24px;
    font-weight:900;
    color:#991b1b;
}
.warning-box{
    background:#fff7ed;
    color:#9a3412;
    padding:14px;
    border-radius:14px;
    font-weight:800;
    margin-bottom:18px;
}
.actions{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
}
.btn-danger{
    background:#991b1b;
}
.btn-green{
    background:#16a34a;
}
</style>
<link rel="stylesheet" href="../../assets/css/recouvrement.css">
</head>

<body class="cp-recouvrement-page">
<div class="admin-layout">

<?php require_once "../../includes/sidebar.php"; ?>

<main class="main-content">
<?php require_once "../../includes/topbar.php"; ?>

<div class="hero-amr">
    <h2>Validation AMR</h2>
    <p>Validation ou rejet de l’Avis de Mise en Recouvrement.</p>
</div>

<div class="panel cp-rec-panel">
    <div class="warning-box">
        La validation de cet AMR autorisera la reprise du paiement de la NP / NPF échue.
    </div>

    <table class="table-premium cp-rec-table">
        <tr>
            <th>Numéro AMR</th>
            <td><strong><?= htmlspecialchars($amr['numero_amr']) ?></strong></td>
        </tr>
        <tr>
            <th>Référence</th>
            <td><?= htmlspecialchars($amr['reference_type']) ?> — <?= htmlspecialchars($amr['reference_numero']) ?></td>
        </tr>
        <tr>
            <th>Montant principal</th>
            <td><?= number_format($amr['montant_principal'], 2, ',', ' ') ?> CDF</td>
        </tr>
        <tr>
            <th>Pénalité</th>
            <td><span class="amount"><?= number_format($amr['montant_penalite'], 2, ',', ' ') ?> CDF</span></td>
        </tr>
        <tr>
            <th>Total à recouvrer</th>
            <td><strong><?= number_format($amr['montant_total'], 2, ',', ' ') ?> CDF</strong></td>
        </tr>
        <tr>
            <th>Jours de retard</th>
            <td><?= (int)$amr['jours_retard'] ?> jour(s)</td>
        </tr>
        <tr>
            <th>Statut actuel</th>
            <td><?= strtoupper(htmlspecialchars($amr['statut'])) ?></td>
        </tr>
    </table>

    <form method="POST">
        <input type="hidden" name="id" value="<?= (int)$amr['id'] ?>">

        <label>Motif / Observation de validation</label>
        <textarea name="motif" required><?= htmlspecialchars($amr['motif'] ?? '') ?></textarea>

        <div class="actions">
            <button type="submit" name="decision" value="valide" class="btn-green">
                Valider AMR
            </button>

            <button type="submit" name="decision" value="rejete" class="btn-danger">
                Rejeter AMR
            </button>

            <a href="amr_list.php" class="btn">
                Retour
            </a>
        </div>
    </form>
</div>

</main>
</div>
</body>
</html>