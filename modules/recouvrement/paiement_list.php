<?php
require_once "../../config/database.php";
require_once "../../config/security.php";

checkAuth();
requireRole([
    'SUPER_ADMIN',
    'RECOUVREMENT',
    'CHEF_RECOUVREMENT',
    'CAISSIER',
    'COMPTABLE',
    'PAIEMENT'
]);

$page_title = "Liste des paiements";

$stmt = $pdo->prepare("
    SELECT 
        p.*,
        np.numero_np,
        np.type_np,
        np.statut AS statut_np,
        u.nom AS nom_comptable
    FROM paiements p
    LEFT JOIN notes_perception np ON p.note_perception_id = np.id
    LEFT JOIN users u ON p.user_comptable_id = u.id
    ORDER BY p.created_at DESC
");
$stmt->execute();
$paiements = $stmt->fetchAll();

function formatDatePaiement($date) {
    if (!$date) return '-';
    return date('d/m/Y H:i:s', strtotime($date));
}

function badgePaiement($statut) {
    $statut = strtolower((string)$statut);
    if (in_array($statut, ['valide', 'validé', 'payee', 'payé'])) {
        return '<span class="badge green">VALIDÉ</span>';
    }
    if (in_array($statut, ['annule', 'annulé', 'rejete', 'rejeté'])) {
        return '<span class="badge orange">ANNULÉ</span>';
    }
    return '<span class="badge blue">' . strtoupper(htmlspecialchars($statut ?: '-')) . '</span>';
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
    padding:7px 12px;
    border-radius:999px;
    font-weight:900;
    display:inline-block;
}
.green{background:#dcfce7;color:#166534}
.orange{background:#ffedd5;color:#9a3412}
.blue{background:#dbeafe;color:#1e40af}
.amount{font-weight:900;color:#0f3460}
.action-buttons{display:flex;gap:8px;flex-wrap:wrap}
.btn-small{
    display:inline-block;
    padding:8px 12px;
    border-radius:10px;
    text-decoration:none;
    font-weight:800;
    background:#0f3460;
    color:white;
}
.btn-gold{
    display:inline-block;
    padding:10px 14px;
    border-radius:12px;
    text-decoration:none;
    font-weight:900;
    background:#d4af37;
    color:#111827;
}
.top-actions{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
</style>
</head>

<body>
<div class="admin-layout">

<?php require_once "../../includes/sidebar.php"; ?>

<main class="main-content">
<?php require_once "../../includes/topbar.php"; ?>

<div class="panel">
    <div class="top-actions">
        <h2>Liste des paiements</h2>

        <!-- Le paiement doit partir d'une NP/NPF pour éviter l'erreur Numéro NP obligatoire -->
        <a href="/collect_pay/modules/ordonnancement/np_list.php" class="btn-gold">
            + Nouveau paiement depuis une NP / NPF
        </a>
    </div>

    <p style="margin-top:10px;color:#64748b;font-weight:700;">
        Pour enregistrer un paiement, choisissez d'abord la NP/NPF concernée puis cliquez sur <strong>Payer</strong>.
    </p>

    <table class="table-premium">
        <tr>
            <th>Date</th>
            <th>NP / NPF</th>
            <th>Type</th>
            <th>Montant payé</th>
            <th>Devise</th>
            <th>Montant CDF</th>
            <th>Mode</th>
            <th>Référence</th>
            <th>Compte crédité</th>
            <th>Statut</th>
            <th>Agent</th>
            <th>Actions</th>
        </tr>

        <?php foreach ($paiements as $p): ?>
            <tr>
                <td><?= htmlspecialchars(formatDatePaiement($p['created_at'] ?? $p['date_paiement'] ?? null)) ?></td>
                <td><strong><?= htmlspecialchars($p['numero_np'] ?? '-') ?></strong></td>
                <td><?= strtoupper(htmlspecialchars($p['type_np'] ?? '-')) ?></td>
                <td><span class="amount"><?= number_format((float)($p['montant_paye'] ?? 0), 2, ',', ' ') ?></span></td>
                <td><?= htmlspecialchars($p['devise'] ?? 'CDF') ?></td>
                <td><span class="amount"><?= number_format((float)($p['montant_converti_cdf'] ?? 0), 2, ',', ' ') ?> CDF</span></td>
                <td><?= htmlspecialchars($p['mode_paiement_id'] ?? '-') ?></td>
                <td><?= htmlspecialchars($p['reference_transaction'] ?? '-') ?></td>
                <td><?= htmlspecialchars($p['compte_credite'] ?? '-') ?></td>
                <td><?= badgePaiement($p['statut'] ?? '-') ?></td>
                <td><?= htmlspecialchars($p['nom_comptable'] ?? '-') ?></td>
                <td>
                    <div class="action-buttons">
                        <a href="/collect_pay/modules/recouvrement/paiement_view.php?id=<?= (int)$p['id'] ?>"
                           class="btn-small">
                            Voir
                        </a>

                        <?php if (!empty($p['reference_transaction'])): ?>
                            <a href="/collect_pay/modules/recouvrement/paiement_view.php?reference=<?= urlencode($p['reference_transaction']) ?>"
                               class="btn-small">
                                Réf.
                            </a>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>

        <?php if (empty($paiements)): ?>
            <tr>
                <td colspan="12">Aucun paiement enregistré.</td>
            </tr>
        <?php endif; ?>
    </table>
</div>

</main>
</div>
</body>
</html>