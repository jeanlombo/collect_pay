<?php
require_once "_rapport_bootstrap.php";
$f=cpRapportFilters();$c=cpRapportCatalogues($pdo);
$etat=trim((string)($_GET['etat']??'tous'));
[$where,$params]=cpRapportScopeWhere($f,"COALESCE(np.date_emission,np.created_at)");
if($etat==='impayees')$where[]="np.statut IN ('en_attente','non_payee','defaillante')";
elseif($etat==='partielles')$where[]="np.statut='partiellement_payee'";
elseif($etat==='retard')$where[]="np.date_echeance<CURDATE() AND np.statut<>'payee'";
$whereSql=$where?"WHERE ".implode(" AND ",$where):"";

$paySub="SELECT note_perception_id,SUM(CASE WHEN statut<>'annule' THEN montant_converti_cdf ELSE 0 END) total_paye FROM paiements WHERE note_perception_id IS NOT NULL GROUP BY note_perception_id";
$sql="
SELECT np.id,np.numero_np,np.type_np,np.statut,np.date_echeance,
 COALESCE(np.date_emission,np.created_at) date_emission,
 COALESCE(NULLIF(np.montant_initial,0),np.montant_total,0) montant_du,
 COALESCE(pp.total_paye,0) montant_paye,
 ".cpRapportNomContribuableSql("ct")." contribuable,ct.nif,
 pr.nom province,ce.nom centre,s.nom_service,
 DATEDIFF(CURDATE(),np.date_echeance) jours_retard
FROM notes_perception np
JOIN notes_debit nd ON np.note_debit_id=nd.id
JOIN notes_taxation nt ON nd.note_taxation_id=nt.id
JOIN contribuables ct ON nt.contribuable_id=ct.id
JOIN centres ce ON nt.centre_id=ce.id
JOIN provinces pr ON ce.province_id=pr.id
LEFT JOIN services_assiette s ON nt.service_id=s.id
LEFT JOIN directions d ON s.direction_id=d.id
LEFT JOIN ({$paySub}) pp ON pp.note_perception_id=np.id
{$whereSql}
ORDER BY (np.date_echeance<CURDATE() AND np.statut<>'payee') DESC,np.date_echeance ASC
";
$stmt=$pdo->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
$du=$pa=0;$retard=0;
foreach($rows as $r){$du+=(float)$r['montant_du'];$pa+=(float)$r['montant_paye'];if((int)$r['jours_retard']>0 && $r['statut']!=='payee')$retard++;}
$solde=max(0,$du-$pa);

cpRapportPageStart("Rapport de recouvrement","Créances, échéances, retards et soldes restant à recouvrer.");
?>
<section class="rp-panel"><form method="GET" class="rp-filter-grid"><label>État<select name="etat"><option value="tous" <?=$etat==='tous'?'selected':''?>>Toutes</option><option value="impayees" <?=$etat==='impayees'?'selected':''?>>Impayées</option><option value="partielles" <?=$etat==='partielles'?'selected':''?>>Partielles</option><option value="retard" <?=$etat==='retard'?'selected':''?>>En retard</option></select></label><label>Du<input type="date" name="date_debut" value="<?=cpRapportH($f['date_debut'])?>"></label><label>Au<input type="date" name="date_fin" value="<?=cpRapportH($f['date_fin'])?>"></label><label>Province<select name="province_id"><option value="0">Toutes</option><?php foreach($c['provinces'] as $x):?><option value="<?=$x['id']?>" <?=$f['province_id']==$x['id']?'selected':''?>><?=cpRapportH($x['nom'])?></option><?php endforeach;?></select></label><label>Centre<select name="centre_id"><option value="0">Tous</option><?php foreach($c['centres'] as $x):?><option value="<?=$x['id']?>" <?=$f['centre_id']==$x['id']?'selected':''?>><?=cpRapportH($x['nom'])?></option><?php endforeach;?></select></label><div class="rp-filter-action"><button>Filtrer</button></div></form></section>
<section class="rp-kpis"><article><small>Créances</small><strong><?=count($rows)?></strong></article><article><small>Total dû</small><strong><?=cpRapportMoney($du)?></strong></article><article><small>Recouvré</small><strong><?=cpRapportMoney($pa)?></strong></article><article><small>Reste</small><strong><?=cpRapportMoney($solde)?></strong></article><article><small>En retard</small><strong><?=$retard?></strong></article></section>
<section class="rp-panel"><div class="rp-table-wrap"><table class="rp-table"><thead><tr><th>NP/NPF</th><th>Assujetti</th><th>Échéance</th><th>Retard</th><th>Dû</th><th>Payé</th><th>Reste</th><th>Statut</th></tr></thead><tbody>
<?php foreach($rows as $r):$reste=max(0,(float)$r['montant_du']-(float)$r['montant_paye']);?><tr><td><b><?=cpRapportH($r['numero_np'])?></b></td><td><?=cpRapportH($r['contribuable'])?><small><?=cpRapportH($r['nif'])?></small></td><td><?=cpRapportDate($r['date_echeance'])?></td><td><?=max(0,(int)$r['jours_retard'])?> j</td><td><?=cpRapportMoney($r['montant_du'])?></td><td><?=cpRapportMoney($r['montant_paye'])?></td><td><?=cpRapportMoney($reste)?></td><td><?=cpRapportStatusBadge($r['statut'])?></td></tr><?php endforeach;?><?php if(!$rows):?><tr><td colspan="8" class="rp-empty">Aucune créance.</td></tr><?php endif;?></tbody></table></div></section>
<?php cpRapportPageEnd(); ?>
