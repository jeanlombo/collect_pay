<?php
require_once "_rapport_bootstrap.php";
$f=cpRapportFilters();$c=cpRapportCatalogues($pdo);
$statut=trim((string)($_GET['statut']??'tous'));$search=trim((string)($_GET['search']??''));
$where=[];$params=[];
if($f['date_debut']){$where[]="DATE(COALESCE(nd.date_liquidation,nd.created_at))>=?";$params[]=$f['date_debut'];}
if($f['date_fin']){$where[]="DATE(COALESCE(nd.date_liquidation,nd.created_at))<=?";$params[]=$f['date_fin'];}
if($f['province_id']){$where[]="pr.id=?";$params[]=$f['province_id'];}
if($f['centre_id']){$where[]="ce.id=?";$params[]=$f['centre_id'];}
if($statut!=='tous'){$where[]="nd.statut=?";$params[]=$statut;}
if($search!==''){$like="%{$search}%";$where[]="(nd.numero_nd LIKE ? OR nt.numero_nt LIKE ? OR ct.nif LIKE ? OR ct.raison_sociale LIKE ? OR ct.nom LIKE ?)";array_push($params,$like,$like,$like,$like,$like);}
$whereSql=$where?"WHERE ".implode(" AND ",$where):"";
$sql="
SELECT nd.*,nt.numero_nt,".cpRapportNomContribuableSql("ct")." contribuable,ct.nif,
 pr.nom province,ce.nom centre,s.nom_service
FROM notes_debit nd
JOIN notes_taxation nt ON nd.note_taxation_id=nt.id
JOIN contribuables ct ON nt.contribuable_id=ct.id
JOIN centres ce ON nt.centre_id=ce.id JOIN provinces pr ON ce.province_id=pr.id
LEFT JOIN services_assiette s ON nt.service_id=s.id
{$whereSql}
ORDER BY COALESCE(nd.date_liquidation,DATE(nd.created_at)) DESC,nd.id DESC
";
$stmt=$pdo->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
$total=0;foreach($rows as $r)$total+=(float)($r['montant_total']?:$r['total_exigible']);
cpRapportPageStart("Rapport — Notes de Débit","Liquidations émises et état de validation.");
?>
<section class="rp-panel"><form method="GET" class="rp-filter-grid"><label>Recherche<input name="search" value="<?=cpRapportH($search)?>" placeholder="N° ND, NT, NIF"></label><label>Statut<select name="statut"><option value="tous">Tous</option><option value="en_controle" <?=$statut==='en_controle'?'selected':''?>>En contrôle</option><option value="validee" <?=$statut==='validee'?'selected':''?>>Validée</option><option value="rejete" <?=$statut==='rejete'?'selected':''?>>Rejetée</option></select></label><label>Du<input type="date" name="date_debut" value="<?=cpRapportH($f['date_debut'])?>"></label><label>Au<input type="date" name="date_fin" value="<?=cpRapportH($f['date_fin'])?>"></label><label>Province<select name="province_id"><option value="0">Toutes</option><?php foreach($c['provinces'] as $x):?><option value="<?=$x['id']?>" <?=$f['province_id']==$x['id']?'selected':''?>><?=cpRapportH($x['nom'])?></option><?php endforeach;?></select></label><label>Centre<select name="centre_id"><option value="0">Tous</option><?php foreach($c['centres'] as $x):?><option value="<?=$x['id']?>" <?=$f['centre_id']==$x['id']?'selected':''?>><?=cpRapportH($x['nom'])?></option><?php endforeach;?></select></label><div class="rp-filter-action"><button>Filtrer</button></div></form></section>
<section class="rp-kpis"><article><small>Notes de débit</small><strong><?=count($rows)?></strong></article><article><small>Total exigible</small><strong><?=cpRapportMoney($total)?></strong></article></section>
<section class="rp-panel"><div class="rp-table-wrap"><table class="rp-table"><thead><tr><th>Date</th><th>ND</th><th>NT</th><th>Assujetti</th><th>Province / Centre</th><th>Principal</th><th>Frais</th><th>Pénalités</th><th>Total</th><th>Statut</th><th>PDF</th></tr></thead><tbody><?php foreach($rows as $r):$frais=(float)$r['montant_frais_admin']+(float)$r['montant_frais_tech'];$pen=(float)$r['penalite_assiette']+(float)$r['penalite_recouvrement'];$tot=(float)($r['montant_total']?:$r['total_exigible']);?><tr><td><?=cpRapportDate($r['date_liquidation']?:$r['created_at'])?></td><td><b><?=cpRapportH($r['numero_nd'])?></b></td><td><?=cpRapportH($r['numero_nt'])?></td><td><?=cpRapportH($r['contribuable'])?><small><?=cpRapportH($r['nif'])?></small></td><td><?=cpRapportH($r['province'].' / '.$r['centre'])?></td><td><?=cpRapportMoney($r['montant_acte'])?></td><td><?=cpRapportMoney($frais)?></td><td><?=cpRapportMoney($pen)?></td><td><b><?=cpRapportMoney($tot)?></b></td><td><?=cpRapportStatusBadge($r['statut'])?></td><td><a class="rp-link" target="_blank" href="nd_pdf.php?numero=<?=urlencode($r['numero_nd'])?>">Ouvrir</a></td></tr><?php endforeach;?><?php if(!$rows):?><tr><td colspan="11" class="rp-empty">Aucune Note de Débit.</td></tr><?php endif;?></tbody></table></div></section>
<?php cpRapportPageEnd(); ?>
