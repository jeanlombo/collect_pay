<?php
require_once "../../config/database.php";
require_once "../../config/security.php";

checkAuth();
requirePermission('penalites', 'history');


$page_title = "Historique des pénalités";

$stmt = $pdo->query("
    SELECT *
    FROM penalites_historique
    ORDER BY created_at DESC
");

$penalites = $stmt->fetchAll();

$role = strtoupper(trim((string)($_SESSION['role'] ?? $_SESSION['nom_role'] ?? '')));
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
    color:#fff;
    padding:24px;
    border-radius:24px;
    margin-bottom:22px;
}

.hero h2{
    margin:0;
    font-weight:900;
}

.hero p{
    margin-top:8px;
    color:#dbeafe;
}

.badge{
    display:inline-block;
    padding:7px 12px;
    border-radius:999px;
    font-weight:900;
}

.badge-orange{
    background:#ffedd5;
    color:#9a3412;
}

.badge-green{
    background:#dcfce7;
    color:#166534;
}

.badge-red{
    background:#fee2e2;
    color:#991b1b;
}

.success-box{
    background:#dcfce7;
    color:#166534;
    padding:14px;
    border-radius:14px;
    margin-bottom:20px;
    font-weight:900;
}

.small{
    font-size:12px;
    color:#6b7280;
}

.btn-validate{
    background:#16a34a;
    color:white;
    text-decoration:none;
    padding:8px 14px;
    border-radius:12px;
    font-weight:800;
}

.signature{
    font-size:11px;
    color:#6b7280;
    word-break:break-all;
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
    <h2>Historique des pénalités</h2>
    <p>Suivi des pénalités d'assiette et de recouvrement appliquées dans le système.</p>
</div>

<?php if (isset($_GET['validated'])): ?>
    <div class="success-box">
        Pénalité validée et signée numériquement avec succès.
    </div>
<?php endif; ?>

<div class="panel cp-penalites-panel">

<table class="table-premium cp-penalites-table">

<tr>
    <th>Type</th>
    <th>Référence</th>
    <th>Montant Base</th>
    <th>Taux</th>
    <th>Pénalité</th>
    <th>Jours</th>
    <th>Date</th>
    <th>Statut</th>
    <th>Action</th>
</tr>

<?php foreach ($penalites as $p): ?>

<tr>

    <td>
        <?= strtoupper(htmlspecialchars($p['type'])) ?>
    </td>

    <td>
        <?= htmlspecialchars($p['reference_type']) ?>
        #
        <?= (int)$p['reference_id'] ?>
    </td>

    <td>
        <?= number_format($p['montant_base'],2,',',' ') ?>
        CDF
    </td>

    <td>
        <?= number_format($p['taux_applique'],2,',',' ') ?>%
    </td>

    <td>
        <strong>
            <?= number_format($p['montant_penalite'],2,',',' ') ?>
            CDF
        </strong>
    </td>

    <td>
        <?= (int)$p['jours_retard'] ?>
        jour(s)
    </td>

    <td>
        <?= htmlspecialchars($p['date_application']) ?>
    </td>

    <td>

        <?php if (($p['statut'] ?? '') === 'proposee'): ?>

            <span class="badge badge-orange">
                PROPOSÉE
            </span>

        <?php elseif (($p['statut'] ?? '') === 'validee'): ?>

            <span class="badge badge-green">
                VALIDÉE
            </span>

        <?php else: ?>

            <span class="badge badge-red">
                <?= strtoupper(htmlspecialchars($p['statut'])) ?>
            </span>

        <?php endif; ?>

        <?php if (!empty($p['justification'])): ?>

            <div class="small">
                <?= nl2br(htmlspecialchars($p['justification'])) ?>
            </div>

        <?php endif; ?>

        <?php if (!empty($p['signature_hash'])): ?>

            <div class="signature">
                Signature :
                <?= htmlspecialchars(substr($p['signature_hash'],0,40)) ?>...
            </div>

        <?php endif; ?>

    </td>

    <td>

        <?php
        if (
            ($p['statut'] ?? '') === 'proposee'
            &&
            in_array(
                $role,
                [
                    'SUPER_ADMIN',
                    'CHEF_RECOUVREMENT'
                ]
            )
        ):
        ?>

            <a
                class="btn-validate"
                href="valider.php?id=<?= (int)$p['id'] ?>">
                Valider et signer
            </a>

        <?php else: ?>

            —

        <?php endif; ?>

    </td>

</tr>

<?php endforeach; ?>

<?php if (empty($penalites)): ?>

<tr>
    <td colspan="9">
        Aucune pénalité enregistrée.
    </td>
</tr>

<?php endif; ?>

</table>

</div>

</main>
</div>

</body>
</html>