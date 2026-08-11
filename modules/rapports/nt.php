<?php
require_once "_rapport_bootstrap.php";
$f=cpRapportFilters(); $c=cpRapportCatalogues($pdo);
$statut=trim((string)($_GET['statut']??'tous'));
$search=trim((string)($_GET['search']??''));

$where=[];$params=[];
if($f['date_debut']){$where[]="DATE(nt.created_at)>=?";$params[]=$f['date_debut'];}
if($f['date_fin']){$where[]="DATE(nt.created_at)<=?";$params[]=$f['date_fin'];}
if($f['province_id']){$where[]="pr.id=?";$params[]=$f['province_id'];}
if($f['centre_id']){$where[]="ce.id=?";$params[]=$f['centre_id'];}
if($f['direction_id']){$where[]="d.id=?";$params[]=$f['direction_id'];}
if($f['service_id']){$where[]="s.id=?";$params[]=$f['service_id'];}
if($statut!=='tous'){$where[]="nt.statut=?";$params[]=$statut;}
if($search!==''){
  $like="%{$search}%";
  $where[]="(nt.numero_nt LIKE ? OR ct.nif LIKE ? OR ct.raison_sociale LIKE ? OR ct.nom LIKE ? OR ct.postnom LIKE ? OR ct.prenom LIKE ?)";
  array_push($params,$like,$like,$like,$like,$like,$like);
}
$whereSql=$where?"WHERE ".implode(" AND ",$where):"";

$sql="
SELECT nt.id,nt.numero_nt,nt.exercice,nt.statut,nt.total_estime,nt.devise,nt.taux_change,
       nt.created_at,nt.source_creation,
       ".cpRapportNomContribuableSql("ct")." contribuable,ct.nif,
       pr.nom province,ce.nom centre,s.nom_service,d.nom_direction
FROM notes_taxation nt
JOIN contribuables ct ON nt.contribuable_id=ct.id
JOIN centres ce ON nt.centre_id=ce.id
JOIN provinces pr ON ce.province_id=pr.id
LEFT JOIN services_assiette s ON nt.service_id=s.id
LEFT JOIN directions d ON s.direction_id=d.id
{$whereSql}
ORDER BY nt.created_at DESC,nt.id DESC
";
$stmt=$pdo->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
$total=0;foreach($rows as $r)$total+=(float)$r['total_estime'];

cpRapportPageStart("Rapport — Notes de Taxation","Toutes les NT avec contribuable, centre, service, montant et statut.");
?>
<section class="rp-panel">
<form method="GET" class="rp-filter-grid">
<label>Recherche<input type="text" name="search" value="<?=cpRapportH($search)?>" placeholder="N° NT, NIF, assujetti"></label>
<label>Statut<select name="statut">
<option value="tous">Tous</option>
<?php foreach(['brouillon'=>'Brouillon','en_attente_liquidation'=>'En attente liquidation','liquidee'=>'Liquidée','rejetee'=>'Rejetée','annulee'=>'Annulée'] as $v=>$l):?>
<option value="<?=$v?>" <?=$statut===$v?'selected':''?>><?=$l?></option><?php endforeach;?>
</select></label>
<label>Du<input type="date" name="date_debut" value="<?=cpRapportH($f['date_debut'])?>"></label>
<label>Au<input type="date" name="date_fin" value="<?=cpRapportH($f['date_fin'])?>"></label>
<label>Province<select name="province_id"><option value="0">Toutes</option><?php foreach($c['provinces'] as $x):?><option value="<?=$x['id']?>" <?=$f['province_id']==$x['id']?'selected':''?>><?=cpRapportH($x['nom'])?></option><?php endforeach;?></select></label>
<label>Centre<select name="centre_id"><option value="0">Tous</option><?php foreach($c['centres'] as $x):?><option value="<?=$x['id']?>" <?=$f['centre_id']==$x['id']?'selected':''?>><?=cpRapportH($x['province'].' / '.$x['nom'])?></option><?php endforeach;?></select></label>
<div class="rp-filter-action"><button>Filtrer</button></div>
</form>
</section>
<section class="rp-kpis"><article><small>Notes de taxation</small><strong><?=count($rows)?></strong></article><article><small>Total estimé</small><strong><?=cpRapportMoney($total)?></strong></article></section>
<section class="rp-panel"><div class="rp-table-wrap"><table class="rp-table"><thead><tr><th>Date</th><th>NT</th><th>Assujetti</th><th>Exercice</th><th>Province / Centre</th><th>Service</th><th>Montant estimé</th><th>Statut</th><th>Source</th><th>PDF</th></tr></thead><tbody>
<?php foreach($rows as $r):?><tr><td><?=cpRapportDate($r['created_at'])?></td><td><b><?=cpRapportH($r['numero_nt'])?></b></td><td><?=cpRapportH($r['contribuable'])?><small>NIF : <?=cpRapportH($r['nif'])?></small></td><td><?=cpRapportH($r['exercice'])?></td><td><?=cpRapportH($r['province'].' / '.$r['centre'])?></td><td><?=cpRapportH($r['nom_service']??'-')?></td><td><?=cpRapportMoney($r['total_estime'])?></td><td><?=cpRapportStatusBadge($r['statut'])?></td><td><?=cpRapportH($r['source_creation'])?></td><td><a class="rp-link" target="_blank" href="nt_pdf.php?numero=<?=urlencode($r['numero_nt'])?>">Ouvrir</a></td></tr><?php endforeach;?>
<?php if(!$rows):?><tr><td colspan="10" class="rp-empty">Aucune Note de Taxation trouvée.</td></tr><?php endif;?>
</tbody></table></div></section>
<?php cpRapportPageEnd(); ?>
