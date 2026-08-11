<?php
/*
|--------------------------------------------------------------------------
| cOllect_Pay - Génération quittance depuis apurement
|--------------------------------------------------------------------------
| Ajout formulaire réceptionniste + signature comptable.
|--------------------------------------------------------------------------
*/

require_once "../../auth/check_auth.php";
require_once "../../core/numero_generator.php";
require_once "../../core/secure_qr_engine.php";

checkAuth();

if (!function_exists('cpRecouvrementCurrentUserId')) {
    function cpRecouvrementCurrentUserId(PDO $pdo): int
    {
        $id = (int)(cpRecouvrementCurrentUserId($pdo) ?? 0);

        if ($id > 0) {
            return $id;
        }

        $email = trim((string)($_SESSION['email'] ?? $_SESSION['user_email'] ?? ''));

        if ($email !== '') {
            $stmtUser = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmtUser->execute([$email]);
            $rowUser = $stmtUser->fetch(PDO::FETCH_ASSOC);
            $id = (int)($rowUser['id'] ?? 0);

            if ($id > 0) {
                $_SESSION['user_id'] = $id;
                return $id;
            }
        }

        return 0;
    }
}

requirePermission('quittances', 'create');

$apurement_id = isset($_GET['apurement_id']) ? (int)$_GET['apurement_id'] : 0;
if ($apurement_id <= 0) die("ID apurement obligatoire.");

function ensureQuittanceSignatureColumns(PDO $pdo): void {
    $cols = array_column($pdo->query("SHOW COLUMNS FROM quittances")->fetchAll(PDO::FETCH_ASSOC), 'Field');
    $adds = [
        'nom_receptionniste' => "ALTER TABLE quittances ADD COLUMN nom_receptionniste VARCHAR(150) NULL",
        'fonction_receptionniste' => "ALTER TABLE quittances ADD COLUMN fonction_receptionniste VARCHAR(150) NULL",
        'observation_signature' => "ALTER TABLE quittances ADD COLUMN observation_signature TEXT NULL",
        'date_signature_receptionniste' => "ALTER TABLE quittances ADD COLUMN date_signature_receptionniste DATETIME NULL",
        'date_signature_comptable' => "ALTER TABLE quittances ADD COLUMN date_signature_comptable DATETIME NULL",
    ];
    foreach ($adds as $col=>$sql) {
        if (!in_array($col, $cols, true)) {
            try { $pdo->exec($sql); } catch(Throwable $e) {}
        }
    }
}
ensureQuittanceSignatureColumns($pdo);

$stmt = $pdo->prepare("
    SELECT ap.*, np.numero_np, np.type_np, np.np_mere_id, np.statut AS statut_np,
           np.solde_restant AS solde_np, np.montant_initial, np.montant_paye,
           nd.numero_nd, nt.numero_nt, nt.centre_id AS centre_id_taxation,
           c.raison_sociale, c.nom, c.postnom, c.prenom, c.nif
    FROM apurements ap
    JOIN notes_perception np ON ap.reference_id = np.id
    JOIN notes_debit nd ON np.note_debit_id = nd.id
    JOIN notes_taxation nt ON nd.note_taxation_id = nt.id
    JOIN contribuables c ON nt.contribuable_id = c.id
    WHERE ap.id = ?
    LIMIT 1
");
$stmt->execute([$apurement_id]);
$apurement = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$apurement) die("Apurement introuvable.");

$stmt = $pdo->prepare("SELECT numero_quittance FROM quittances WHERE apurement_id = ? LIMIT 1");
$stmt->execute([$apurement_id]);
$ancienne = $stmt->fetch(PDO::FETCH_ASSOC);
if ($ancienne) {
    header("Location: quittance_view.php?numero=" . urlencode($ancienne['numero_quittance']));
    exit;
}

if ((float)$apurement['solde_restant'] > 0.01 || ($apurement['statut'] ?? '') !== 'total') {
    die("Quittance refusée : la note n'est pas totalement apurée.");
}

