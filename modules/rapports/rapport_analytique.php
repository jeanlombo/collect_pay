<?php
require_once "_rapport_bootstrap.php";
$f=cpRapportFilters();$c=cpRapportCatalogues($pdo);
$axe=trim((string)($_GET['axe']??'province'));
$axes=['province'=>'Province','centre'=>'Centre','direction'=>'Direction','service'=>'Service','article'=>'Article budgétaire','secteur'=>'Secteur / catégorie','nature'=>'Nature d’acte'];
if(!isset($axes[$axe]))$axe='province';

[$where,$params]=cpRapportScopeWhere($f,"COALESCE(np.date_emission,np.created_at)");
$whereSql=$where?"WHERE ".implode(" AND ",$where):"";
$paySub="SELECT note_perception_id,SUM(CASE WHEN statut<>'annule' THEN montant_converti_cdf ELSE 0 END) total_paye FROM paiements WHERE note_perception_id IS NOT NULL GROUP BY note_perception_id";

$rows=[];
if(in_array($axe,['province','centre','direction','service'],true)){
 $maps=[
  'province'=>["pr.nom","pr.id"],
  'centre'=>["CONCAT(pr.nom,' / ',ce.nom)","ce.id"],
  'direction'=>["COALESCE(d.nom_direction,'Sans direction')","COALESCE(d.id,0)"],
  'service'=>["COALESCE(s.nom_service,'Sans service')","COALESCE(s.id,0)"],
 ];
 [$label,$group]=$maps[$axe];
 $sql="
 SELECT {$label} libelle,
        COUNT(DISTINCT np.id) nb_documents,
        SUM(COALESCE(NULLIF(np.montant_initial,0),np.montant_total,0)) montant_du,
        SUM(COALESCE(pp.total_paye,0)) montant_paye
 FROM notes_perception np
 JOIN notes_debit nd ON np.note_debit_id=nd.id
 JOIN notes_taxation nt ON nd.note_taxation_id=nt.id
 JOIN centres ce ON nt.centre_id=ce.id
 JOIN provinces pr ON ce.province_id=pr.id
 LEFT JOIN services_assiette s ON nt.service_id=s.id
 LEFT JOIN directions d ON s.direction_id=d.id
 LEFT JOIN ({$paySub}) pp ON pp.note_perception_id=np.id
 {$whereSql}
 GROUP BY {$group},{$label}
 ORDER BY montant_du DESC
 ";
}else{
 $maps=[
  'article'=>["CONCAT(ab.code_article,' — ',LEFT(ab.nature_acte,120))","ab.id"],
  'secteur'=>["COALESCE(ab.secteur,'Sans secteur')","ab.secteur"],
  'nature'=>["COALESCE(ab.nature_acte,'Sans nature')","ab.nature_acte"],
 ];
 [$label,$group]=$maps[$axe];
 /*
  * Pour ne pas dupliquer le paiement d'une NP sur plusieurs lignes,
  * le paiement est ventilé au prorata de total_ligne / total_estime.
  */
 $sql="
 SELECT {$label} libelle,
        COUNT(DISTINCT np.id) nb_documents,
        SUM(COALESCE(td.total_ligne_cdf,td.total_ligne,0)) montant_du,
        SUM(CASE WHEN COALESCE(nt.total_estime,0)>0
            THEN COALESCE(pp.total_paye,0)*(COALESCE(td.total_ligne_cdf,td.total_ligne,0)/nt.total_estime)
            ELSE 0 END) montant_paye
 FROM notes_taxation_details td
 JOIN notes_taxation nt ON td.note_taxation_id=nt.id
 JOIN articles_budgetaires ab ON td.article_id=ab.id
 JOIN centres ce ON nt.centre_id=ce.id
 JOIN provinces pr ON ce.province_id=pr.id
 LEFT JOIN services_assiette s ON nt.service_id=s.id
 LEFT JOIN directions d ON s.direction_id=d.id
 JOIN notes_debit nd ON nd.note_taxation_id=nt.id
 JOIN notes_perception np ON np.note_debit_id=nd.id
 LEFT JOIN ({$paySub}) pp ON pp.note_perception_id=np.id
 {$whereSql}
 GROUP BY {$group},{$label}
 ORDER BY montant_du DESC
 ";
}
$stmt=$pdo->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);

cpRapportPageStart("Rapport analytique","Analyse consolidée par axe fiscal et géographique.");
?>
<section class="rp-panel">
<form method="GET" class="rp-filter-grid">
<label>Axe<select name="axe"><?php foreach($axes as $v=>$l):?><option value="<?=$v?>" <?=$axe===$v?'selected':''?>><?=cpRapportH($l)?></option><?php endforeach;?></select></label>
<label>Du<input type="date" name="date_debut" value="<?=cpRapportH($f['date_debut'])?>"></label><label>Au<input type="date" name="date_fin" value="<?=cpRapportH($f['date_fin'])?>"></label>
<label>Province<select name="province_id"><option value="0">Toutes</option><?php foreach($c['provinces'] as $x):?><option value="<?=$x['id']?>" <?=$f['province_id']==$x['id']?'selected':''?>><?=cpRapportH($x['nom'])?></option><?php endforeach;?></select></label>
<label>Centre<select name="centre_id"><option value="0">Tous</option><?php foreach($c['centres'] as $x):?><option value="<?=$x['id']?>" <?=$f['centre_id']==$x['id']?'selected':''?>><?=cpRapportH($x['nom'])?></option><?php endforeach;?></select></label>
<div class="rp-filter-action"><button>Analyser</button></div>
</form></section>

<section class="rp-panel"><div class="rp-panel-head"><h2><?=cpRapportH($axes[$axe])?></h2><span><?=count($rows)?> ligne(s)</span></div><div class="rp-table-wrap"><table class="rp-table"><thead><tr><th>Libellé</th><th>Documents</th><th>Montant dû</th><th>Montant payé</th><th>Solde</th><th>Taux</th></tr></thead><tbody>
<?php foreach($rows as $r):$du=(float)$r['montant_du'];$pa=(float)$r['montant_paye'];$so=max(0,$du-$pa);$ta=$du>0?min(100,$pa/$du*100):0;?>
<tr><td><b><?=cpRapportH($r['libelle'])?></b></td><td><?=number_format((int)$r['nb_documents'],0,',',' ')?></td><td><?=cpRapportMoney($du)?></td><td><?=cpRapportMoney($pa)?></td><td><?=cpRapportMoney($so)?></td><td><span class="rp-rate"><?=number_format($ta,1,',',' ')?> %</span></td></tr>
<?php endforeach;?><?php if(!$rows):?><tr><td colspan="6" class="rp-empty">Aucune donnée.</td></tr><?php endif;?>
</tbody></table></div></section>
<?php cpRapportPageEnd(); ?>
