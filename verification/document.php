<?php
$page_title = "Document vérifié";
require_once __DIR__ . "/header.php";

$type   = strtoupper(trim($_GET['type_document'] ?? 'ALL'));
$numero = trim($_GET['numero_document'] ?? '');

[$foundType, $meta, $doc] = vFind($pdo, $type, $numero);

if (!$doc) {
    echo '<div class="card"><div class="bad">Document introuvable.</div></div>';
    require_once __DIR__ . "/footer.php";
    exit;
}

$contrib = vContrib($pdo, $foundType, $doc);
?>

<div class="card watermark">
    <h2><?= vSafe($meta['label']) ?></h2>

    <div class="ok">
        ✅ Document vérifié par cOllect_Pay
    </div>

    <br>

    <div class="grid">
        <div class="info">
            <small>Numéro</small>
            <strong><?= vSafe($doc[$meta['numero']] ?? $numero) ?></strong>
        </div>

        <div class="info">
            <small>Contribuable</small>
            <strong><?= vSafe(vName($contrib)) ?></strong>
        </div>

        <div class="info">
            <small>Montant</small>
            <strong><?= vMoney(vAmount($foundType, $doc, $meta)) ?></strong>
        </div>

        <?php if (in_array($foundType, ['NP', 'NPF'], true)): ?>
            <div class="info">
                <small>Montant dû</small>
                <strong><?= vMoney($doc['montant_initial'] ?? 0) ?></strong>
            </div>

            <div class="info">
                <small>Montant payé</small>
                <strong><?= vMoney($doc['montant_paye'] ?? 0) ?></strong>
            </div>

            <div class="info">
                <small>Solde restant</small>
                <strong><?= vMoney($doc['solde_restant'] ?? 0) ?></strong>
            </div>
        <?php endif; ?>

        <div class="info">
            <small>Statut</small>
            <strong><?= vSafe(strtoupper($doc['statut'] ?? '-')) ?></strong>
        </div>

        <div class="info">
            <small>Date</small>
            <strong><?= vSafe($doc[$meta['date']] ?? '-') ?></strong>
        </div>

        <div class="info">
            <small>Type</small>
            <strong><?= vSafe($foundType) ?></strong>
        </div>
    </div>

    <br>

    <h3>Détails techniques en lecture seule</h3>

    <table class="table">
        <tr>
            <th>Champ</th>
            <th>Valeur</th>
        </tr>

        <?php foreach ($doc as $k => $v): ?>
            <?php if (is_numeric($k)) continue; ?>
            <?php if (in_array($k, ['qr_hash', 'qr_data', 'password', 'signature_receptionniste'], true)) continue; ?>

            <tr>
                <td><?= vSafe($k) ?></td>
                <td><?= vSafe($v) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>

    <br>

    <button class="btn" onclick="window.print()">Imprimer</button>

    <a class="btn btn-gray" href="rechercher.php">
        Retour
    </a>
</div>

<?php require_once __DIR__ . "/footer.php"; ?>