<?php
require_once "config/database.php";
require_once "core/secure_qr_engine.php";

/*
|--------------------------------------------------------------------------
| Vérification QR sécurisé Collect_Pay
|--------------------------------------------------------------------------
|
| Deux modes supportés :
|
| 1. Ancien mode :
|    verify.php?t=TOKEN
|
| 2. Nouveau mode :
|    verify.php?q=CONTENU_QR_CRYPTE
|
*/

$token = $_GET['t'] ?? null;
$qrContent = $_GET['q'] ?? null;

$data = null;

if (!empty($qrContent)) {

    $verification = verifyEncryptedQrPayload(
        $pdo,
        $qrContent
    );

    if (!$verification['valid']) {
        die($verification['message']);
    }

    $doc = $verification['document_token'];

    $type = $doc['type_document'];
    $numero = $doc['numero_document'];

} else {

    if (!$token) {
        die("Lien de vérification invalide.");
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM document_tokens
        WHERE token = ?
        AND statut = 'actif'
        LIMIT 1
    ");

    $stmt->execute([$token]);

    $doc = $stmt->fetch();

    if (!$doc) {
        die("Document introuvable ou révoqué.");
    }

    if (!verifierSignatureDocument($doc)) {
        die("DOCUMENT CONTREFAIT OU SIGNATURE INVALIDE");
    }

    $type = $doc['type_document'];
    $numero = $doc['numero_document'];
}

function nomDocContribuable($c)
{
    if (!empty($c['raison_sociale'])) {
        return $c['raison_sociale'];
    }

    return trim(
        ($c['nom'] ?? '') . ' ' .
        ($c['postnom'] ?? '') . ' ' .
        ($c['prenom'] ?? '')
    );
}

/*
|--------------------------------------------------------------------------
| NT
|--------------------------------------------------------------------------
*/
if ($type === 'NT') {

    $stmt = $pdo->prepare("
        SELECT
            nt.numero_nt AS numero,
            nt.total_estime AS montant,
            nt.statut,
            nt.created_at,
            c.raison_sociale,
            c.nom,
            c.postnom,
            c.prenom,
            c.nif
        FROM notes_taxation nt
        JOIN contribuables c
            ON nt.contribuable_id = c.id
        WHERE nt.numero_nt = ?
        LIMIT 1
    ");

    $stmt->execute([$numero]);
    $data = $stmt->fetch();
}

/*
|--------------------------------------------------------------------------
| ND
|--------------------------------------------------------------------------
*/
if ($type === 'ND') {

    $stmt = $pdo->prepare("
        SELECT
            nd.numero_nd AS numero,
            nd.montant_total AS montant,
            nd.statut,
            nd.created_at,
            c.raison_sociale,
            c.nom,
            c.postnom,
            c.prenom,
            c.nif
        FROM notes_debit nd
        JOIN notes_taxation nt
            ON nd.note_taxation_id = nt.id
        JOIN contribuables c
            ON nt.contribuable_id = c.id
        WHERE nd.numero_nd = ?
        LIMIT 1
    ");

    $stmt->execute([$numero]);
    $data = $stmt->fetch();
}

/*
|--------------------------------------------------------------------------
| NP / NPF
|--------------------------------------------------------------------------
*/
if ($type === 'NP' || $type === 'NPF') {

    $stmt = $pdo->prepare("
        SELECT
            np.numero_np AS numero,
            np.type_np,
            np.montant_initial AS montant,
            np.solde_restant,
            np.statut,
            np.date_emission AS created_at,
            np.date_echeance,
            mere.numero_np AS numero_np_mere,
            nd.numero_nd,
            nt.numero_nt,
            c.raison_sociale,
            c.nom,
            c.postnom,
            c.prenom,
            c.nif
        FROM notes_perception np
        LEFT JOIN notes_perception mere
            ON np.np_mere_id = mere.id
        JOIN notes_debit nd
            ON np.note_debit_id = nd.id
        JOIN notes_taxation nt
            ON nd.note_taxation_id = nt.id
        JOIN contribuables c
            ON nt.contribuable_id = c.id
        WHERE np.numero_np = ?
        LIMIT 1
    ");

    $stmt->execute([$numero]);
    $data = $stmt->fetch();

    if ($data) {

        if (($data['type_np'] ?? '') === 'fractionnee') {
            $type = 'NPF';
        } else {
            $type = 'NP';
        }
    }
}

/*
|--------------------------------------------------------------------------
| Vérification finale
|--------------------------------------------------------------------------
*/
if (!$data) {
    die("Document non retrouvé dans le système.");
}

$typeLabel = [
    'NT' => 'NOTE DE TAXATION',
    'ND' => 'NOTE DE DÉBIT',
    'NP' => 'NOTE DE PERCEPTION GLOBALE',
    'NPF' => 'NOTE DE PERCEPTION FRACTIONNÉE',
    'AVF' => 'AVIS DE FRACTIONNEMENT',
    'QUITTANCE' => 'QUITTANCE INFORMATISÉE',
    'ACQUIT' => 'ACQUIT LIBÉRATOIRE'
][$type] ?? $type;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Vérification document | cOllect_Pay</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
*{box-sizing:border-box}
body{
    margin:0;
    font-family:Segoe UI,Arial,sans-serif;
    background:linear-gradient(135deg,#06152b,#0f3460);
    min-height:100vh;
    padding:25px;
    color:#1f2937;
}
.card{
    max-width:760px;
    margin:30px auto;
    background:white;
    border-radius:28px;
    overflow:hidden;
    box-shadow:0 30px 80px rgba(0,0,0,.28);
}
.header{
    background:linear-gradient(135deg,#06152b,#0f3460);
    color:white;
    padding:28px;
    text-align:center;
}
.header h1{
    margin:0;
    font-size:24px;
    font-weight:900;
}
.header p{
    margin:8px 0 0;
    color:#cbd5e1;
}
.badge{
    display:inline-block;
    background:#dcfce7;
    color:#166534;
    padding:10px 18px;
    border-radius:999px;
    font-weight:900;
    margin-top:16px;
}
.content{
    padding:28px;
}
.doc-title{
    background:#f8fafc;
    border:1px solid #e5e7eb;
    border-radius:20px;
    padding:18px;
    margin-bottom:22px;
}
.doc-title h2{
    margin:0;
    color:#0f3460;
    font-size:20px;
}
.doc-title strong{
    font-size:18px;
}
.grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:14px;
}
.item{
    border:1px solid #e5e7eb;
    border-radius:16px;
    padding:14px;
    background:#fff;
}
.item span{
    display:block;
    font-size:12px;
    color:#64748b;
    font-weight:800;
    text-transform:uppercase;
    margin-bottom:6px;
}
.item strong{
    color:#06152b;
    font-size:15px;
}
.amount{
    color:#0f3460 !important;
    font-size:20px !important;
}
.footer{
    padding:18px 28px;
    background:#f8fafc;
    border-top:1px solid #e5e7eb;
    font-size:13px;
    color:#64748b;
    text-align:center;
}
.warning{
    margin-top:20px;
    border:1px solid #bfdbfe;
    background:#eff6ff;
    color:#1e3a8a;
    padding:14px;
    border-radius:16px;
    font-weight:700;
}
@media(max-width:650px){
    body{padding:12px}
    .grid{grid-template-columns:1fr}
    .header h1{font-size:20px}
}
</style>
</head>

<body>

<div class="card">
    <div class="header">
        <h1>cOllect_Pay</h1>
        <p>Plateforme officielle de vérification sécurisée</p>
        <div class="badge">DOCUMENT AUTHENTIQUE</div>
    </div>

    <div class="content">
        <div class="doc-title">
            <h2><?= htmlspecialchars($typeLabel) ?></h2>
            <p>Numéro : <strong><?= htmlspecialchars($data['numero']) ?></strong></p>
        </div>

        <div class="grid">
            <div class="item">
                <span>Assujetti</span>
                <strong><?= htmlspecialchars(nomDocContribuable($data)) ?></strong>
            </div>

            <div class="item">
                <span>NIF</span>
                <strong><?= htmlspecialchars($data['nif'] ?? '-') ?></strong>
            </div>

            <?php if (!empty($data['numero_nt'])): ?>
            <div class="item">
                <span>Note de Taxation</span>
                <strong><?= htmlspecialchars($data['numero_nt']) ?></strong>
            </div>
            <?php endif; ?>

            <?php if (!empty($data['numero_nd'])): ?>
            <div class="item">
                <span>Note de Débit</span>
                <strong><?= htmlspecialchars($data['numero_nd']) ?></strong>
            </div>
            <?php endif; ?>

            <?php if (!empty($data['numero_np_mere'])): ?>
            <div class="item">
                <span>NP mère</span>
                <strong><?= htmlspecialchars($data['numero_np_mere']) ?></strong>
            </div>
            <?php endif; ?>

            <div class="item">
                <span>Montant</span>
                <strong class="amount"><?= number_format($data['montant'] ?? 0, 2, ',', ' ') ?> CDF</strong>
            </div>

            <?php if (isset($data['solde_restant'])): ?>
            <div class="item">
                <span>Solde restant</span>
                <strong><?= number_format($data['solde_restant'], 2, ',', ' ') ?> CDF</strong>
            </div>
            <?php endif; ?>

            <div class="item">
                <span>Statut</span>
                <strong><?= strtoupper(htmlspecialchars(str_replace('_',' ', $data['statut'] ?? '-'))) ?></strong>
            </div>

            <div class="item">
                <span>Date document</span>
                <strong><?= htmlspecialchars($data['created_at'] ?? '-') ?></strong>
            </div>

            <?php if (!empty($data['date_echeance'])): ?>
            <div class="item">
                <span>Date échéance</span>
                <strong><?= htmlspecialchars($data['date_echeance']) ?></strong>
            </div>
            <?php endif; ?>
        </div>

        <div class="warning">
            Ce document a été retrouvé dans le système et son QR Code est valide.
        </div>
    </div>

    <div class="footer">
        Vérification effectuée le <?= date('d/m/Y H:i:s') ?> — cOllect_Pay
    </div>
</div>

</body>
</html>