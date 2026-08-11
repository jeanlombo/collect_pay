<?php
require_once "../../config/database.php";
require_once "../../config/security.php";

checkAuth();
requireRole([
    'SUPER_ADMIN',
    'ORDONNATEUR'
]);

$page_title = "NP Fractionnées / Fractionnements";

$stmt = $pdo->prepare("
    SELECT
        np.*,
        mere.numero_np AS numero_np_mere,
        av.numero_avis,
        av.autorite_type,
        av.autorite_nom,
        nd.numero_nd,
        nt.numero_nt,
        c.raison_sociale,
        c.nom,
        c.postnom,
        c.prenom,
        c.nif
    FROM notes_perception np
    LEFT JOIN notes_perception mere ON np.np_mere_id = mere.id
    LEFT JOIN avis_fractionnement av ON np.avis_fractionnement_id = av.id
    JOIN notes_debit nd ON np.note_debit_id = nd.id
    JOIN notes_taxation nt ON nd.note_taxation_id = nt.id
    JOIN contribuables c ON nt.contribuable_id = c.id
    WHERE np.type_np = 'fractionnee'
    ORDER BY np.date_emission DESC, np.id DESC
");
$stmt->execute();
$fractions = $stmt->fetchAll();

function nomContribuableFraction($c)
{
    if (!empty($c['raison_sociale'])) {
        return $c['raison_sociale'];
    }

    return trim(($c['nom'] ?? '') . ' ' . ($c['postnom'] ?? '') . ' ' . ($c['prenom'] ?? ''));
}

function formatDateFraction($date)
{
    if (!$date) return '-';
    return date('d/m/Y H:i:s', strtotime($date));
}

function badgeFraction($statut)
{
    $label = strtoupper(str_replace('_', ' ', $statut));

    if ($statut === 'payee') {
        return "<span class='badge green'>$label</span>";
    }

    if ($statut === 'defaillante') {
        return "<span class='badge red'>$label</span>";
    }

    if ($statut === 'partiellement_payee') {
        return "<span class='badge orange'>$label</span>";
    }

    return "<span class='badge blue'>$label</span>";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($page_title) ?> | cOllect_Pay</title>
<link rel="stylesheet" href="/collect_pay/assets/css/admin.css">

<style>
.hero-luxoria {
    background: linear-gradient(135deg, #06152b, #0f3460);
    color: white;
    border-radius: 24px;
    padding: 24px;
    margin-bottom: 22px;
    box-shadow: 0 18px 40px rgba(15,52,96,.25);
}

.hero-luxoria h2 {
    margin: 0 0 8px 0;
    font-weight: 900;
}

.hero-luxoria p {
    margin: 0;
    opacity: .9;
}

.filters-luxoria {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 18px;
}

.btn-luxoria {
    display: inline-block;
    text-decoration: none;
    padding: 11px 15px;
    border-radius: 14px;
    font-weight: 900;
    background: #f8fafc;
    color: #0f3460;
    border: 1px solid #dbeafe;
}

.btn-luxoria.primary {
    background: linear-gradient(135deg, #0f3460, #06152b);
    color: white;
    border: none;
}

.btn-luxoria.gold {
    background: #fbbf24;
    color: #111827;
    border: none;
}

.badge {
    padding: 6px 10px;
    border-radius: 999px;
    font-weight: 900;
    display: inline-block;
    font-size: 12px;
}

.badge.green { background: #dcfce7; color: #166534; }
.badge.red { background: #fee2e2; color: #991b1b; }
.badge.orange { background: #ffedd5; color: #9a3412; }
.badge.blue { background: #dbeafe; color: #1e40af; }

.amount {
    font-weight: 900;
    color: #0f3460;
}

.small-muted {
    color: #6b7280;
    font-size: 12px;
}
</style>
</head>

<body>
<div class="admin-layout">

<?php require_once "../../includes/sidebar.php"; ?>

<main class="main-content">
<?php require_once "../../includes/topbar.php"; ?>

<div class="hero-luxoria">
    <h2>Notes de Perception Fractionnées</h2>
    <p>Suivi des NPF générées à partir des avis de fractionnement accordés.</p>
</div>

<div class="panel">
    <div class="filters-luxoria">
        <a href="np_list.php?type=globale" class="btn-luxoria">NP globales</a>
        <a href="np_list.php?statut=non_payee" class="btn-luxoria">NP non payées</a>
        <a href="np_list.php?statut=defaillante" class="btn-luxoria">Défaillantes</a>
        <a href="np_list.php" class="btn-luxoria primary">Toutes les NP</a>
    </div>

    <table class="table-premium">
        <tr>
            <th>N° NPF</th>
            <th>Tranche</th>
            <th>NP mère</th>
            <th>Avis</th>
            <th>Contribuable</th>
            <th>NIF</th>
            <th>Montant</th>
            <th>Solde</th>
            <th>Échéance</th>
            <th>Statut</th>
            <th>Action</th>
        </tr>

        <?php foreach ($fractions as $f): ?>
            <tr>
                <td>
                    <strong><?= htmlspecialchars($f['numero_np']) ?></strong>
                    <div class="small-muted">ND : <?= htmlspecialchars($f['numero_nd']) ?></div>
                </td>

                <td>
                    NPF-<?= str_pad((int)$f['numero_tranche'], 2, '0', STR_PAD_LEFT) ?>
                </td>

                <td><?= htmlspecialchars($f['numero_np_mere'] ?? '-') ?></td>

                <td>
                    <?= htmlspecialchars($f['numero_avis'] ?? '-') ?><br>
                    <span class="small-muted">
                        <?= htmlspecialchars(($f['autorite_type'] ?? '') . ' ' . ($f['autorite_nom'] ?? '')) ?>
                    </span>
                </td>

                <td><?= htmlspecialchars(nomContribuableFraction($f)) ?></td>
                <td><?= htmlspecialchars($f['nif'] ?? '-') ?></td>

                <td class="amount">
                    <?= number_format($f['montant_initial'], 2, ',', ' ') ?> CDF
                </td>

                <td class="amount">
                    <?= number_format($f['solde_restant'], 2, ',', ' ') ?> CDF
                </td>

                <td><?= htmlspecialchars(formatDateFraction($f['date_echeance'])) ?></td>

                <td><?= badgeFraction($f['statut']) ?></td>

                <td>
                    <a class="btn-luxoria primary"
                       href="np_view.php?numero=<?= urlencode($f['numero_np']) ?>">
                        Voir
                    </a>

                    <a class="btn-luxoria gold"
                       href="../rapports/npf_pdf.php?numero=<?= urlencode($f['numero_np']) ?>"
                       target="_blank">
                        PDF
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>

        <?php if (empty($fractions)): ?>
            <tr>
                <td colspan="11">
                    Aucune NPF trouvée. Crée d’abord un avis de fractionnement depuis une NP globale.
                </td>
            </tr>
        <?php endif; ?>
    </table>
</div>

</main>
</div>
</body>
</html>