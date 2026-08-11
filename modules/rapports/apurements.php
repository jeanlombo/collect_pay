<?php
require_once "_rapport_bootstrap.php";
$f=cpRapportFilters();
$devise=trim((string)($_GET['devise']??'toutes'));
$where=["DATE(a.date_apurement)>=?","DATE(a.date_apurement)<=?"];$params=[$f['date_debut'],$f['date_fin']];
if($devise!=='toutes'){$where[]="p.devise=?";$params[]=$devise;}
$whereSql="WHERE ".implode(" AND ",$where);
$sql="
SELECT a.id,a.reference_type,a.reference_id,a.montant_du,a.montant_paye,a.penalite_validee,a.solde_restant,a.statut,a.date_apurement,
 CASE a.reference_type WHEN 'NP' THEN np.numero_np WHEN 'FRACTION' THEN fr.numero_fraction END numero_document,
 p.devise,
 SUM(CASE WHEN p.statut<>'annule' THEN p.montant_paye ELSE 0 END) montant_devise,
 SUM(CASE WHEN p.statut<>'annule' THEN p.montant_converti_cdf ELSE 0 END) montant_cdf,
 COUNT(CASE WHEN p.statut<>'annule' THEN p.id END) nb_paiements
FROM apurements a
LEFT JOIN notes_perception np ON a.reference_type='NP' AND a.reference_id=np.id
LEFT JOIN notes_perception_fractions fr ON a.reference_type='FRACTION' AND a.reference_id=fr.id
LEFT JOIN paiements p ON (
 (a.reference_type='NP' AND p.note_perception_id=a.reference_id)
 OR (a.reference_type='FRACTION' AND p.fraction_id=a.reference_id)
)
{$whereSql}
GROUP BY a.id,p.devise
ORDER BY a.date_apurement DESC,a.id DESC,p.devise
";
$stmt=$pdo->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
$totals=[];$totalCDF=0;foreach($rows as $r){$d=$r['devise']?:'N/A';$totals[$d]=($totals[$d]??0)+(float)$r['montant_devise'];$totalCDF+=(float)$r['montant_cdf'];}
cpRapportPageStart("Rapport — Apurements par devise","Apurements et paiements associés ventilés en CDF, USD et EUR.");
?>
<section class="rp-panel"><form method="GET" class="rp-search-form"><label>Du<input type="date" name="date_debut" value="<?=cpRapportH($f['date_debut'])?>"></label><label>Au<input type="date" name="date_fin" value="<?=cpRapportH($f['date_fin'])?>"></label><label>Devise<select name="devise"><option value="toutes">Toutes</option><?php foreach(['CDF','USD','EUR'] as $d):?><option value="<?=$d?>" <?=$devise===$d?'selected':''?>><?=$d?></option><?php endforeach;?></select></label><button>Filtrer</button></form></section>
<section class="rp-kpis"><article><small>Lignes d’apurement</small><strong><?=count($rows)?></strong></article><article><small>Total converti CDF</small><strong><?=cpRapportMoney($totalCDF)?></strong></article><?php foreach($totals as $d=>$v):?><article><small>Total <?=$d?></small><strong><?=cpRapportMoney($v,$d)?></strong></article><?php endforeach;?></section>
<section class="rp-panel"><div class="rp-table-wrap"><table class="rp-table"><thead><tr><th>Date</th><th>Document</th><th>Type</th><th>Devise</th><th>Montant devise</th><th>Équivalent CDF</th><th>Paiements</th><th>Montant dû</th><th>Solde</th><th>Apurement</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><?=cpRapportDate($r['date_apurement'])?></td><td><b><?=cpRapportH($r['numero_document']?:$r['reference_type'].'#'.$r['reference_id'])?></b></td><td><?=cpRapportH($r['reference_type'])?></td><td><?=cpRapportH($r['devise']?:'-')?></td><td><?=cpRapportMoney($r['montant_devise'],$r['devise']?:'')?></td><td><?=cpRapportMoney($r['montant_cdf'])?></td><td><?=$r['nb_paiements']?></td><td><?=cpRapportMoney($r['montant_du'])?></td><td><?=cpRapportMoney($r['solde_restant'])?></td><td><?=cpRapportStatusBadge($r['statut'])?></td></tr><?php endforeach;?><?php if(!$rows):?><tr><td colspan="10" class="rp-empty">Aucun apurement.</td></tr><?php endif;?></tbody></table></div></section>
<?php cpRapportPageEnd(); ?>
