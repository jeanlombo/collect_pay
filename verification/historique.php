<?php
require_once __DIR__ . "/../auth/check_auth.php"; checkAuth(); requirePermission('inspection','verify');
$page_title="Historique vérifications"; require_once __DIR__."/header.php";
$rows=[]; try{ vCreateLog($pdo); $rows=$pdo->query("SELECT * FROM verification_logs ORDER BY id DESC LIMIT 500")->fetchAll(PDO::FETCH_ASSOC); }catch(Throwable $e){}
?>
<div class="card"><h2>Historique des vérifications</h2><table class="table"><tr><th>Référence</th><th>Document</th><th>Type</th><th>Résultat</th><th>IP</th><th>Date</th></tr><?php foreach($rows as $r): ?><tr><td><?= vSafe($r['reference_verification']??'-') ?></td><td><?= vSafe($r['numero_document']??'-') ?></td><td><?= vSafe($r['type_document']??'-') ?></td><td><?= vSafe($r['resultat']??'-') ?></td><td><?= vSafe($r['ip_address']??'-') ?></td><td><?= vSafe($r['created_at']??'-') ?></td></tr><?php endforeach; ?><?php if(empty($rows)): ?><tr><td colspan="6">Aucune vérification enregistrée.</td></tr><?php endif; ?></table></div>
<?php require_once __DIR__."/footer.php"; ?>