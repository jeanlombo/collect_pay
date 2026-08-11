<?php
require_once "_rapport_bootstrap.php";
$f=cpRapportFilters();$c=cpRapportCatalogues($pdo);
$niveau=trim((string)($_GET['niveau']??'centre'));
if(!in_array($niveau,['province','centre','service'],true))$niveau='centre';

[$where,$params]=cpRapportScopeWhere($f,"COALESCE(np.date_emission,np.created_at)");
$whereSql=$where?"WHERE ".implode(" AND ",$where):"";
$paySub="SELECT note_perception_id,SUM(CASE WHEN statut<>'annule' THEN montant_converti_cdf ELSE 0 END) total_paye FROM paiements WHERE note_perception_id IS NOT NULL GROUP BY note_perception_id";
$map=[
 'province'=>["pr.nom","pr.id"],
 'centre'=>["CONCAT(pr.nom,' / ',ce.nom)","ce.id"],
 'service'=>["CONCAT(pr.nom,' / ',ce.nom,' / ',COALESCE(s.nom_service,'Sans service'))","COALESCE(s.id,0)"]
];
[$label,$group]=$map[$niveau];
$sql="
SELECT {$label} libelle,COUNT(DISTINCT np.id) nb_notes,
 SUM(COALESCE(NULLIF(np.montant_initial,0),np.montant_total,0)) montant_du,
 SUM(COALESCE(pp.total_paye,0)) montant_paye,
 SUM(np.statut='payee') nb_payees,
 SUM(np.statut='partiellement_payee') nb_partielles
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
ORDER BY montant_paye DESC
";
$stmt=$pdo->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);

cpRapportPageStart("Rapport de performance","Classement des entités selon le niveau de recouvrement.");
?>
<section class="rp-panel"><form method="GET" class="rp-filter-grid"><label>Niveau<select name="niveau"><option value="province" <?=$niveau==='province'?'selected':''?>>Province</option><option value="centre" <?=$niveau==='centre'?'selected':''?>>Centre</option><option value="service" <?=$niveau==='service'?'selected':''?>>Service</option></select></label><label>Du<input type="date" name="date_debut" value="<?=cpRapportH($f['date_debut'])?>"></label><label>Au<input type="date" name="date_fin" value="<?=cpRapportH($f['date_fin'])?>"></label><div class="rp-filter-action"><button>Comparer</button></div></form></section>
<section class="rp-panel"><div class="rp-table-wrap"><table class="rp-table"><thead><tr><th>#</th><th>Entité</th><th>Notes</th><th>Dû</th><th>Payé</th><th>Solde</th><th>Taux</th><th>Payées</th><th>Partielles</th></tr></thead><tbody><?php $rank=0;foreach($rows as $r):$rank++;$du=(float)$r['montant_du'];$pa=(float)$r['montant_paye'];$ta=$du>0?min(100,$pa/$du*100):0;?><tr><td><b><?=$rank?></b></td><td><?=cpRapportH($r['libelle'])?></td><td><?=$r['nb_notes']?></td><td><?=cpRapportMoney($du)?></td><td><?=cpRapportMoney($pa)?></td><td><?=cpRapportMoney(max(0,$du-$pa))?></td><td><span class="rp-rate"><?=number_format($ta,1,',',' ')?> %</span></td><td><?=$r['nb_payees']?></td><td><?=$r['nb_partielles']?></td></tr><?php endforeach;?><?php if(!$rows):?><tr><td colspan="9" class="rp-empty">Aucune donnée.</td></tr><?php endif;?></tbody></table></div></section>
<?php cpRapportPageEnd(); ?>
