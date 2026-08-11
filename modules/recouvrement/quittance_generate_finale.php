<?php
/*
|--------------------------------------------------------------------------
| cOllect_Pay - Quittance finale NP fractionnée
|--------------------------------------------------------------------------
*/

require_once "../../auth/check_auth.php";
require_once "../../core/numero_generator.php";
require_once "../../core/secure_qr_engine.php";

checkAuth();

if (!function_exists('cpRecouvrementCurrentUserId')) {
    function cpRecouvrementCurrentUserId(PDO $pdo): int
    {
        $id = (int)($_SESSION['user_id'] ?? 0);

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

$np_mere_id = isset($_GET['np_mere_id']) ? (int)$_GET['np_mere_id'] : 0;
if ($np_mere_id <= 0) die("NP mère obligatoire.");

function ensureQuittanceFinaleColumns(PDO $pdo): void {
    $cols = array_column($pdo->query("SHOW COLUMNS FROM quittances")->fetchAll(PDO::FETCH_ASSOC), 'Field');
    $adds = [
        'nom_receptionniste' => "ALTER TABLE quittances ADD COLUMN nom_receptionniste VARCHAR(150) NULL",
        'fonction_receptionniste' => "ALTER TABLE quittances ADD COLUMN fonction_receptionniste VARCHAR(150) NULL",
        'observation_signature' => "ALTER TABLE quittances ADD COLUMN observation_signature TEXT NULL",
        'date_signature_receptionniste' => "ALTER TABLE quittances ADD COLUMN date_signature_receptionniste DATETIME NULL",
        'date_signature_comptable' => "ALTER TABLE quittances ADD COLUMN date_signature_comptable DATETIME NULL",
    ];
    foreach ($adds as $col=>$sql) if (!in_array($col,$cols,true)) { try{$pdo->exec($sql);}catch(Throwable $e){} }
}
ensureQuittanceFinaleColumns($pdo);

$stmt = $pdo->prepare("SELECT * FROM notes_perception WHERE id=? AND type_np='globale' LIMIT 1");
$stmt->execute([$np_mere_id]);
$mere = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$mere) die("NP mère introuvable.");

$stmt = $pdo->prepare("
    SELECT COUNT(*) total_fractions,
           SUM(CASE WHEN statut='payee' THEN 1 ELSE 0 END) fractions_payees,
           IFNULL(SUM(montant_paye),0) total_paye,
           IFNULL(SUM(solde_restant),0) total_solde
    FROM notes_perception
    WHERE np_mere_id=? AND type_np='fractionnee'
");
$stmt->execute([$np_mere_id]);
$sync = $stmt->fetch(PDO::FETCH_ASSOC);

if ((int)$sync['total_fractions'] <= 0) die("Aucune fraction trouvée pour cette NP mère.");
if ((int)$sync['fractions_payees'] !== (int)$sync['total_fractions'] || (float)$sync['total_solde'] > 0.01) {
    die("Impossible : toutes les fractions ne sont pas totalement payées.");
}
$totalPaye = (float)$sync['total_paye'];

$pdo->beginTransaction();
$stmt = $pdo->prepare("UPDATE notes_perception SET montant_paye=?, solde_restant=0, statut='payee' WHERE id=?");
$stmt->execute([$totalPaye,$np_mere_id]);

$stmt = $pdo->prepare("SELECT id FROM apurements WHERE reference_type='NP' AND reference_id=? LIMIT 1");
$stmt->execute([$np_mere_id]);
$ap = $stmt->fetch(PDO::FETCH_ASSOC);

if ($ap) {
    $apurement_id = (int)$ap['id'];
    $stmt = $pdo->prepare("UPDATE apurements SET montant_du=?, montant_paye=?, solde_restant=0, statut='total', date_apurement=CURDATE(), user_apurement_id=? WHERE id=?");
    $stmt->execute([(float)$mere['montant_initial'],$totalPaye,cpRecouvrementCurrentUserId($pdo)??null,$apurement_id]);
} else {
    $stmt = $pdo->prepare("INSERT INTO apurements(reference_type,reference_id,montant_du,montant_paye,penalite_validee,solde_restant,statut,date_apurement,user_apurement_id) VALUES('NP',?,?,?,0,0,'total',CURDATE(),?)");
    $stmt->execute([$np_mere_id,(float)$mere['montant_initial'],$totalPaye,cpRecouvrementCurrentUserId($pdo)??null]);
    $apurement_id = (int)$pdo->lastInsertId();
}

$stmt = $pdo->prepare("SELECT numero_quittance FROM quittances WHERE apurement_id=? LIMIT 1");
$stmt->execute([$apurement_id]);
$old = $stmt->fetch(PDO::FETCH_ASSOC);
$pdo->commit();

if ($old) {
    header("Location: quittance_view.php?numero=".urlencode($old['numero_quittance']));
    exit;
}

header("Location: quittance_generate.php?apurement_id=".$apurement_id);
exit;
