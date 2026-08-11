<?php
require_once "_rapport_bootstrap.php";
$f=cpRapportFilters();$c=cpRapportCatalogues($pdo);
[$where,$params]=cpRapportScopeWhere($f,"nt.created_at");
$whereSql=$where?"WHERE ".implode(" AND ",$where):"";
$sql="
SELECT nt.numero_nt,nt.statut statut_nt,nt.created_at date_nt,
       nd.numero_nd,nd.statut statut_nd,nd.date_liquidation,
       np.numero_np,np.type_np,np.statut statut_np,np.date_emission,np.date_echeance,
       ".cpRapportNomContribuableSql("ct")." contribuable,ct.nif,
       pr.nom province,ce.nom centre,s.nom_service,
       COALESCE(NULLIF(np.montant_initial,0),np.montant_total,0) montant_np,
       COALESCE(SUM(CASE WHEN p.statut<>'annule' THEN p.montant_converti_cdf ELSE 0 END),0) total_paye,
       COUNT(CASE WHEN p.statut<>'annule' THEN p.id END) nb_paiements
FROM notes_taxation nt
JOIN contribuables ct ON nt.contribuable_id=ct.id
JOIN centres ce ON nt.centre_id=ce.id
JOIN provinces pr ON ce.province_id=pr.id
LEFT JOIN services_assiette s ON nt.service_id=s.id
LEFT JOIN directions d ON s.direction_id=d.id
LEFT JOIN notes_debit nd ON nd.note_taxation_id=nt.id
LEFT JOIN notes_perception np ON np.note_debit_id=nd.id
LEFT JOIN paiements p ON p.note_perception_id=np.id
{$whereSql}
GROUP BY nt.id,nd.id,np.id
ORDER BY nt.created_at DESC
";
$stmt=$pdo->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
cpRapportPageStart("Rapport du cycle fiscal","Traçabilité complète de la Note de Taxation jusqu’au paiement.");
?>
<section class="rp-panel"><?php cpRapportFilterHtml($f,$c); ?></section>
<section class="rp-panel"><div class="rp-table-wrap"><table class="rp-table"><thead><tr><th>Assujetti</th><th>NT</th><th>ND</th><th>NP/NPF</th><th>Montant NP</th><th>Paiements</th><th>Solde</th><th>Province / Centre</th></tr></thead><tbody><?php foreach($rows as $r):$so=max(0,(float)$r['montant_np']-(float)$r['total_paye']);?><tr><td><b><?=cpRapportH($r['contribuable'])?></b><small><?=cpRapportH($r['nif'])?></small></td><td><?=cpRapportH($r['numero_nt'])?><small><?=cpRapportStatusBadge($r['statut_nt'])?></small></td><td><?=cpRapportH($r['numero_nd']?:'-')?><small><?=cpRapportStatusBadge($r['statut_nd']??'')?></small></td><td><?=cpRapportH($r['numero_np']?:'-')?><small><?=cpRapportStatusBadge($r['statut_np']??'')?></small></td><td><?=cpRapportMoney($r['montant_np'])?></td><td><?=cpRapportMoney($r['total_paye'])?><small><?=$r['nb_paiements']?> opération(s)</small></td><td><?=cpRapportMoney($so)?></td><td><?=cpRapportH($r['province'].' / '.$r['centre'])?></td></tr><?php endforeach;?><?php if(!$rows):?><tr><td colspan="8" class="rp-empty">Aucun document.</td></tr><?php endif;?></tbody></table></div></section>
<?php cpRapportPageEnd(); ?>
