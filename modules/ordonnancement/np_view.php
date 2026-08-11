<?php
require_once "../../config/database.php";
require_once "../../config/security.php";


$page_title = "Note de Perception";

$numero_np = $_GET['numero'] ?? null;
if (!$numero_np) {
    die("Numéro NP manquant.");
}

$stmt = $pdo->prepare("
    SELECT 
        np.*,
        nd.numero_nd,
        nd.statut AS statut_nd,
        nt.numero_nt,
        nt.exercice,
        c.type_personne,
        c.raison_sociale,
        c.nom,
        c.postnom,
        c.prenom,
        c.nif,
        c.rccm,
        c.telephone,
        c.adresse,
        c.ville,
        u.nom AS nom_ordonnateur
    FROM notes_perception np
    JOIN notes_debit nd ON np.note_debit_id = nd.id
    JOIN notes_taxation nt ON nd.note_taxation_id = nt.id
    JOIN contribuables c ON nt.contribuable_id = c.id
    LEFT JOIN users u ON np.user_ordonnateur_id = u.id
    WHERE np.numero_np = ?
    LIMIT 1
");
$stmt->execute([$numero_np]);
$np = $stmt->fetch();

if (!$np) {
    die("NP introuvable.");
}

/*
|--------------------------------------------------------------------------
| Récupération des NPF filles si la note affichée est une NP globale
|--------------------------------------------------------------------------
*/
$npf_filles = [];

if (($np['type_np'] ?? '') === 'globale') {
    $stmt = $pdo->prepare("
        SELECT *
        FROM notes_perception
        WHERE np_mere_id = ?
        AND type_np = 'fractionnee'
        ORDER BY numero_tranche ASC
    ");
    $stmt->execute([$np['id']]);
    $npf_filles = $stmt->fetchAll();
}

/*
|--------------------------------------------------------------------------
| Pénalités AMR liées à cette NP / NPF
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT 
        IFNULL(SUM(montant_penalite), 0) AS total_penalite_amr,
        IFNULL(SUM(montant_total), 0) AS total_amr,
        COUNT(*) AS nombre_amr
    FROM amr
    WHERE note_perception_id = ?
    AND reference_numero = ?
");
$stmt->execute([
    $np['id'],
    $np['numero_np']
]);
$amrInfoNP = $stmt->fetch();

$penaliteAmrNP = (float)($amrInfoNP['total_penalite_amr'] ?? 0);
$nombreAmrNP = (int)($amrInfoNP['nombre_amr'] ?? 0);

function nomContribuableNPView($c)
{
    if (!empty($c['raison_sociale'])) {
        return $c['raison_sociale'];
    }

    return trim(($c['nom'] ?? '') . ' ' . ($c['postnom'] ?? '') . ' ' . ($c['prenom'] ?? ''));
}

function formatDateNP($date)
{
    if (!$date) return '-';
    return date('d/m/Y H:i:s', strtotime($date));
}

function badgeStatutNP($statut)
{
    $label = strtoupper(str_replace('_', ' ', $statut ?? '-'));

    if ($statut === 'payee') {
        return "<span class='badge-np green'>$label</span>";
    }

    if ($statut === 'defaillante') {
        return "<span class='badge-np red'>$label</span>";
    }

    if ($statut === 'partiellement_payee') {
        return "<span class='badge-np orange'>$label</span>";
    }

    return "<span class='badge-np blue'>$label</span>";
}

$statutLabel = strtoupper(str_replace('_', ' ', $np['statut'] ?? 'en_attente'));
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($page_title) ?> | cOllect_Pay</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../../assets/css/admin.css">

<style>
.doc-header{
    background:linear-gradient(135deg,#06152b,#0f3460);
    color:white;
    border-radius:24px;
    padding:26px;
    margin-bottom:25px;
}

.badge-status{
    display:inline-block;
    padding:7px 12px;
    border-radius:999px;
    font-weight:900;
    background:#fbbf24;
    color:#111827;
}

.badge-np{
    padding:6px 10px;
    border-radius:999px;
    font-weight:900;
    display:inline-block;
    font-size:12px;
}

.badge-np.green{background:#dcfce7;color:#166534}
.badge-np.red{background:#fee2e2;color:#991b1b}
.badge-np.orange{background:#ffedd5;color:#9a3412}
.badge-np.blue{background:#dbeafe;color:#1e40af}

.amount-big{
    font-size:30px;
    font-weight:900;
    color:#0f3460;
}

.actions{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    margin-top:20px;
}

.actions a{
    text-decoration:none;
    padding:12px 18px;
    border-radius:14px;
    font-weight:900;
}

.btn-primary-custom{background:#0f3460;color:white}
.btn-outline-custom{border:1px solid #0f3460;color:#0f3460;background:white}
.btn-gold-custom{background:#fbbf24;color:#111827}
.btn-danger-custom{background:#dc2626;color:white}

.stamp-box{
    border:2px dashed #0f3460;
    border-radius:18px;
    height:90px;
    display:flex;
    align-items:center;
    justify-content:center;
    font-weight:900;
    color:#0f3460;
    margin-top:10px;
}

.grid-2{
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:14px;
}

.small-muted{
    color:#64748b;
    font-size:12px;
}
</style>
<link rel="stylesheet" href="../../assets/css/ordonnancement.css">
</head>

<body class="cp-ordonnancement-page">
<div class="admin-layout">

<?php require_once "../../includes/sidebar.php"; ?>

<main class="main-content">
<?php require_once "../../includes/topbar.php"; ?>

<div class="doc-header">
    <h2>
        <?= ($np['type_np'] ?? '') === 'fractionnee'
            ? 'NOTE DE PERCEPTION FRACTIONNÉE'
            : 'NOTE DE PERCEPTION GLOBALE'
        ?>
    </h2>

    <p>Numéro : <strong><?= htmlspecialchars($np['numero_np']) ?></strong></p>

    <span class="badge-status">
        <?= htmlspecialchars($statutLabel) ?>
    </span>
</div>

<div class="panel cp-panel cp-view-shell">
    <h3>I. Références</h3>

    <table class="table-premium cp-ord-table">
        <tr>
            <th>Type NP</th>
            <td><?= strtoupper(htmlspecialchars($np['type_np'] ?? '-')) ?></td>
        </tr>
        <tr>
            <th>Note de Débit</th>
            <td><?= htmlspecialchars($np['numero_nd']) ?></td>
        </tr>
        <tr>
            <th>Note de Taxation</th>
            <td><?= htmlspecialchars($np['numero_nt']) ?></td>
        </tr>
        <tr>
            <th>Exercice</th>
            <td><?= htmlspecialchars($np['exercice']) ?></td>
        </tr>
        <tr>
            <th>Ordonnateur</th>
            <td><?= htmlspecialchars($np['nom_ordonnateur'] ?? 'Utilisateur système') ?></td>
        </tr>

        <?php if (($np['type_np'] ?? '') === 'fractionnee'): ?>
            <tr>
                <th>Numéro tranche</th>
                <td>NPF-<?= str_pad((int)$np['numero_tranche'], 3, '0', STR_PAD_LEFT) ?></td>
            </tr>
        <?php endif; ?>
    </table>
</div>
<div class="panel">
    <h3>II. Contribuable</h3>

    <p>
        <strong><?= htmlspecialchars(nomContribuableNPView($np)) ?></strong><br>
        Type : <?= htmlspecialchars($np['type_personne'] ?? '-') ?><br>
        NIF : <?= htmlspecialchars($np['nif'] ?? '-') ?><br>
        RCCM / Patente : <?= htmlspecialchars($np['rccm'] ?? '-') ?><br>
        Téléphone : <?= htmlspecialchars($np['telephone'] ?? '-') ?><br>
        Ville / Adresse :
        <?= htmlspecialchars(($np['ville'] ?? '') . ' - ' . ($np['adresse'] ?? '-')) ?>
    </p>
</div>

<div class="panel">
    <h3>III. Montant & échéance</h3>

    <table class="table-premium cp-ord-table">
        <tr>
            <th>Montant initial</th>
            <td><?= number_format($np['montant_initial'] ?? 0, 2, ',', ' ') ?> CDF</td>
        </tr>
        <tr>
            <th>Montant payé</th>
            <td><?= number_format($np['montant_paye'] ?? 0, 2, ',', ' ') ?> CDF</td>
        </tr>
        <tr>
            <th>Solde restant</th>
            <td><strong><?= number_format($np['solde_restant'] ?? 0, 2, ',', ' ') ?> CDF</strong></td>
        </tr>
        <tr>
            <th>Pénalité d’assiette</th>
            <td><?= number_format($np['penalite_assiette'] ?? 0, 2, ',', ' ') ?> CDF</td>
        </tr>
        <tr>
            <th>Pénalité de recouvrement</th>
            <td><?= number_format($np['penalite_recouvrement'] ?? 0, 2, ',', ' ') ?> CDF</td>
        </tr>
        <?php if ($penaliteAmrNP > 0): ?>
            <tr>
                <th>Pénalité AMR</th>
                <td><strong><?= number_format($penaliteAmrNP, 2, ',', ' ') ?> CDF</strong></td>
            </tr>
        <?php endif; ?>
        <tr>
            <th>Date échéance</th>
            <td><strong><?= htmlspecialchars(formatDateNP($np['date_echeance'] ?? null)) ?></strong></td>
        </tr>
    </table>

    <p style="text-align:right;margin-top:20px;">
        Total global à percevoir :
        <span class="amount-big">
            <?= number_format($np['solde_restant'] ?? 0, 2, ',', ' ') ?> CDF
        </span>
        <?php if ($penaliteAmrNP > 0): ?>
            <br><small>Pénalités AMR incluses dans le solde global.</small>
        <?php endif; ?>
    </p>
</div>

<div class="panel">
    <h3>IV. Déclarant & sceau</h3>

    <div class="grid-2">
        <div>
            <p>
                <strong>Déclarant / Signataire :</strong><br>
                <?= htmlspecialchars($np['declarant_nom'] ?? '-') ?>
            </p>

            <p>
                <strong>Sceau :</strong>
                <?= !empty($np['sceau_appose']) ? 'Apposé' : 'Non apposé' ?>
            </p>
        </div>

        <div>
            <div class="stamp-box">
                ESPACE SCEAU / SIGNATURE
            </div>
        </div>
    </div>
</div>

<?php if (!empty($np['annotation_autorite'])): ?>
<div class="panel">
    <h3>V. Annotation autorité</h3>
    <p><?= nl2br(htmlspecialchars($np['annotation_autorite'])) ?></p>
</div>
<?php endif; ?>

<?php if (($np['type_np'] ?? '') === 'globale'): ?>
<div class="panel">
    <h3>VI. Notes de Perception Fractionnées liées</h3>

    <table class="table-premium cp-ord-table">
        <tr>
            <th>N° NPF</th>
            <th>Tranche</th>
            <th>Montant</th>
            <th>Payé</th>
            <th>Solde</th>
            <th>Échéance</th>
            <th>Statut</th>
            <th>Actions</th>
        </tr>

        <?php foreach ($npf_filles as $f): ?>
            <tr>
                <td>
                    <strong><?= htmlspecialchars($f['numero_np']) ?></strong>
                    <div class="small-muted">
                        Fille de <?= htmlspecialchars($np['numero_np']) ?>
                    </div>
                </td>
                <td><?= str_pad((int)$f['numero_tranche'], 3, '0', STR_PAD_LEFT) ?></td>
                <td><?= number_format($f['montant_initial'] ?? 0, 2, ',', ' ') ?> CDF</td>
                <td><?= number_format($f['montant_paye'] ?? 0, 2, ',', ' ') ?> CDF</td>
                <td><strong><?= number_format($f['solde_restant'] ?? 0, 2, ',', ' ') ?> CDF</strong></td>
                <td><?= htmlspecialchars(formatDateNP($f['date_echeance'] ?? null)) ?></td>
                <td><?= badgeStatutNP($f['statut'] ?? '-') ?></td>
                <td>
                    <a href="np_view.php?numero=<?= urlencode($f['numero_np']) ?>"
                       class="btn-primary-custom">
                        Voir
                    </a>

                    <?php if (in_array(($f['statut'] ?? ''), ['en_attente', 'non_payee', 'partiellement_payee', 'partielle'])): ?>
                        <a href="../recouvrement/paiement_add.php?numero=<?= urlencode($f['numero_np']) ?>"
                           class="btn-gold-custom">
                            Payer
                        </a>
                    <?php endif; ?>

                    <a href="../rapports/npf_pdf.php?numero=<?= urlencode($f['numero_np']) ?>"
                       target="_blank"
                       class="btn-outline-custom">
                        PDF
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>

        <?php if (empty($npf_filles)): ?>
            <tr>
                <td colspan="8">
                    Aucune NPF générée pour cette NP mère.
                </td>
            </tr>
        <?php endif; ?>
    </table>
</div>
<?php endif; ?>

<div class="panel">
    <div class="actions">
        <a href="np_list.php" class="btn-primary-custom">Retour liste</a>

        <?php if (($np['type_np'] ?? '') === 'fractionnee'): ?>
            <a href="../rapports/npf_pdf.php?numero=<?= urlencode($np['numero_np']) ?>"
               target="_blank"
               class="btn-outline-custom">
                Imprimer NPF
            </a>
        <?php else: ?>
            <a href="../rapports/np_pdf.php?numero=<?= urlencode($np['numero_np']) ?>"
               target="_blank"
               class="btn-outline-custom">
                Imprimer NP
            </a>
        <?php endif; ?>

        <?php if (in_array(($np['statut'] ?? ''), ['en_attente', 'non_payee', 'partiellement_payee', 'partielle', 'fractionnee'])): ?>
            <a href="../recouvrement/paiement_add.php?numero=<?= urlencode($np['numero_np']) ?>"
               class="btn-gold-custom">
                💰 Nouveau paiement
            </a>
        <?php endif; ?>


        <?php if (
            ($np['type_np'] ?? '') === 'globale'
            && in_array(($np['statut'] ?? ''), ['en_attente', 'non_payee'])
            && empty($npf_filles)
        ): ?>
            <a href="avis_fractionnement_create.php?numero_np=<?= urlencode($np['numero_np']) ?>"
               class="btn-gold-custom">
                Créer avis de fractionnement
            </a>
        <?php endif; ?>
    </div>
</div>

</main>
</div>
</body>
</html>