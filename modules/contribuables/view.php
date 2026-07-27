<?php
require_once "../../config/database.php";
require_once "../../config/security.php";
require_once "../../core/functions.php";

checkAuth();

$id = $_GET['id'] ?? null;

if (!$id) {
    die("Contribuable introuvable.");
}

$stmt = $pdo->prepare("SELECT * FROM contribuables WHERE id = ?");
$stmt->execute([$id]);
$contribuable = $stmt->fetch();

if (!$contribuable) {
    die("Contribuable introuvable.");
}

function nomContribuableView($c) {
    if (!empty($c['raison_sociale'])) {
        return $c['raison_sociale'];
    }

    return trim(($c['nom'] ?? '') . ' ' . ($c['postnom'] ?? '') . ' ' . ($c['prenom'] ?? ''));
}

$nomContribuable = nomContribuableView($contribuable);

$totalNT = $pdo->prepare("SELECT COUNT(*) total FROM notes_taxation WHERE contribuable_id = ?");
$totalNT->execute([$id]);
$totalNT = $totalNT->fetch()['total'];

$totalND = $pdo->prepare("
    SELECT COUNT(*) total
    FROM notes_debit nd
    JOIN notes_taxation nt ON nd.note_taxation_id = nt.id
    WHERE nt.contribuable_id = ?
");
$totalND->execute([$id]);
$totalND = $totalND->fetch()['total'];

$totalNP = $pdo->prepare("
    SELECT COUNT(*) total
    FROM notes_perception np
    JOIN notes_debit nd ON np.note_debit_id = nd.id
    JOIN notes_taxation nt ON nd.note_taxation_id = nt.id
    WHERE nt.contribuable_id = ?
");
$totalNP->execute([$id]);
$totalNP = $totalNP->fetch()['total'];

$totalQT = $pdo->prepare("
    SELECT COUNT(*) total
    FROM quittances q
    JOIN apurements a ON q.apurement_id = a.id
    JOIN notes_perception np ON a.reference_id = np.id
    JOIN notes_debit nd ON np.note_debit_id = nd.id
    JOIN notes_taxation nt ON nd.note_taxation_id = nt.id
    WHERE a.reference_type='NP'
    AND nt.contribuable_id = ?
");
$totalQT->execute([$id]);
$totalQT = $totalQT->fetch()['total'];

$totalPaye = $pdo->prepare("
    SELECT IFNULL(SUM(p.montant_converti_cdf),0) total
    FROM paiements p
    JOIN notes_perception np ON p.note_perception_id = np.id
    JOIN notes_debit nd ON np.note_debit_id = nd.id
    JOIN notes_taxation nt ON nd.note_taxation_id = nt.id
    WHERE nt.contribuable_id = ?
");
$totalPaye->execute([$id]);
$totalPaye = $totalPaye->fetch()['total'];

$totalImpayes = $pdo->prepare("
    SELECT IFNULL(SUM(np.montant_total),0) total
    FROM notes_perception np
    JOIN notes_debit nd ON np.note_debit_id = nd.id
    JOIN notes_taxation nt ON nd.note_taxation_id = nt.id
    WHERE nt.contribuable_id = ?
    AND np.statut <> 'totalement_payee'
");
$totalImpayes->execute([$id]);
$totalImpayes = $totalImpayes->fetch()['total'];

$notes = $pdo->prepare("
    SELECT np.numero_np, np.montant_total, np.date_echeance, np.statut
    FROM notes_perception np
    JOIN notes_debit nd ON np.note_debit_id = nd.id
    JOIN notes_taxation nt ON nd.note_taxation_id = nt.id
    WHERE nt.contribuable_id = ?
    ORDER BY np.created_at DESC
    LIMIT 10
");
$notes->execute([$id]);
$notes = $notes->fetchAll();

$page_title = "Fiche Contribuable";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= $page_title ?> | cOllect_Pay</title>
    <link rel="stylesheet" href="/collect_pay/assets/css/admin.css">

    <style>
        .profile-grid {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 24px;
        }

        .profile-card {
            background: white;
            border-radius: 24px;
            padding: 24px;
            box-shadow: 0 15px 35px rgba(0,0,0,.08);
            text-align: center;
        }

        .profile-card img {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 50%;
            border: 5px solid #0f3460;
            margin-bottom: 15px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        .info-box {
            background: #f8fafc;
            padding: 14px;
            border-radius: 14px;
            border: 1px solid #e5e7eb;
        }

        .info-box span {
            display: block;
            font-size: 12px;
            color: #6b7280;
            font-weight: 800;
        }

        .info-box strong {
            color: #111827;
        }

        .action-row {
            margin-top: 20px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn-premium {
            background: #0f3460;
            color: white;
            padding: 12px 18px;
            border-radius: 14px;
            text-decoration: none;
            font-weight: 800;
        }

        .btn-gold {
            background: #fbbf24;
            color: #111827;
        }
    </style>
</head>
<body>

<div class="admin-layout">
    <?php require_once "../../includes/sidebar.php"; ?>

    <main class="main-content">
        <?php require_once "../../includes/topbar.php"; ?>

        <div class="profile-grid">

            <div class="profile-card">
                <?php if (!empty($contribuable['photo'])): ?>
                    <img src="/collect_pay/assets/uploads/contribuables/<?= htmlspecialchars($contribuable['photo']) ?>">
                <?php else: ?>
                    <img src="/collect_pay/assets/images/default-user.png">
                <?php endif; ?>

                <h3><?= htmlspecialchars($nomContribuable) ?></h3>
                <p><?= htmlspecialchars($contribuable['code_contribuable'] ?? '-') ?></p>
                <p><strong><?= strtoupper($contribuable['statut']) ?></strong></p>

                <div class="action-row" style="justify-content:center;">
                    <a class="btn-premium" href="edit.php?id=<?= $contribuable['id'] ?>">Modifier</a>
                    <a class="btn-premium btn-gold" href="../constatation/nt_create.php?contribuable_id=<?= $contribuable['id'] ?>">
                        Créer NT
                    </a>
                </div>
            </div>

            <div class="panel" style="margin-top:0;">
                <h3>Informations du contribuable</h3>

                <div class="info-grid">
                    <div class="info-box">
                        <span>Type</span>
                        <strong><?= htmlspecialchars($contribuable['type_personne']) ?></strong>
                    </div>

                    <div class="info-box">
                        <span>NIF</span>
                        <strong><?= htmlspecialchars($contribuable['nif'] ?? 'NON ATTRIBUE') ?></strong>
                    </div>

                    <div class="info-box">
                        <span>RCCM / Patente</span>
                        <strong><?= htmlspecialchars($contribuable['rccm'] ?? '-') ?></strong>
                    </div>

                    <div class="info-box">
                        <span>ID National</span>
                        <strong><?= htmlspecialchars($contribuable['id_national'] ?? '-') ?></strong>
                    </div>

                    <div class="info-box">
                        <span>Téléphone</span>
                        <strong><?= htmlspecialchars($contribuable['telephone'] ?? '-') ?></strong>
                    </div>

                    <div class="info-box">
                        <span>Email</span>
                        <strong><?= htmlspecialchars($contribuable['email'] ?? '-') ?></strong>
                    </div>

                    <div class="info-box">
                        <span>Ville / Commune</span>
                        <strong><?= htmlspecialchars(($contribuable['ville'] ?? '-') . ' / ' . ($contribuable['commune'] ?? '-')) ?></strong>
                    </div>

                    <div class="info-box">
                        <span>Adresse</span>
                        <strong><?= htmlspecialchars($contribuable['adresse'] ?? '-') ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="kpi-grid" style="margin-top:28px;">
            <div class="kpi-card">
                <span>Notes de Taxation</span>
                <h2><?= $totalNT ?></h2>
            </div>

            <div class="kpi-card">
                <span>Notes de Débit</span>
                <h2><?= $totalND ?></h2>
            </div>

            <div class="kpi-card">
                <span>Notes de Perception</span>
                <h2><?= $totalNP ?></h2>
            </div>

            <div class="kpi-card">
                <span>Quittances</span>
                <h2><?= $totalQT ?></h2>
            </div>
        </div>

        <div class="kpi-grid" style="margin-top:28px;grid-template-columns:repeat(2,1fr);">
            <div class="kpi-card">
                <span>Total payé</span>
                <h2><?= number_format($totalPaye, 0, ',', ' ') ?> CDF</h2>
            </div>

            <div class="kpi-card">
                <span>Total impayé</span>
                <h2><?= number_format($totalImpayes, 0, ',', ' ') ?> CDF</h2>
            </div>
        </div>

        <div class="panel">
            <h3>Dernières Notes de Perception</h3>

            <table class="table-premium">
                <tr>
                    <th>Numéro NP</th>
                    <th>Montant</th>
                    <th>Échéance</th>
                    <th>Statut</th>
                    <th>Action</th>
                </tr>

                <?php foreach($notes as $n): ?>
                    <tr>
                        <td><?= htmlspecialchars($n['numero_np']) ?></td>
                        <td><?= number_format($n['montant_total'], 0, ',', ' ') ?> CDF</td>
                        <td><?= htmlspecialchars($n['date_echeance']) ?></td>
                        <td><?= strtoupper(htmlspecialchars($n['statut'])) ?></td>
                        <td>
                            <a href="../ordonnancement/np_view.php?numero=<?= htmlspecialchars($n['numero_np']) ?>">
                                Voir
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if(empty($notes)): ?>
                    <tr>
                        <td colspan="5">Aucune note de perception trouvée.</td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>

    </main>
</div>

</body>
</html>