<?php
require_once "_rapport_bootstrap.php";
$f=cpRapportFilters();
$where=["DATE(a.date_emission)>=?","DATE(a.date_emission)<=?"];$params=[$f['date_debut'],$f['date_fin']];
$whereSql="WHERE ".implode(" AND ",$where);
$sql="
SELECT a.*,np.numero_np,u1.nom emetteur,u2.nom validateur
FROM amr a
JOIN notes_perception np ON a.note_perception_id=np.id
LEFT JOIN users u1 ON a.user_emission_id=u1.id
LEFT JOIN users u2 ON a.user_validation_id=u2.id
{$whereSql}
ORDER BY a.date_emission DESC,a.id DESC
";
$stmt=$pdo->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
$principal=$penalite=$total=0;foreach($rows as $r){$principal+=(float)$r['montant_principal'];$penalite+=(float)$r['montant_penalite'];$total+=(float)$r['montant_total'];}
cpRapportPageStart("Rapport AMR","Avis de mise en recouvrement, pénalités et jours de retard.");
?>
<section class="rp-panel"><form method="GET" class="rp-search-form"><label>Du<input type="date" name="date_debut" value="<?=cpRapportH($f['date_debut'])?>"></label><label>Au<input type="date" name="date_fin" value="<?=cpRapportH($f['date_fin'])?>"></label><button>Filtrer</button></form></section>
<section class="rp-kpis"><article><small>AMR</small><strong><?=count($rows)?></strong></article><article><small>Principal</small><strong><?=cpRapportMoney($principal)?></strong></article><article><small>Pénalités</small><strong><?=cpRapportMoney($penalite)?></strong></article><article><small>Total réclamé</small><strong><?=cpRapportMoney($total)?></strong></article></section>
<section class="rp-panel"><div class="rp-table-wrap"><table class="rp-table"><thead><tr><th>Date</th><th>AMR</th><th>Référence</th><th>Principal</th><th>Pénalité</th><th>Total</th><th>Retard</th><th>Statut</th><th>Émetteur / validateur</th><th>PDF</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><?=cpRapportDate($r['date_emission'])?></td><td><b><?=cpRapportH($r['numero_amr'])?></b></td><td><?=cpRapportH($r['reference_numero'])?></td><td><?=cpRapportMoney($r['montant_principal'])?></td><td><?=cpRapportMoney($r['montant_penalite'])?></td><td><?=cpRapportMoney($r['montant_total'])?></td><td><?=$r['jours_retard']?> j</td><td><?=cpRapportStatusBadge($r['statut'])?></td><td><?=cpRapportH(($r['emetteur']??'-').' / '.($r['validateur']??'-'))?></td><td><a class="rp-link" href="amr_pdf.php?numero=<?=urlencode($r['numero_amr'])?>" target="_blank">Ouvrir</a></td></tr><?php endforeach;?><?php if(!$rows):?><tr><td colspan="10" class="rp-empty">Aucun AMR.</td></tr><?php endif;?></tbody></table></div></section>
<?php cpRapportPageEnd(); ?>
