<?php
/*
|--------------------------------------------------------------------------
| cOllect_Pay - Journal des Vérifications QR
|--------------------------------------------------------------------------
| Fichier : modules/inspection/verifications.php
|--------------------------------------------------------------------------
*/

require_once "../../auth/check_auth.php";

checkAuth();

if (function_exists('canDo')) {
    if (!canDo('inspection', 'verify') && !canDo('inspection', 'view')) {
}
}

$page_title = "Journal des vérifications";

$search = trim($_GET['search'] ?? '');

$rows = [];

try {
    /*
    |--------------------------------------------------------------------------
    | Détection automatique d'une table de vérification existante
    |--------------------------------------------------------------------------
    */
    $tables = [];
    $stmt = $pdo->query("SHOW TABLES");
    while ($t = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $t[0];
    }

    $table = null;

    foreach ([
        'verifications_qr',
        'qr_verifications',
        'verification_qr',
        'journal_verifications',
        'logs_verification',
        'audit_logs'
    ] as $candidate) {
        if (in_array($candidate, $tables, true)) {
            $table = $candidate;
            break;
        }
    }

    if ($table) {
        $cols = array_column(
            $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC),
            'Field'
        );

        $dateCol = in_array('created_at', $cols, true) ? 'created_at' :
            (in_array('date_verification', $cols, true) ? 'date_verification' :
            (in_array('date_action', $cols, true) ? 'date_action' : 'id'));

        $sql = "SELECT * FROM `$table` WHERE 1=1";
        $params = [];

        if ($search !== '') {
            $likeParts = [];
            foreach ($cols as $c) {
                if (stripos($c, 'numero') !== false || stripos($c, 'document') !== false || stripos($c, 'qr') !== false || stripos($c, 'action') !== false || stripos($c, 'result') !== false || stripos($c, 'statut') !== false) {
                    $likeParts[] = "`$c` LIKE ?";
                    $params[] = "%$search%";
                }
            }

            if (!empty($likeParts)) {
                $sql .= " AND (" . implode(" OR ", $likeParts) . ")";
            }
        }

        $sql .= " ORDER BY `$dateCol` DESC LIMIT 200";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (Throwable $e) {
    $rows = [];
}

function cellValueVerification($row, array $keys, $default = '-')
{
    foreach ($keys as $k) {
        if (isset($row[$k]) && $row[$k] !== '') {
            return $row[$k];
        }
    }

    return $default;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($page_title) ?> | cOllect_Pay</title>
<link rel="stylesheet" href="../../assets/css/admin.css">

<style>
.hero-verif{
    background:linear-gradient(135deg,#06152b,#0f3460);
    color:white;
    padding:24px;
    border-radius:24px;
    margin-bottom:20px;
}
.hero-verif h2{
    margin:0;
    font-weight:1000;
}
.hero-verif p{
    margin:8px 0 0;
    color:#dbeafe;
    font-weight:800;
}
.filter-form{
    display:grid;
    grid-template-columns:1fr auto;
    gap:12px;
}
.filter-form input{
    padding:12px 14px;
    border:1px solid #d1d5db;
    border-radius:12px;
    font-weight:800;
}
.filter-form button{
    background:#0f3460;
    color:white;
    border:none;
    border-radius:12px;
    padding:12px 18px;
    font-weight:900;
    cursor:pointer;
}
.badge-ok{
    display:inline-block;
    background:#dcfce7;
    color:#166534;
    padding:6px 10px;
    border-radius:999px;
    font-weight:900;
    font-size:12px;
}
.badge-warn{
    display:inline-block;
    background:#ffedd5;
    color:#9a3412;
    padding:6px 10px;
    border-radius:999px;
    font-weight:900;
    font-size:12px;
}
@media(max-width:800px){
    .filter-form{grid-template-columns:1fr;}
}
</style>
<link rel="stylesheet" href="../../assets/css/inspection.css">
</head>

<body class="cp-inspection-page">
<div class="admin-layout">

<?php require_once "../../includes/sidebar.php"; ?>

<main class="main-content">
<?php require_once "../../includes/topbar.php"; ?>

<div class="hero-verif">
    <h2>Journal des vérifications QR</h2>
    <p>Consultation des contrôles effectués sur les documents sécurisés par QR Code.</p>
</div>

<div class="panel cp-inspection-panel">
    <form method="GET" class="filter-form">
        <input type="text" name="search" placeholder="Rechercher numéro document, QR Code, statut..."
               value="<?= htmlspecialchars($search) ?>">
        <button type="submit">Rechercher</button>
    </form>
</div>

<div class="panel cp-inspection-panel">
    <table class="table-premium cp-inspection-table">
        <tr>
            <th>Date</th>
            <th>Document</th>
            <th>Type</th>
            <th>Résultat</th>
            <th>Utilisateur</th>
            <th>IP / Terminal</th>
        </tr>

        <?php foreach ($rows as $r): ?>
            <?php
                $date = cellValueVerification($r, ['created_at','date_verification','date_action','date_scan']);
                $document = cellValueVerification($r, ['numero_document','document_numero','numero','reference','numero_piece','qr_hash']);
                $type = cellValueVerification($r, ['type_document','document_type','module','type']);
                $result = cellValueVerification($r, ['resultat','result','statut','status','action']);
                $user = cellValueVerification($r, ['user_id','utilisateur','nom_user','agent']);
                $ip = cellValueVerification($r, ['adresse_ip','ip_inspecteur','ip','ip_address','terminal','device','user_agent']);
            ?>
            <tr>
                <td><?= htmlspecialchars($date) ?></td>
                <td><strong><?= htmlspecialchars($document) ?></strong></td>
                <td><?= htmlspecialchars($type) ?></td>
                <td>
                    <?php if (in_array(strtolower(trim((string)$result)), ['valide','authentique','ok'], true)): ?>
                        <span class="badge-ok"><?= htmlspecialchars($result) ?></span>
                    <?php else: ?>
                        <span class="badge-warn"><?= htmlspecialchars($result) ?></span>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($user) ?></td>
                <td><?= htmlspecialchars($ip) ?></td>
            </tr>
        <?php endforeach; ?>

        <?php if (empty($rows)): ?>
            <tr>
                <td colspan="6">
                    Aucun journal de vérification trouvé.  
                    Si le module QR fonctionne déjà, il faudra seulement me donner le nom exact de la table où les vérifications sont enregistrées.
                </td>
            </tr>
        <?php endif; ?>
    </table>
</div>

</main>
</div>
</body>
</html>