if (($apurement['reference_type'] ?? '') === 'FRACTION') {
    $np_mere_id = (int)($apurement['np_mere_id'] ?? 0);
    if ($np_mere_id <= 0) die("Quittance finale impossible : NP mère introuvable.");

    header("Location: quittance_generate_finale.php?np_mere_id=" . urlencode($np_mere_id));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nomReceptionniste = trim($_POST['nom_receptionniste'] ?? '');
    $fonctionReceptionniste = trim($_POST['fonction_receptionniste'] ?? '');
    $observation = trim($_POST['observation_signature'] ?? '');

    if ($nomReceptionniste === '') die("Nom du réceptionniste obligatoire.");

    try {
        $centre_id = (int)($apurement['centre_id_taxation'] ?? 0);

        if ($centre_id <= 0) {
            die("Centre de taxation introuvable pour la quittance.");
        }

        $stmtCentre = $pdo->prepare("
            SELECT province_id
            FROM centres
            WHERE id = ?
            LIMIT 1
        ");
        $stmtCentre->execute([$centre_id]);
        $centreInfo = $stmtCentre->fetch(PDO::FETCH_ASSOC);

        $province_id = (int)($centreInfo['province_id'] ?? 0);

        if ($province_id <= 0) {
            die("Province liée au centre introuvable.");
        }

        $numero_qt = genererNumero('QT', $province_id, $centre_id, $pdo);
        $montant = (float)$apurement['montant_paye'];

        $token = getOrCreateDocumentToken($pdo, 'QUITTANCE', $numero_qt, $montant);
        $qr_data = buildEncryptedQrContent($pdo, 'QUITTANCE', $numero_qt, $montant);

        $stmt = $pdo->prepare("
            INSERT INTO quittances
            (
                numero_quittance, apurement_id, montant_acquitte,
                qr_hash, qr_data, user_comptable_id, penalite_recouvrement,
                nom_receptionniste, fonction_receptionniste, observation_signature,
                date_signature_receptionniste, date_signature_comptable
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $numero_qt,
            $apurement_id,
            $montant,
            $token,
            $qr_data,
            cpRecouvrementCurrentUserId($pdo),
            (float)($apurement['penalite_validee'] ?? 0),
            $nomReceptionniste,
            $fonctionReceptionniste,
            $observation
        ]);

        if (function_exists('auditLog')) {
            auditLog($pdo, cpRecouvrementCurrentUserId($pdo), "Quittance générée", "Recouvrement", $numero_qt, "Quittance générée pour apurement #".$apurement_id);
        }

        header("Location: quittance_view.php?numero=" . urlencode($numero_qt));
        exit;

    } catch (Exception $e) {
        die("Erreur génération quittance : " . $e->getMessage());
    }
}

function nomQg(array $c): string {
    return !empty($c['raison_sociale']) ? $c['raison_sociale'] : trim(($c['nom']??'').' '.($c['postnom']??'').' '.($c['prenom']??''));
}
function moneyQg($v): string { return number_format((float)$v,2,',',' ') . ' CDF'; }

$page_title = "Générer quittance";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($page_title) ?> | cOllect_Pay</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../../assets/css/admin.css">
<style>
.qt-hero{background:linear-gradient(135deg,#064e3b,#0f766e);color:#fff;padding:24px;border-radius:24px;margin-bottom:20px}
.qt-hero h2{margin:0;font-weight:1000}.qt-hero p{margin:7px 0 0;color:#ccfbf1;font-weight:800}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
label{font-weight:900;margin-top:12px;display:block}
input,textarea{width:100%;padding:12px;border:1px solid #d1d5db;border-radius:12px;font-weight:800}
textarea{min-height:100px}
.btn{background:#0f766e;color:white;border:none;padding:13px 18px;border-radius:14px;font-weight:1000;margin-top:18px;cursor:pointer}
.btn-gray{display:inline-block;background:#e5e7eb;color:#111827;text-decoration:none;padding:13px 18px;border-radius:14px;font-weight:1000;margin-top:18px}
@media(max-width:900px){.grid{grid-template-columns:1fr}}
</style>
<link rel="stylesheet" href="../../assets/css/recouvrement.css">
</head>
<body class="cp-recouvrement-page">
<div class="admin-layout">
<?php require_once "../../includes/sidebar.php"; ?>
<main class="main-content">
<?php require_once "../../includes/topbar.php"; ?>

<div class="qt-hero">
    <h2>Générer la quittance</h2>
    <p>Apurement total validé — la quittance sera signée par le comptable public et le réceptionniste.</p>
</div>

<div class="panel cp-rec-panel">
    <h3>Informations de l’apurement</h3>
    <table class="table-premium cp-rec-table">
        <tr><th>NP / NPF</th><td><?= htmlspecialchars($apurement['numero_np']) ?></td></tr>
        <tr><th>ND / NT</th><td><?= htmlspecialchars($apurement['numero_nd'].' / '.$apurement['numero_nt']) ?></td></tr>
        <tr><th>Contribuable</th><td><?= htmlspecialchars(nomQg($apurement)) ?></td></tr>
        <tr><th>Montant apuré</th><td><strong><?= moneyQg($apurement['montant_paye']) ?></strong></td></tr>
    </table>
</div>

<div class="panel cp-rec-panel">
    <h3>Signature réceptionniste / bénéficiaire</h3>
    <form method="POST">
        <div class="grid">
            <div>
                <label>Nom du réceptionniste *</label>
                <input type="text" name="nom_receptionniste" required placeholder="Ex: BOSMIL MENDE Josephe">
            </div>
            <div>
                <label>Fonction / Qualité</label>
                <input type="text" name="fonction_receptionniste" placeholder="Ex: Réceptionniste, bénéficiaire, assujetti...">
            </div>
        </div>

        <label>Observation</label>
        <textarea name="observation_signature" placeholder="Observation éventuelle..."></textarea>

        <button type="submit" class="btn">Générer la quittance</button>
        <a href="apurement_list.php" class="btn-gray">Retour</a>
    </form>
</div>

</main>
</div>
</body>
</html>
