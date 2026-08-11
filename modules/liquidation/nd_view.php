<?php
require_once "../../config/database.php";
require_once "../../config/security.php";
require_once "../../core/functions.php";


$numero_nd = $_GET['numero'] ?? null;
if (!$numero_nd) {
    die("Numéro ND manquant.");
}

$stmt = $pdo->prepare("
    SELECT 
        nd.*,
        nt.numero_nt,
        nt.exercice,
        nt.created_at AS date_nt,
        c.type_personne,
        c.raison_sociale,
        c.nom,
        c.postnom,
        c.prenom,
        c.nif,
        c.rccm,
        c.telephone,
        c.adresse,
        c.ville
    FROM notes_debit nd
    JOIN notes_taxation nt ON nd.note_taxation_id = nt.id
    JOIN contribuables c ON nt.contribuable_id = c.id
    WHERE nd.numero_nd = ?
    LIMIT 1
");
$stmt->execute([$numero_nd]);
$nd = $stmt->fetch();

if (!$nd) {
    die("ND introuvable.");
}

$stmt = $pdo->prepare("
    SELECT
        d.*,
        a.code_article,
        a.secteur,
        a.nature_acte,
        a.taux_acte AS article_taux_acte,
        a.devise_base AS article_devise_base,
        a.libelle_taux AS article_libelle_taux
    FROM notes_taxation_details d
    JOIN articles_budgetaires a ON d.article_id = a.id
    WHERE d.note_taxation_id = ?
    ORDER BY d.id ASC
");
$stmt->execute([$nd['note_taxation_id']]);
$details = $stmt->fetchAll();

function nomContribuableND($c) {
    if (!empty($c['raison_sociale'])) {
        return $c['raison_sociale'];
    }

    return trim(($c['nom'] ?? '') . ' ' . ($c['postnom'] ?? '') . ' ' . ($c['prenom'] ?? ''));
}

function moneyNDView($value) {
    return number_format((float)$value, 2, ',', ' ') . ' CDF';
}

function qtyNDView($value) {
    $v = (float)$value;
    if (floor($v) == $v) {
        return number_format($v, 0, ',', ' ');
    }
    return number_format($v, 2, ',', ' ');
}

function textNDView($value) {
    $value = trim((string)($value ?? ''));
    return $value !== '' ? $value : '-';
}

function detailCalculNDView($d) {
    $devise = strtoupper($d['devise_source'] ?? $d['article_devise_base'] ?? 'CDF');
    $tauxChange = (float)($d['taux_change'] ?? 1);
    if ($tauxChange <= 0) {
        $tauxChange = 1;
    }

    $base = (float)($d['base_imposable'] ?? 0);
    $quantite = (float)($d['quantite'] ?? 1);
    $principalCdf = (float)($d['montant_acte'] ?? 0);
    $faCdf = (float)($d['montant_frais_admin'] ?? 0);
    $ftCdf = (float)($d['montant_frais_tech'] ?? 0);

    $tauxActe = (float)($d['article_taux_acte'] ?? 0);
    if ($tauxActe <= 0 && $quantite > 0) {
        if ($devise === 'USD') {
            $tauxActe = ($principalCdf / $tauxChange) / $quantite;
        } else {
            $tauxActe = $principalCdf / $quantite;
        }
    }

    $acte = textNDView($d['nature_acte'] ?? $d['libelle_acte'] ?? $d['acte_generateur'] ?? 'Acte taxable');

    if ($devise === 'USD') {
        $principalUsd = $tauxChange > 0 ? ($principalCdf / $tauxChange) : 0;
        $faUsd = $tauxChange > 0 ? ($faCdf / $tauxChange) : 0;
        $ftUsd = $tauxChange > 0 ? ($ftCdf / $tauxChange) : 0;

        return
            htmlspecialchars($acte) . ' :<br>' .
            qtyNDView($quantite) . ' × ' .
            number_format($tauxActe, 2, ',', ' ') . ' USD = ' .
            number_format($principalUsd, 2, ',', ' ') . ' USD<br>' .
            'ou soit ' . moneyNDView($principalCdf) . '<br>' .
            'Frais administratif : ' .
            number_format($faUsd, 2, ',', ' ') . ' USD × ' .
            number_format($tauxChange, 0, ',', ' ') . ' = ' .
            moneyNDView($faCdf) . '<br>' .
            'Frais technique : ' .
            number_format($ftUsd, 2, ',', ' ') . ' USD × ' .
            number_format($tauxChange, 0, ',', ' ') . ' = ' .
            moneyNDView($ftCdf);
    }

    return
        htmlspecialchars($acte) . ' :<br>' .
        qtyNDView($quantite) . ' × ' .
        number_format($tauxActe, 2, ',', ' ') . ' CDF = ' .
        moneyNDView($principalCdf) . '<br>' .
        'Frais administratif : ' . moneyNDView($faCdf) . '<br>' .
        'Frais technique : ' . moneyNDView($ftCdf);
}

$page_title = "Note de Débit";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($page_title) ?> | cOllect_Pay</title>
    <link rel="stylesheet" href="../../assets/css/admin.css">
<link rel="stylesheet" href="../../assets/css/liquidation.css">
</head>

<body class="cp-liquidation-page cp-nd-view">
<div class="admin-layout">

<?php require_once "../../includes/sidebar.php"; ?>

<main class="main-content">
<?php require_once "../../includes/topbar.php"; ?>

<div class="panel cp-liquidation-panel">
    <h2>NOTE DE DÉBIT N° <?= htmlspecialchars($nd['numero_nd']) ?></h2>
    <p><strong>Statut :</strong> <?= strtoupper(htmlspecialchars($nd['statut'])) ?></p>
</div>

<div class="panel cp-liquidation-panel">
    <h3>I. Contribuable</h3>

    <p>
        <strong><?= htmlspecialchars(nomContribuableND($nd)) ?></strong><br>
        Type : <?= htmlspecialchars(strtoupper($nd['type_personne'] ?? '-')) ?><br>
        NIF : <?= htmlspecialchars($nd['nif'] ?? '-') ?><br>
        RCCM / Patente : <?= htmlspecialchars($nd['rccm'] ?? '-') ?><br>
        Téléphone : <?= htmlspecialchars($nd['telephone'] ?? '-') ?><br>
        Adresse : <?= htmlspecialchars(trim(($nd['ville'] ?? '') . ' - ' . ($nd['adresse'] ?? '-'))) ?>
    </p>
</div>

<div class="panel cp-liquidation-panel">
    <h3>II. Référence NT</h3>

    <p>
        Note de Taxation :
        <strong><?= htmlspecialchars($nd['numero_nt']) ?></strong><br>
        Exercice : <?= htmlspecialchars($nd['exercice']) ?><br>
        Date NT : <?= htmlspecialchars($nd['date_nt']) ?><br>
        Date liquidation : <?= htmlspecialchars($nd['date_liquidation'] ?? '-') ?>
    </p>
</div>

<div class="panel cp-liquidation-panel">
    <h3>III. Détails liquidés</h3>

    <div class="cp-table-wrap"><table class="table-premium">
        <tr>
            <th>Code</th>
            <th>Secteur</th>
            <th>Nature d’acte</th>
            <th>Qté</th>
            <th>Taux du jour</th>
            <th>Détail calcul</th>
            <th>Principal CDF</th>
            <th>FA CDF</th>
            <th>FT CDF</th>
            <th>Total CDF</th>
        </tr>

        <?php foreach($details as $d): ?>
            <tr>
                <td><?= htmlspecialchars($d['code_article']) ?></td>
                <td><?= htmlspecialchars($d['secteur']) ?></td>
                <td><?= htmlspecialchars($d['nature_acte']) ?></td>
                <td><?= qtyNDView($d['quantite']) ?></td>
                <td>
                    <?php if(strtoupper($d['devise_source'] ?? 'CDF') === 'USD'): ?>
                        <?= number_format((float)$d['taux_change'], 0, ',', ' ') ?>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
                <td><?= detailCalculNDView($d) ?></td>
                <td><?= moneyNDView($d['montant_acte']) ?></td>
                <td><?= moneyNDView($d['montant_frais_admin']) ?></td>
                <td><?= moneyNDView($d['montant_frais_tech']) ?></td>
                <td><strong><?= moneyNDView($d['total_ligne_cdf']) ?></strong></td>
            </tr>
        <?php endforeach; ?>

        <?php if(empty($details)): ?>
            <tr>
                <td colspan="10">Aucun détail trouvé.</td>
            </tr>
        <?php endif; ?>
    </table></div>
</div>

<div class="panel cp-liquidation-panel">
    <h3>IV. Synthèse de liquidation</h3>

    <div class="cp-table-wrap"><table class="table-premium">
        <tr>
            <th>Principal dû</th>
            <td><?= moneyNDView($nd['montant_acte'] ?? 0) ?></td>
        </tr>
        <tr>
            <th>Frais administratifs</th>
            <td><?= moneyNDView($nd['montant_frais_admin'] ?? 0) ?></td>
        </tr>
        <tr>
            <th>Frais techniques</th>
            <td><?= moneyNDView($nd['montant_frais_tech'] ?? 0) ?></td>
        </tr>
        <tr>
            <th>Pénalité d’assiette</th>
            <td><?= moneyNDView($nd['penalite_assiette'] ?? 0) ?></td>
        </tr>
        <tr>
            <th>Pénalité de recouvrement</th>
            <td><?= moneyNDView($nd['penalite_recouvrement'] ?? 0) ?></td>
        </tr>
        <tr>
            <th>Total exigible</th>
            <td><strong><?= moneyNDView($nd['montant_total'] ?? $nd['total_exigible']) ?></strong></td>
        </tr>
    </table></div>
</div>

<div class="panel cp-liquidation-panel">
    <h3>V. Observation / Contrôle</h3>

    <p>
        <strong>Observation liquidation :</strong><br>
        <?= nl2br(htmlspecialchars($nd['observation'] ?? 'Aucune observation.')) ?>
    </p>

    <p>
        <strong>Décision contrôle :</strong>
        <?= htmlspecialchars($nd['decision'] ?? '-') ?>
    </p>
</div>

<div class="panel cp-liquidation-panel">
    <a href="nd_list.php" class="btn">Retour liste</a>

    <a href="../rapports/nd_pdf.php?numero=<?= urlencode($nd['numero_nd']) ?>"
       target="_blank"
       class="btn">
        Imprimer ND
    </a>

    <?php if($nd['statut'] === 'en_controle'): ?>
        <a href="nd_validate.php?numero=<?= urlencode($nd['numero_nd']) ?>" class="btn">
            Contrôler / Vérifier
        </a>
    <?php endif; ?>

    <?php if(($nd['statut'] === 'validee') && (($nd['decision'] ?? '') === 'conforme')): ?>
        <a href="../ordonnancement/np_create.php?numero_nd=<?= urlencode($nd['numero_nd']) ?>" class="btn">
            Générer NP
        </a>
    <?php endif; ?>
</div>

</main>
</div>
</body>
</html>
