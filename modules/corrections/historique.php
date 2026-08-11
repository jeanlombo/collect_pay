<?php
require_once "../../auth/check_auth.php";

checkAuth();
requirePermission('corrections','history');

$page_title = "Historique des corrections";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$rows = [];

try {
    if ($pdo->query("SHOW TABLES LIKE 'corrections_documents'")->fetch()) {
        if ($id > 0) {
            $stmt = $pdo->prepare("SELECT * FROM corrections_documents WHERE id=? LIMIT 1");
            $stmt->execute([$id]);
            $one = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($one) $rows = [$one];
        } else {
            $rows = $pdo->query("
                SELECT *
                FROM corrections_documents
                ORDER BY date_modification DESC, id DESC
                LIMIT 500
            ")->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (Throwable $e) {
    $rows = [];
}

function prettyCorr($v) {
    if (!$v) return '-';
    $decoded = json_decode($v, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    return $v;
}
?>
<!doctype html>
<html lang="fr">
<head>
<title><?= htmlspecialchars($page_title) ?> | cOllect_Pay</title>

<meta charset="UTF-8">
<link rel="stylesheet" href="../../assets/css/admin.css">
<style>
.hero{background:linear-gradient(135deg,#06152b,#0f3460);color:white;padding:24px;border-radius:24px;margin-bottom:20px}
.hero h2{margin:0;font-weight:1000}
.hero p{margin:8px 0 0;color:#dbeafe;font-weight:800}
.badge{display:inline-block;padding:6px 10px;border-radius:999px;font-weight:900;font-size:12px}
.badge-green{background:#dcfce7;color:#166534}
.badge-blue{background:#dbeafe;color:#1e40af}
.btn{display:inline-block;background:#0f3460;color:white;padding:8px 12px;border-radius:12px;text-decoration:none;font-weight:900;margin:2px}
.btn-gray{background:#e5e7eb;color:#111827}
pre{white-space:pre-wrap;background:#f8fafc;border:1px solid #e5e7eb;border-radius:12px;padding:10px;font-size:12px;max-width:520px}
</style>

<link rel="stylesheet" href="../../assets/css/corrections.css">
</head>
<body class="cp-corrections-page">
<div class="admin-layout">
<?php require_once "../../includes/sidebar.php"; ?>

<main class="main-content">
<?php require_once "../../includes/topbar.php"; ?>

<div class="hero">
    <h2>Historique des corrections</h2>
    <p>Traçabilité complète : ancienne valeur, nouvelle valeur, utilisateur et date.</p>
</div>

<div class="panel cp-corrections-panel">
    <a class="btn" href="correction_create.php">Nouvelle correction</a>
    <a class="btn btn-gray" href="documents_corriges.php">Documents corrigés</a>
    <a class="btn btn-gray" href="corrections_list.php">Liste corrections</a>
</div>

<div class="panel cp-corrections-panel">
<table class="table-premium cp-corrections-table">
<tr>
    <th>ID</th>
    <th>Document</th>
    <th>Type</th>
    <th>Raison</th>
    <th>Ancienne valeur</th>
    <th>Nouvelle valeur</th>
    <th>Utilisateur</th>
    <th>Date</th>
</tr>

<?php foreach($rows as $r): ?>
<tr>
    <td><?= htmlspecialchars($r['id'] ?? '-') ?></td>
    <td><strong><?= htmlspecialchars($r['numero_document'] ?? '-') ?></strong></td>
    <td><?= htmlspecialchars($r['type_document'] ?? '-') ?></td>
    <td><?= htmlspecialchars($r['raison_modification'] ?? $r['motif'] ?? '-') ?></td>
    <td><pre><?= htmlspecialchars(prettyCorr($r['ancienne_valeur'] ?? '')) ?></pre></td>
    <td><pre><?= htmlspecialchars(prettyCorr($r['nouvelle_valeur'] ?? '')) ?></pre></td>
    <td><?= htmlspecialchars($r['user_id'] ?? '-') ?></td>
    <td><?= htmlspecialchars($r['date_modification'] ?? '-') ?></td>
</tr>
<?php endforeach; ?>

<?php if(empty($rows)): ?>
<tr><td colspan="8">Aucun historique disponible.</td></tr>
<?php endif; ?>
</table>
</div>

</main>
</div>
</body>
</html>
