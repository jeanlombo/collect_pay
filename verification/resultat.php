<?php
$page_title = "Résultat vérification";
require_once __DIR__ . "/header.php";

$type   = strtoupper(trim($_GET['type_document'] ?? 'ALL'));
$numero = trim($_GET['numero_document'] ?? '');

[$foundType, $meta, $doc] = vFind($pdo, $type, $numero);

$contrib = $doc ? vContrib($pdo, $foundType, $doc) : null;

$ref = vLog(
    $pdo,
    $foundType,
    $numero,
    $doc ? 'AUTHENTIQUE' : 'NON_TROUVE'
);
?>

<div class="card">
    <?php if ($doc): ?>

        <div class="ok">
            ✅ DOCUMENT AUTHENTIQUE — Vérifié dans la base cOllect_Pay.
        </div>

        <br>

        <div class="grid">
            <div class="info">
                <small>Type</small>
                <strong><?= vSafe($meta['label']) ?></strong>
            </div>

            <div class="info">
                <small>Numéro</small>
                <strong><?= vSafe($doc[$meta['numero']] ?? $numero) ?></strong>
            </div>

            <div class="info">
                <small>Statut</small>
                <strong><?= vSafe(strtoupper($doc['statut'] ?? '-')) ?></strong>
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
                <small>Date</small>
                <strong><?= vSafe($doc[$meta['date']] ?? '-') ?></strong>
            </div>

            <div class="info">
                <small>Contribuable</small>
                <strong><?= vSafe(vName($contrib)) ?></strong>
            </div>
        </div>

        <br>

        <a class="btn"
           href="document.php?type_document=<?= urlencode($foundType) ?>&numero_document=<?= urlencode($numero) ?>">
            Voir le document
        </a>

        <a class="btn btn-gold"
           href="certificat.php?ref=<?= urlencode($ref) ?>&type_document=<?= urlencode($foundType) ?>&numero_document=<?= urlencode($numero) ?>">
            Certificat
        </a>

        <a class="btn btn-gray" href="rechercher.php">
            Nouvelle recherche
        </a>
    <a class="btn btn-gray" href="../login.php">
        Retour
    </a>
    <?php else: ?>

        <div class="bad">
            ⛔ DOCUMENT NON AUTHENTIQUE OU INTROUVABLE.
        </div>

        <p>
            Le numéro saisi n’existe pas dans la base officielle cOllect_Pay.
        </p>

        <a class="btn" href="rechercher.php">
            Réessayer
        </a>

    <?php endif; ?>
</div>

<?php require_once __DIR__ . "/footer.php"; ?>