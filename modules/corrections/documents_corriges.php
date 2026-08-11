<?php
require_once "../../auth/check_auth.php";

checkAuth();
requirePermission('corrections','view');

$page_title = "Documents corrigés";

$rows = [];

try {
    if ($pdo->query("SHOW TABLES LIKE 'corrections_documents'")->fetch()) {
        $rows = $pdo->query("
            SELECT *
            FROM corrections_documents
            ORDER BY date_modification DESC, id DESC
            LIMIT 300
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    $rows = [];
}

function shortJsonCorr($v) {
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
    <h2>Documents corrigés</h2>
    <p>Liste des documents ayant fait l’objet d’une correction administrative.</p>
</div>

<div class="panel cp-corrections-panel">
    <a class="btn" href="correction_create.php">Nouvelle correction</a>
    <a class="btn btn-gray" href="corrections_list.php">Liste des corrections</a>
    <a class="btn btn-gray" href="historique.php">Historique</a>
</div>

<div class="panel cp-corrections-panel">
<table class="table-premium cp-corrections-table">
<tr>
    <th>ID</th>
    <th>Document</th>
    <th>Type</th>
    <th>Raison</th>
    <th>Statut</th>
    <th>Date correction</th>
    <th>Action</th>
</tr>

<?php foreach($rows as $r): ?>
<tr>
    <td><?= htmlspecialchars($r['id'] ?? '-') ?></td>
    <td><strong><?= htmlspecialchars($r['numero_document'] ?? '-') ?></strong></td>
    <td><?= htmlspecialchars($r['type_document'] ?? '-') ?></td>
    <td><?= htmlspecialchars($r['raison_modification'] ?? $r['motif'] ?? '-') ?></td>
    <td><span class="badge badge-green">CORRIGÉ</span></td>
    <td><?= htmlspecialchars($r['date_modification'] ?? '-') ?></td>
    <td>
        <a class="btn" href="historique.php?id=<?= (int)($r['id'] ?? 0) ?>">Détails</a>
    </td>
</tr>
<?php endforeach; ?>

<?php if(empty($rows)): ?>
<tr><td colspan="7">Aucun document corrigé.</td></tr>
<?php endif; ?>
</table>
</div>

</main>
</div>
</body>
</html>
