<?php
require_once "_rapport_bootstrap.php";
$f=cpRapportFilters();
$where=["DATE(q.date_emission)>=?","DATE(q.date_emission)<=?"];$params=[$f['date_debut'],$f['date_fin']];
$whereSql="WHERE ".implode(" AND ",$where);
$sql="
SELECT q.*,a.reference_type,a.reference_id,a.montant_du,a.montant_paye,a.penalite_validee,a.statut statut_apurement,
 CASE a.reference_type WHEN 'NP' THEN np.numero_np WHEN 'FRACTION' THEN fr.numero_fraction END reference_document,
 u.nom comptable
FROM quittances q
JOIN apurements a ON q.apurement_id=a.id
LEFT JOIN notes_perception np ON a.reference_type='NP' AND a.reference_id=np.id
LEFT JOIN notes_perception_fractions fr ON a.reference_type='FRACTION' AND a.reference_id=fr.id
LEFT JOIN users u ON q.user_comptable_id=u.id
{$whereSql}
ORDER BY q.date_emission DESC,q.id DESC
";
$stmt=$pdo->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
$total=0;foreach($rows as $r)$total+=(float)$r['montant_acquitte'];

cpRapportPageStart("Rapport des quittances","Quittances émises après apurement des créances.");
?>
<section class="rp-panel"><form method="GET" class="rp-search-form"><label>Du<input type="date" name="date_debut" value="<?=cpRapportH($f['date_debut'])?>"></label><label>Au<input type="date" name="date_fin" value="<?=cpRapportH($f['date_fin'])?>"></label><button>Filtrer</button></form></section>
<section class="rp-kpis"><article><small>Quittances</small><strong><?=count($rows)?></strong></article><article><small>Montant acquitté</small><strong><?=cpRapportMoney($total)?></strong></article></section>
<section class="rp-panel"><div class="rp-table-wrap"><table class="rp-table"><thead><tr><th>Date</th><th>Quittance</th><th>Document</th><th>Montant acquitté</th><th>Pénalité assiette</th><th>Pénalité recouvrement</th><th>Apurement</th><th>Comptable</th><th>PDF</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><?=cpRapportDate($r['date_emission'])?></td><td><b><?=cpRapportH($r['numero_quittance'])?></b></td><td><?=cpRapportH($r['reference_document']?:$r['reference_type'].'#'.$r['reference_id'])?></td><td><?=cpRapportMoney($r['montant_acquitte'])?></td><td><?=cpRapportMoney($r['penalite_assiette'])?></td><td><?=cpRapportMoney($r['penalite_recouvrement'])?></td><td><?=cpRapportStatusBadge($r['statut_apurement'])?></td><td><?=cpRapportH($r['comptable']??'-')?></td><td><a class="rp-link" href="quittance_pdf.php?numero=<?=urlencode($r['numero_quittance'])?>" target="_blank">Ouvrir</a></td></tr><?php endforeach;?><?php if(!$rows):?><tr><td colspan="9" class="rp-empty">Aucune quittance.</td></tr><?php endif;?></tbody></table></div></section>
<?php cpRapportPageEnd(); ?>
