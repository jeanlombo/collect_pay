<?php
require_once "../../config/database.php";
require_once "../../config/security.php";
require_once "../../auth/check_auth.php";

checkAuth();
requirePermission('penalites', 'manage');
$page_title = "Avis de fractionnement";

$stmt = $pdo->prepare("
    SELECT 
        np.*,
        nd.numero_nd,
        nt.numero_nt,
        c.raison_sociale,
        c.nom,
        c.postnom,
        c.prenom,
        c.nif,
        av.numero_avis,
        av.id AS avis_id
    FROM notes_perception np
    JOIN notes_debit nd ON np.note_debit_id = nd.id
    JOIN notes_taxation nt ON nd.note_taxation_id = nt.id
    JOIN contribuables c ON nt.contribuable_id = c.id
    LEFT JOIN avis_fractionnement av ON av.note_perception_id = np.id
    WHERE np.type_np = 'globale'
    AND np.statut IN ('non_payee','en_attente','partiellement_payee')
    ORDER BY np.id DESC
");
$stmt->execute();
$notes = $stmt->fetchAll();

function nomContribuableAvisList($c) {
    if (!empty($c['raison_sociale'])) return $c['raison_sociale'];
    return trim(($c['nom'] ?? '') . ' ' . ($c['postnom'] ?? '') . ' ' . ($c['prenom'] ?? ''));
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($page_title) ?> | cOllect_Pay</title>
<link rel="stylesheet" href="/collect_pay/assets/css/admin.css">

<style>
.badge{
    display:inline-block;
    padding:7px 12px;
    border-radius:999px;
    font-weight:900;
}
.badge-blue{background:#dbeafe;color:#1e40af}
.badge-green{background:#dcfce7;color:#166534}
.action-group{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
}
.btn-secondary{
    background:#fbbf24;
    color:#111827;
}
</style>
</head>

<body>
<div class="admin-layout">
<?php require_once "../../includes/sidebar.php"; ?>

<main class="main-content">
<?php require_once "../../includes/topbar.php"; ?>

<div class="panel">
    <h2>Avis de fractionnement</h2>
    <p>Une NP globale ne peut être fractionnée qu'une seule fois. Si l'avis existe déjà, vous pouvez seulement consulter ou générer les NPF liées.</p>

    <table class="table-premium">
        <tr>
            <th>N° NP</th>
            <th>Contribuable</th>
            <th>NIF</th>
            <th>ND</th>
            <th>NT</th>
            <th>Montant</th>
            <th>Solde</th>
            <th>Statut</th>
            <th>Avis</th>
            <th>Action</th>
        </tr>

        <?php foreach ($notes as $n): ?>
            <tr>
                <td><strong><?= htmlspecialchars($n['numero_np']) ?></strong></td>
                <td><?= htmlspecialchars(nomContribuableAvisList($n)) ?></td>
                <td><?= htmlspecialchars($n['nif'] ?? '-') ?></td>
                <td><?= htmlspecialchars($n['numero_nd']) ?></td>
                <td><?= htmlspecialchars($n['numero_nt']) ?></td>
                <td><?= number_format($n['montant_initial'], 2, ',', ' ') ?> CDF</td>
                <td><strong><?= number_format($n['solde_restant'], 2, ',', ' ') ?> CDF</strong></td>
                <td><?= strtoupper(htmlspecialchars($n['statut'])) ?></td>

                <td>
                    <?php if (!empty($n['numero_avis'])): ?>
                        <span class="badge badge-green"><?= htmlspecialchars($n['numero_avis']) ?></span>
                    <?php else: ?>
                        <span class="badge badge-blue">NON FRACTIONNÉE</span>
                    <?php endif; ?>
                </td>

                <td>
                    <div class="action-group">
                        <?php if (!empty($n['numero_avis'])): ?>
                            <a class="btn btn-secondary"
                               href="npf_create.php?numero_avis=<?= urlencode($n['numero_avis']) ?>">
                                Voir / générer les NPF
                            </a>
                        <?php else: ?>
                            <a class="btn"
                               href="avis_fractionnement_create.php?numero_np=<?= urlencode($n['numero_np']) ?>">
                                Générer avis
                            </a>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>

        <?php if (empty($notes)): ?>
            <tr>
                <td colspan="10">
                    Aucune NP globale disponible pour fractionnement.
                </td>
            </tr>
        <?php endif; ?>
    </table>
</div>

</main>
</div>
</body>
</html>