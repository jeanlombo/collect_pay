<?php
require_once "../../config/database.php";
require_once "../../config/security.php";
require_once "../../core/functions.php";



$page_title = "NT à liquider";

$stmt = $pdo->prepare("
    SELECT 
        nt.*,
        c.raison_sociale,
        c.nom,
        c.postnom,
        c.prenom,
        c.nif,
        c.telephone
    FROM notes_taxation nt
    JOIN contribuables c ON nt.contribuable_id = c.id
    WHERE nt.statut = 'en_attente_liquidation'
    ORDER BY nt.created_at DESC
");
$stmt->execute();
$notes = $stmt->fetchAll();

function nomContribuableLiquidation($c) {
    if (!empty($c['raison_sociale'])) {
        return $c['raison_sociale'];
    }

    return trim(($c['nom'] ?? '') . ' ' . ($c['postnom'] ?? '') . ' ' . ($c['prenom'] ?? ''));
}

function formatDateLiquidation($date) {
    if (!$date) {
        return '-';
    }

    return date('d/m/Y H:i:s', strtotime($date));
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($page_title) ?> | cOllect_Pay</title>
<link rel="stylesheet" href="../../assets/css/admin.css">

<style>
.hero-luxoria{
    background:linear-gradient(135deg,#06152b,#0f3460);
    color:white;
    border-radius:24px;
    padding:24px;
    margin-bottom:22px;
}

.hero-luxoria h2{
    margin:0;
    font-weight:900;
}

.hero-luxoria p{
    margin:8px 0 0;
    color:#dbeafe;
}

.btn-liquider{
    display:inline-flex;
    align-items:center;
    gap:10px;
    padding:13px 18px;
    border-radius:15px;
    text-decoration:none;
    font-weight:900;
    color:#fff;
    background:linear-gradient(135deg,#0f766e,#134e4a);
    border:1px solid rgba(255,255,255,.15);
    box-shadow:0 10px 25px rgba(15,118,110,.35);
    transition:.3s;
    white-space:nowrap;
}

.btn-liquider:hover{
    transform:translateY(-3px);
    box-shadow:0 15px 35px rgba(15,118,110,.45);
}

.btn-liquider .icon{
    font-size:18px;
}

.badge-waiting{
    display:inline-block;
    background:#fff7ed;
    color:#9a3412;
    padding:6px 10px;
    border-radius:999px;
    font-weight:900;
    font-size:12px;
}

.amount{
    font-weight:900;
    color:#0f3460;
}
</style>
<link rel="stylesheet" href="../../assets/css/liquidation.css">
</head>

<body class="cp-liquidation-page cp-nt-a-liquider">

<div class="admin-layout">

<?php require_once "../../includes/sidebar.php"; ?>

<main class="main-content">

<?php require_once "../../includes/topbar.php"; ?>

<div class="hero-luxoria cp-liquidation-hero">
    <h2>NT à liquider</h2>
    <p>Liste des Notes de Taxation soumises et prêtes pour liquidation.</p>
</div>

<div class="panel cp-liquidation-panel">
    <div class="cp-section-head"><div><span class="cp-eyebrow">Liquidation</span><h3>Notes de Taxation en attente de liquidation</h3><p>Vérifiez les NT soumises puis générez la Note de Débit correspondante.</p></div></div>
    <div class="cp-table-wrap">

    <table class="table-premium">
        <tr>
            <th>Numéro NT</th>
            <th>Contribuable</th>
            <th>NIF</th>
            <th>Téléphone</th>
            <th>Montant</th>
            <th>Date</th>
            <th>Statut</th>
            <th>Action</th>
        </tr>

        <?php foreach ($notes as $n): ?>
            <tr>
                <td><strong><?= htmlspecialchars($n['numero_nt']) ?></strong></td>

                <td><?= htmlspecialchars(nomContribuableLiquidation($n)) ?></td>

                <td><?= htmlspecialchars($n['nif'] ?? '-') ?></td>

                <td><?= htmlspecialchars($n['telephone'] ?? '-') ?></td>

                <td>
                    <span class="amount">
                        <?= number_format($n['total_estime'] ?? 0, 2, ',', ' ') ?> CDF
                    </span>
                </td>

                <td><?= htmlspecialchars(formatDateLiquidation($n['created_at'] ?? null)) ?></td>

                <td>
                    <span class="badge-waiting">
                        EN ATTENTE
                    </span>
                </td>

                <td>
                    <a href="nd_create.php?numero_nt=<?= urlencode($n['numero_nt']) ?>"
                       class="btn-liquider">
                        <span class="icon">⚖️</span>
                        Liquider et Générer ND
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>

        <?php if (empty($notes)): ?>
            <tr>
                <td colspan="8">Aucune NT en attente de liquidation.</td>
            </tr>
        <?php endif; ?>
    </table>
    </div>
</div>

</main>
</div>

</body>
</html>