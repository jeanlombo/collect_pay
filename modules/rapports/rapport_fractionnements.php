<?php
require_once "_rapport_bootstrap.php";
$f=cpRapportFilters();
$where=["DATE(av.date_avis)>=?","DATE(av.date_avis)<=?"];$params=[$f['date_debut'],$f['date_fin']];
$whereSql="WHERE ".implode(" AND ",$where);
$sql="
SELECT av.*,np.numero_np,
 COUNT(fr.id) nb_fractions,
 SUM(fr.montant_fraction) montant_fractionne,
 SUM(fr.statut='payee') nb_payees,
 SUM(fr.statut='partiellement_payee') nb_partielles,
 SUM(fr.statut='en_retard') nb_retard
FROM avis_fractionnement av
JOIN notes_perception np ON av.note_perception_id=np.id
LEFT JOIN notes_perception_fractions fr ON fr.avis_id=av.id
{$whereSql}
GROUP BY av.id
ORDER BY av.date_avis DESC,av.id DESC
";
$stmt=$pdo->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
$total=0;foreach($rows as $r)$total+=(float)$r['montant_total'];

cpRapportPageStart("Rapport des fractionnements","Avis accordés, nombre de tranches et situation des échéances.");
?>
<section class="rp-panel"><form method="GET" class="rp-search-form"><label>Du<input type="date" name="date_debut" value="<?=cpRapportH($f['date_debut'])?>"></label><label>Au<input type="date" name="date_fin" value="<?=cpRapportH($f['date_fin'])?>"></label><button>Filtrer</button></form></section>
<section class="rp-kpis"><article><small>Avis</small><strong><?=count($rows)?></strong></article><article><small>Montant concerné</small><strong><?=cpRapportMoney($total)?></strong></article></section>
<section class="rp-panel"><div class="rp-table-wrap"><table class="rp-table"><thead><tr><th>Avis</th><th>NP mère</th><th>Date</th><th>Tranches prévues</th><th>Fractions créées</th><th>Montant</th><th>Payées</th><th>Partielles</th><th>Retard</th><th>Statut</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><b><?=cpRapportH($r['numero_avis'])?></b></td><td><?=cpRapportH($r['numero_np'])?></td><td><?=cpRapportDate($r['date_avis'])?></td><td><?=max((int)$r['nombre_fractions'],(int)$r['nombre_tranches'])?></td><td><?=$r['nb_fractions']?></td><td><?=cpRapportMoney($r['montant_total'])?></td><td><?=$r['nb_payees']?></td><td><?=$r['nb_partielles']?></td><td><?=$r['nb_retard']?></td><td><?=cpRapportStatusBadge($r['statut'])?></td></tr><?php endforeach;?><?php if(!$rows):?><tr><td colspan="10" class="rp-empty">Aucun fractionnement.</td></tr><?php endif;?></tbody></table></div></section>
<?php cpRapportPageEnd(); ?>
