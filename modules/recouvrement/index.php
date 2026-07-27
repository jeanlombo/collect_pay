<?php
require_once "../../auth/check_auth.php";
checkAuth();
requirePermission('recouvrement', 'view');
$page_title = "Vue Recouvrement";
function countSafe(PDO $pdo, string $sql): int { try { $r=$pdo->query($sql)->fetch(PDO::FETCH_ASSOC); return (int)($r['total']??0); } catch(Throwable $e){ return 0; } }
$totalNP=countSafe($pdo,"SELECT COUNT(*) total FROM notes_perception");
$totalAMR=countSafe($pdo,"SELECT COUNT(*) total FROM amr");
$totalAp=countSafe($pdo,"SELECT COUNT(*) total FROM apurements");
$totalQt=countSafe($pdo,"SELECT COUNT(*) total FROM quittances");
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><title><?= htmlspecialchars($page_title) ?> | cOllect_Pay</title><link rel="stylesheet" href="/collect_pay/assets/css/admin.css">
<style>.cards{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}.cardx{background:white;border:1px solid #e5e7eb;border-radius:18px;padding:20px;box-shadow:0 10px 25px rgba(0,0,0,.07)}.cardx small{font-weight:900;color:#64748b}.cardx strong{display:block;font-size:28px;color:#0f3460;margin-top:8px}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:20px}.actions a{background:#0f3460;color:white;text-decoration:none;padding:12px 16px;border-radius:12px;font-weight:900}@media(max-width:900px){.cards{grid-template-columns:1fr}}</style></head>
<body><div class="admin-layout"><?php require_once "../../includes/sidebar.php"; ?><main class="main-content"><?php require_once "../../includes/topbar.php"; ?>
<div class="panel"><h2>Vue Recouvrement</h2><p>Suivi synthétique du recouvrement, des AMR, apurements et quittances.</p>
<div class="cards"><div class="cardx"><small>NP / NPF</small><strong><?= $totalNP ?></strong></div><div class="cardx"><small>AMR</small><strong><?= $totalAMR ?></strong></div><div class="cardx"><small>Apurements</small><strong><?= $totalAp ?></strong></div><div class="cardx"><small>Quittances</small><strong><?= $totalQt ?></strong></div></div>
<div class="actions"><a href="/collect_pay/modules/recouvrement/amr_list.php">Liste AMR</a><a href="/collect_pay/modules/recouvrement/amr_create.php">Créer AMR</a><a href="/collect_pay/modules/recouvrement/paiement_list.php">Paiements</a><a href="/collect_pay/modules/recouvrement/apurement_list.php">Apurements</a><a href="/collect_pay/modules/recouvrement/quittance_list.php">Quittances</a></div>
</div></main></div></body></html>