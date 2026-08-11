<?php
require_once "_rapport_bootstrap.php";
$f=cpRapportFilters();$c=cpRapportCatalogues($pdo);
$etat=trim((string)($_GET['etat']??'tous'));$search=trim((string)($_GET['search']??''));
$where=["np.type_np='globale'"];$params=[];
if($f['date_debut']){$where[]="DATE(COALESCE(np.date_emission,np.created_at))>=?";$params[]=$f['date_debut'];}
if($f['date_fin']){$where[]="DATE(COALESCE(np.date_emission,np.created_at))<=?";$params[]=$f['date_fin'];}
if($f['province_id']){$where[]="pr.id=?";$params[]=$f['province_id'];}
if($f['centre_id']){$where[]="ce.id=?";$params[]=$f['centre_id'];}
if($etat==='payees')$where[]="np.statut='payee'";
elseif($etat==='non_payees')$where[]="np.statut IN ('en_attente','non_payee')";
elseif($etat==='partielles')$where[]="np.statut='partiellement_payee'";
elseif($etat==='defaillantes')$where[]="np.statut='defaillante'";
if($search!==''){$like="%{$search}%";$where[]="(np.numero_np LIKE ? OR nd.numero_nd LIKE ? OR nt.numero_nt LIKE ? OR ct.nif LIKE ? OR ct.raison_sociale LIKE ? OR ct.nom LIKE ?)";array_push($params,$like,$like,$like,$like,$like,$like);}
$whereSql="WHERE ".implode(" AND ",$where);
$paySub="SELECT note_perception_id,SUM(CASE WHEN statut<>'annule' THEN montant_converti_cdf ELSE 0 END) total_paye,COUNT(CASE WHEN statut<>'annule' THEN id END) nb_paiements FROM paiements WHERE note_perception_id IS NOT NULL GROUP BY note_perception_id";
$sql="
SELECT np.*,nd.numero_nd,nt.numero_nt,
 ".cpRapportNomContribuableSql("ct")." contribuable,ct.nif,
 pr.nom province,ce.nom centre,
 COALESCE(pp.total_paye,0) total_paye_reel,COALESCE(pp.nb_paiements,0) nb_paiements
FROM notes_perception np
JOIN notes_debit nd ON np.note_debit_id=nd.id JOIN notes_taxation nt ON nd.note_taxation_id=nt.id
JOIN contribuables ct ON nt.contribuable_id=ct.id JOIN centres ce ON nt.centre_id=ce.id JOIN provinces pr ON ce.province_id=pr.id
LEFT JOIN ({$paySub}) pp ON pp.note_perception_id=np.id
{$whereSql}
ORDER BY COALESCE(np.date_emission,np.created_at) DESC,np.id DESC
";
$stmt=$pdo->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
$totalDu=$totalPaye=0;$nbPayees=$nbNon=$nbPart=$nbDef=0;
foreach($rows as $r){$du=(float)($r['montant_initial']?:$r['montant_total']);$totalDu+=$du;$totalPaye+=(float)$r['total_paye_reel'];if($r['statut']==='payee')$nbPayees++;elseif($r['statut']==='partiellement_payee')$nbPart++;elseif($r['statut']==='defaillante')$nbDef++;else$nbNon++;}
cpRapportPageStart("Notes de Perception (NP)","Suivi des NP payées, non payées, partielles et défaillantes.");
?>
<section class="rp-panel"><form method="GET" class="rp-filter-grid"><label>Recherche<input name="search" value="<?=cpRapportH($search)?>" placeholder="N° NP, ND, NT, NIF"></label><label>État<select name="etat"><option value="tous" <?=$etat==='tous'?'selected':''?>>Toutes</option><option value="payees" <?=$etat==='payees'?'selected':''?>>Payées</option><option value="non_payees" <?=$etat==='non_payees'?'selected':''?>>Non payées</option><option value="partielles" <?=$etat==='partielles'?'selected':''?>>Partielles</option><option value="defaillantes" <?=$etat==='defaillantes'?'selected':''?>>Défaillantes</option></select></label><label>Du<input type="date" name="date_debut" value="<?=cpRapportH($f['date_debut'])?>"></label><label>Au<input type="date" name="date_fin" value="<?=cpRapportH($f['date_fin'])?>"></label><label>Province<select name="province_id"><option value="0">Toutes</option><?php foreach($c['provinces'] as $x):?><option value="<?=$x['id']?>" <?=$f['province_id']==$x['id']?'selected':''?>><?=cpRapportH($x['nom'])?></option><?php endforeach;?></select></label><label>Centre<select name="centre_id"><option value="0">Tous</option><?php foreach($c['centres'] as $x):?><option value="<?=$x['id']?>" <?=$f['centre_id']==$x['id']?'selected':''?>><?=cpRapportH($x['nom'])?></option><?php endforeach;?></select></label><div class="rp-filter-action"><button>Filtrer</button></div></form></section>
<section class="rp-kpis"><article><small>Total documents</small><strong><?=count($rows)?></strong></article><article><small>Payées</small><strong><?=$nbPayees?></strong></article><article><small>Non payées</small><strong><?=$nbNon?></strong></article><article><small>Partielles</small><strong><?=$nbPart?></strong></article><article><small>Défaillantes</small><strong><?=$nbDef?></strong></article><article><small>Solde global</small><strong><?=cpRapportMoney(max(0,$totalDu-$totalPaye))?></strong></article></section>
<section class="rp-panel"><div class="rp-table-wrap"><table class="rp-table"><thead><tr><th>Date</th><th>NP/NPF</th><th>Assujetti</th><th>NT / ND</th><th>Échéance</th><th>Dû</th><th>Payé</th><th>Solde</th><th>Paiements</th><th>Statut</th><th>PDF</th></tr></thead><tbody><?php foreach($rows as $r):$du=(float)($r['montant_initial']?:$r['montant_total']);$pa=(float)$r['total_paye_reel'];$so=max(0,$du-$pa);?><tr><td><?=cpRapportDate($r['date_emission']?:$r['created_at'])?></td><td><b><?=cpRapportH($r['numero_np'])?></b><?php if(!empty($r['numero_tranche'])):?><small>Tranche <?=$r['numero_tranche']?></small><?php endif;?></td><td><?=cpRapportH($r['contribuable'])?><small><?=cpRapportH($r['nif'])?></small></td><td><?=cpRapportH($r['numero_nt'])?><small><?=cpRapportH($r['numero_nd'])?></small></td><td><?=cpRapportDate($r['date_echeance'])?></td><td><?=cpRapportMoney($du)?></td><td><?=cpRapportMoney($pa)?></td><td><b><?=cpRapportMoney($so)?></b></td><td><?=$r['nb_paiements']?></td><td><?=cpRapportStatusBadge($r['statut'])?></td><td><a class="rp-link" target="_blank" href="np_pdf.php?numero=<?=urlencode($r['numero_np'])?>">Ouvrir</a></td></tr><?php endforeach;?><?php if(!$rows):?><tr><td colspan="11" class="rp-empty">Aucun document trouvé.</td></tr><?php endif;?></tbody></table></div></section>
<?php cpRapportPageEnd(); ?>
