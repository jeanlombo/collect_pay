<?php
require_once "_rapport_bootstrap.php";
$f=cpRapportFilters();$c=cpRapportCatalogues($pdo);
$type=trim((string)($_GET['type']??'tous'));$statut=trim((string)($_GET['statut']??'tous'));

$where=["DATE(ph.date_application)>=?","DATE(ph.date_application)<=?"];$params=[$f['date_debut'],$f['date_fin']];
if($type!=='tous'){$where[]="ph.type=?";$params[]=$type;}
if($statut!=='tous'){$where[]="ph.statut=?";$params[]=$statut;}
$whereSql="WHERE ".implode(" AND ",$where);

$sql="
SELECT ph.*,
 CASE ph.reference_type
   WHEN 'ND' THEN nd.numero_nd
   WHEN 'NP' THEN np.numero_np
   WHEN 'FRACTION' THEN fr.numero_fraction
 END numero_reference,
 u.nom validateur
FROM penalites_historique ph
LEFT JOIN notes_debit nd ON ph.reference_type='ND' AND ph.reference_id=nd.id
LEFT JOIN notes_perception np ON ph.reference_type='NP' AND ph.reference_id=np.id
LEFT JOIN notes_perception_fractions fr ON ph.reference_type='FRACTION' AND ph.reference_id=fr.id
LEFT JOIN users u ON ph.user_validation_id=u.id
{$whereSql}
ORDER BY ph.date_application DESC,ph.id DESC
";
$stmt=$pdo->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
$total=0;$valid=0;foreach($rows as $r){$total+=(float)$r['montant_penalite'];if($r['statut']==='validee')$valid+=(float)$r['montant_penalite'];}

cpRapportPageStart("Rapport des pénalités","Pénalités d’assiette et de recouvrement proposées ou validées.");
?>
<section class="rp-panel"><form method="GET" class="rp-filter-grid"><label>Du<input type="date" name="date_debut" value="<?=cpRapportH($f['date_debut'])?>"></label><label>Au<input type="date" name="date_fin" value="<?=cpRapportH($f['date_fin'])?>"></label><label>Type<select name="type"><option value="tous">Tous</option><option value="assiette" <?=$type==='assiette'?'selected':''?>>Assiette</option><option value="recouvrement" <?=$type==='recouvrement'?'selected':''?>>Recouvrement</option></select></label><label>Statut<select name="statut"><option value="tous">Tous</option><option value="proposee" <?=$statut==='proposee'?'selected':''?>>Proposée</option><option value="validee" <?=$statut==='validee'?'selected':''?>>Validée</option><option value="suspendue" <?=$statut==='suspendue'?'selected':''?>>Suspendue</option><option value="annulee" <?=$statut==='annulee'?'selected':''?>>Annulée</option></select></label><div class="rp-filter-action"><button>Filtrer</button></div></form></section>
<section class="rp-kpis"><article><small>Pénalités</small><strong><?=count($rows)?></strong></article><article><small>Montant total</small><strong><?=cpRapportMoney($total)?></strong></article><article><small>Montant validé</small><strong><?=cpRapportMoney($valid)?></strong></article></section>
<section class="rp-panel"><div class="rp-table-wrap"><table class="rp-table"><thead><tr><th>Date</th><th>Type</th><th>Référence</th><th>Base</th><th>Taux</th><th>Pénalité</th><th>Retard</th><th>Statut</th><th>Validateur</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><?=cpRapportDate($r['date_application'])?></td><td><?=cpRapportH($r['type'])?></td><td><b><?=cpRapportH($r['numero_reference']?:$r['reference_type'].'#'.$r['reference_id'])?></b></td><td><?=cpRapportMoney($r['montant_base'])?></td><td><?=number_format((float)$r['taux_applique'],2,',',' ')?> %</td><td><?=cpRapportMoney($r['montant_penalite'])?></td><td><?=number_format((int)$r['jours_retard'],0,',',' ')?> j</td><td><?=cpRapportStatusBadge($r['statut'])?></td><td><?=cpRapportH($r['validateur']??'-')?></td></tr><?php endforeach;?><?php if(!$rows):?><tr><td colspan="9" class="rp-empty">Aucune pénalité.</td></tr><?php endif;?></tbody></table></div></section>
<?php cpRapportPageEnd(); ?>
