<?php
require_once "_rapport_bootstrap.php";
$f=cpRapportFilters();
$c=cpRapportCatalogues($pdo);

$mois=(int)($_GET['mois']??date('n'));
$annee=(int)($_GET['annee']??date('Y'));
if($mois<1||$mois>12)$mois=(int)date('n');
if($annee<2000||$annee>2100)$annee=(int)date('Y');
$f['date_debut']=sprintf('%04d-%02d-01',$annee,$mois);
$f['date_fin']=date('Y-m-t',strtotime($f['date_debut']));

[$where,$params]=cpRapportScopeWhere($f,"COALESCE(np.date_emission,np.created_at)");
$whereSql=$where?"WHERE ".implode(" AND ",$where):"";

$paySub="
 SELECT note_perception_id,
        SUM(CASE WHEN statut<>'annule' THEN montant_converti_cdf ELSE 0 END) total_paye,
        COUNT(CASE WHEN statut<>'annule' THEN 1 END) nb_paiements
 FROM paiements WHERE note_perception_id IS NOT NULL GROUP BY note_perception_id
";

$sql="
SELECT np.numero_np,np.type_np,np.statut,
       COALESCE(np.date_emission,np.created_at) date_emission,
       COALESCE(NULLIF(np.montant_initial,0),np.montant_total,0) montant_du,
       COALESCE(pp.total_paye,0) montant_paye,
       COALESCE(pp.nb_paiements,0) nb_paiements,
       ".cpRapportNomContribuableSql("ct")." contribuable,ct.nif,
       pr.nom province,ce.nom centre,s.nom_service,d.nom_direction
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
ORDER BY date_emission DESC,np.id DESC
";
$stmt=$pdo->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);

$totalDu=$totalPaye=0;$payees=$partielles=$autres=$nbP=0;
foreach($rows as $r){
 $totalDu+=(float)$r['montant_du'];$totalPaye+=(float)$r['montant_paye'];$nbP+=(int)$r['nb_paiements'];
 if($r['statut']==='payee')$payees++;elseif($r['statut']==='partiellement_payee')$partielles++;else$autres++;
}
$solde=max(0,$totalDu-$totalPaye);$taux=$totalDu>0?min(100,$totalPaye/$totalDu*100):0;
$months=[1=>'Janvier',2=>'Février',3=>'Mars',4=>'Avril',5=>'Mai',6=>'Juin',7=>'Juillet',8=>'Août',9=>'Septembre',10=>'Octobre',11=>'Novembre',12=>'Décembre'];

cpRapportPageStart("Rapport mensuel",$months[$mois]." ".$annee." — situation des notes et paiements.");
?>
<section class="rp-panel">
<form method="GET" class="rp-filter-grid monthly">
 <label>Mois<select name="mois"><?php foreach($months as $n=>$l): ?><option value="<?=$n?>" <?=$n===$mois?'selected':''?>><?=cpRapportH($l)?></option><?php endforeach; ?></select></label>
 <label>Année<input type="number" name="annee" min="2000" max="2100" value="<?=$annee?>"></label>
 <label>Province<select name="province_id"><option value="0">Toutes</option><?php foreach($c['provinces'] as $x):?><option value="<?=$x['id']?>" <?=$f['province_id']==$x['id']?'selected':''?>><?=cpRapportH($x['nom'])?></option><?php endforeach;?></select></label>
 <label>Centre<select name="centre_id"><option value="0">Tous</option><?php foreach($c['centres'] as $x):?><option value="<?=$x['id']?>" <?=$f['centre_id']==$x['id']?'selected':''?>><?=cpRapportH($x['province'].' / '.$x['nom'])?></option><?php endforeach;?></select></label>
 <label>Direction<select name="direction_id"><option value="0">Toutes</option><?php foreach($c['directions'] as $x):?><option value="<?=$x['id']?>" <?=$f['direction_id']==$x['id']?'selected':''?>><?=cpRapportH($x['nom_direction'])?></option><?php endforeach;?></select></label>
 <label>Service<select name="service_id"><option value="0">Tous</option><?php foreach($c['services'] as $x):?><option value="<?=$x['id']?>" <?=$f['service_id']==$x['id']?'selected':''?>><?=cpRapportH($x['nom_service'])?></option><?php endforeach;?></select></label>
 <div class="rp-filter-action"><button>Afficher</button></div>
</form>
</section>

<section class="rp-kpis">
 <article><small>Notes</small><strong><?=count($rows)?></strong></article>
 <article><small>Total dû</small><strong><?=cpRapportMoney($totalDu)?></strong></article>
 <article><small>Payé</small><strong><?=cpRapportMoney($totalPaye)?></strong></article>
 <article><small>Solde</small><strong><?=cpRapportMoney($solde)?></strong></article>
 <article><small>Taux recouvrement</small><strong><?=number_format($taux,1,',',' ')?> %</strong></article>
 <article><small>Paiements</small><strong><?=$nbP?></strong></article>
</section>

<section class="rp-panel">
<div class="rp-panel-head"><h2>Détail des Notes de Perception</h2><span><?=$payees?> payée(s), <?=$partielles?> partielle(s), <?=$autres?> autre(s)</span></div>
<div class="rp-table-wrap"><table class="rp-table"><thead><tr><th>Date</th><th>NP/NPF</th><th>Assujetti</th><th>Province / Centre</th><th>Service</th><th>Dû</th><th>Payé</th><th>Solde</th><th>Statut</th></tr></thead><tbody>
<?php foreach($rows as $r):$s=max(0,(float)$r['montant_du']-(float)$r['montant_paye']);?>
<tr><td><?=cpRapportDate($r['date_emission'])?></td><td><b><?=cpRapportH($r['numero_np'])?></b><small><?=cpRapportH(strtoupper($r['type_np']))?></small></td><td><?=cpRapportH($r['contribuable'])?><small><?=cpRapportH($r['nif'])?></small></td><td><?=cpRapportH($r['province'].' / '.$r['centre'])?></td><td><?=cpRapportH($r['nom_service']??'-')?></td><td><?=cpRapportMoney($r['montant_du'])?></td><td><?=cpRapportMoney($r['montant_paye'])?></td><td><?=cpRapportMoney($s)?></td><td><?=cpRapportStatusBadge($r['statut'])?></td></tr>
<?php endforeach;?>
<?php if(!$rows):?><tr><td colspan="9" class="rp-empty">Aucune donnée pour cette période.</td></tr><?php endif;?>
</tbody></table></div>
</section>
<?php cpRapportPageEnd(); ?>
