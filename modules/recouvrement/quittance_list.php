<?php
require_once "../../config/database.php";
require_once "../../config/security.php";

checkAuth();

$page_title = "Liste des quittances";

$stmt = $pdo->prepare("
    SELECT 
        q.*,
        ap.reference_type,
        ap.reference_id,
        np.numero_np,
        np.type_np,
        u.nom AS nom_comptable
    FROM quittances q
    LEFT JOIN apurements ap ON q.apurement_id = ap.id
    LEFT JOIN notes_perception np ON ap.reference_id = np.id
    LEFT JOIN users u ON q.user_comptable_id = u.id
    ORDER BY q.date_emission DESC
");
$stmt->execute();
$quittances = $stmt->fetchAll();

function formatDateQuittance($date) {
    if (!$date) return '-';
    return date('d/m/Y H:i:s', strtotime($date));
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
.hero{
    background:linear-gradient(135deg,#06152b,#0f3460);
    color:white;
    padding:24px;
    border-radius:24px;
    margin-bottom:20px;
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
.amount{
    font-weight:900;
    color:#0f3460;
}
.btn-action{
    display:inline-block;
    padding:9px 13px;
    border-radius:12px;
    text-decoration:none;
    font-weight:900;
    background:#0f3460;
    color:white;
}
.btn-pdf{
    background:#fbbf24;
    color:#111827;
}
.action-group{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
}
</style>
<link rel="stylesheet" href="../../assets/css/recouvrement.css">
</head>

<body class="cp-recouvrement-page">
<div class="admin-layout">

<?php require_once "../../includes/sidebar.php"; ?>

<main class="main-content">
<?php require_once "../../includes/topbar.php"; ?>

<div class="hero">
    <h2>Liste des quittances</h2>
    <p>Consultation des quittances informatisées générées après apurement.</p>
</div>

<div class="panel cp-rec-panel">

    <?php if (isset($_GET['success'])): ?>
        <div class="success-box">
            Quittance générée avec succès.
        </div>
    <?php endif; ?>

    <table class="table-premium cp-rec-table">
        <tr>
            <th>Numéro quittance</th>
            <th>NP / NPF</th>
            <th>Type</th>
            <th>Montant acquitté</th>
            <th>Date émission</th>
            <th>Comptable</th>
            <th>Action</th>
        </tr>

        <?php foreach ($quittances as $q): ?>
            <tr>
                <td><strong><?= htmlspecialchars($q['numero_quittance']) ?></strong></td>
                <td><?= htmlspecialchars($q['numero_np'] ?? '-') ?></td>
                <td><?= strtoupper(htmlspecialchars($q['type_np'] ?? $q['reference_type'] ?? '-')) ?></td>
                <td><span class="amount"><?= number_format($q['montant_acquitte'] ?? 0, 2, ',', ' ') ?> CDF</span></td>
                <td><?= htmlspecialchars(formatDateQuittance($q['date_emission'] ?? null)) ?></td>
                <td><?= htmlspecialchars($q['nom_comptable'] ?? '-') ?></td>
                <td>
                    <div class="action-group">
                        <a class="btn-action" href="quittance_view.php?numero=<?= urlencode($q['numero_quittance']) ?>">
                            Voir
                        </a>

                        <a class="btn-action btn-pdf" target="_blank" href="../rapports/quittance_pdf.php?numero=<?= urlencode($q['numero_quittance']) ?>">
                            PDF
                        </a>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>

        <?php if (empty($quittances)): ?>
            <tr>
                <td colspan="7">Aucune quittance générée.</td>
            </tr>
        <?php endif; ?>
    </table>
</div>

</main>
</div>
</body>
</html>