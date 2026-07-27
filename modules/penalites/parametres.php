<?php
require_once "../../config/database.php";
require_once "../../config/security.php";
require_once "../../auth/check_auth.php";

checkAuth();
requirePermission('penalites', 'history');

$page_title = "Paramètres pénalités";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $type  = $_POST['type'] ?? '';
    $debut = (int)($_POST['tranche_debut'] ?? 0);
    $fin   = (int)($_POST['tranche_fin'] ?? 0);
    $taux  = (float)($_POST['taux'] ?? 0);

    if (!in_array($type, ['recouvrement', 'assiette'])) {
        die("Type invalide.");
    }

    if ($fin < $debut) {
        die("La tranche de fin doit être supérieure ou égale au début.");
    }

    $stmt = $pdo->prepare("
        INSERT INTO parametres_penalites_progressives
        (type, tranche_debut, tranche_fin, taux_pourcentage)
        VALUES (?, ?, ?, ?)
    ");

    $stmt->execute([$type, $debut, $fin, $taux]);

    header("Location: parametres.php?success=1");
    exit;
}

$params = $pdo->query("
    SELECT *
    FROM parametres_penalites_progressives
    ORDER BY type, tranche_debut
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($page_title) ?> | cOllect_Pay</title>
<link rel="stylesheet" href="/collect_pay/assets/css/admin.css">

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
.success-box{
    background:#dcfce7;
    color:#166534;
    padding:14px;
    border-radius:14px;
    font-weight:900;
    margin-bottom:18px;
}
.form-grid{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:14px;
}
.badge{
    display:inline-block;
    padding:7px 12px;
    border-radius:999px;
    font-weight:900;
}
.badge-blue{background:#dbeafe;color:#1e40af}
.badge-orange{background:#ffedd5;color:#9a3412}
@media(max-width:900px){
    .form-grid{grid-template-columns:1fr}
}
</style>
</head>

<body>
<div class="admin-layout">

<?php require_once "../../includes/sidebar.php"; ?>

<main class="main-content">
<?php require_once "../../includes/topbar.php"; ?>

<div class="hero">
    <h2>Paramètres des pénalités</h2>
    <p>Configuration des barèmes progressifs pour les pénalités d’assiette et de recouvrement.</p>
</div>

<div class="panel">
    <h3>Ajouter une tranche</h3>

    <?php if (isset($_GET['success'])): ?>
        <div class="success-box">Paramètre ajouté avec succès.</div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-grid">
            <div>
                <label>Type</label>
                <select name="type" required>
                    <option value="recouvrement">Recouvrement</option>
                    <option value="assiette">Assiette</option>
                </select>
            </div>

            <div>
                <label>Début jours</label>
                <input type="number" name="tranche_debut" min="0" required>
            </div>

            <div>
                <label>Fin jours</label>
                <input type="number" name="tranche_fin" min="0" required>
            </div>

            <div>
                <label>Taux (%)</label>
                <input type="number" step="0.01" name="taux" min="0" required>
            </div>
        </div>

        <button type="submit">Ajouter la tranche</button>
    </form>
</div>

<div class="panel">
    <h3>Barème enregistré</h3>

    <table class="table-premium">
        <tr>
            <th>Type</th>
            <th>De</th>
            <th>À</th>
            <th>Taux</th>
        </tr>

        <?php foreach ($params as $p): ?>
            <tr>
                <td>
                    <?php if ($p['type'] === 'recouvrement'): ?>
                        <span class="badge badge-blue">RECOUVREMENT</span>
                    <?php else: ?>
                        <span class="badge badge-orange">ASSIETTE</span>
                    <?php endif; ?>
                </td>
                <td><?= (int)$p['tranche_debut'] ?> jours</td>
                <td><?= (int)$p['tranche_fin'] ?> jours</td>
                <td><strong><?= number_format($p['taux_pourcentage'], 2, ',', ' ') ?>%</strong></td>
            </tr>
        <?php endforeach; ?>

        <?php if (empty($params)): ?>
            <tr>
                <td colspan="4">Aucun paramètre enregistré.</td>
            </tr>
        <?php endif; ?>
    </table>
</div>

</main>
</div>
</body>
</html>