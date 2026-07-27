<?php
require_once "../../auth/check_auth.php";
checkAuth();
requirePermission('corrections','view');
$page_title="Liste des corrections";
$rows=[];
try{
 if($pdo->query("SHOW TABLES LIKE 'corrections_documents'")->fetch()){
  $rows=$pdo->query("SELECT * FROM corrections_documents ORDER BY id DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);
 }
}catch(Throwable $e){}
?>
<!doctype html><html lang="fr"><head><title><?= htmlspecialchars($page_title) ?> | cOllect_Pay</title><meta charset="UTF-8">
<link rel="stylesheet" href="/collect_pay/assets/css/admin.css">
<style>
.hero{background:linear-gradient(135deg,#06152b,#0f3460);color:white;padding:24px;border-radius:24px;margin-bottom:20px}
.hero h2{margin:0;font-weight:1000}.hero p{margin:8px 0 0;color:#dbeafe;font-weight:800}
.btn{display:inline-block;background:#0f3460;color:white;padding:9px 13px;border-radius:12px;text-decoration:none;font-weight:900;margin:3px;border:0}
.btn-gray{background:#e5e7eb;color:#111827}
input,select,textarea{width:100%;padding:12px;border:1px solid #d1d5db;border-radius:12px;font-weight:800}
label{display:block;font-weight:900;margin-top:10px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.alert{background:#fee2e2;color:#991b1b;padding:12px;border-radius:12px;font-weight:900}
@media(max-width:850px){.grid{grid-template-columns:1fr}}
</style></head>
<body><div class="admin-layout"><?php require_once "../../includes/sidebar.php"; ?><main class="main-content"><?php require_once "../../includes/topbar.php"; ?>
<div class="hero"><h2>Liste des corrections</h2><p>Suivi des demandes de correction des documents.</p></div>
<div class="panel"><a class="btn" href="correction_create.php">Nouvelle correction</a><a class="btn btn-gray" href="documents_corriges.php">Documents corrigés</a><a class="btn btn-gray" href="historique.php">Historique</a></div>
<div class="panel"><table class="table-premium"><tr><th>ID</th><th>Document</th><th>Type</th><th>Motif</th><th>Statut</th><th>Date</th></tr>
<?php foreach($rows as $r): ?><tr><td><?= htmlspecialchars($r['id']??'-') ?></td><td><strong><?= htmlspecialchars($r['numero_document']??'-') ?></strong></td><td><?= htmlspecialchars($r['type_document']??'-') ?></td><td><?= htmlspecialchars($r['motif']??'-') ?></td><td><?= strtoupper(htmlspecialchars($r['statut']??'en_attente')) ?></td><td><?= htmlspecialchars($r['created_at']??'-') ?></td></tr><?php endforeach; ?>
<?php if(empty($rows)): ?><tr><td colspan="6">Aucune correction enregistrée.</td></tr><?php endif; ?></table></div>
</main></div></body></html>