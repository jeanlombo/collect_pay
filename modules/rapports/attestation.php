<?php
require_once "_rapport_bootstrap.php";
$f=cpRapportFilters();$c=cpRapportCatalogues($pdo);
$where=["p.statut<>'annule'"];$params=[];
if($f['date_debut']){$where[]="DATE(p.date_paiement)>=?";$params[]=$f['date_debut'];}
if($f['date_fin']){$where[]="DATE(p.date_paiement)<=?";$params[]=$f['date_fin'];}
if($f['province_id']){$where[]="pr.id=?";$params[]=$f['province_id'];}
if($f['centre_id']){$where[]="ce.id=?";$params[]=$f['centre_id'];}
$whereSql="WHERE ".implode(" AND ",$where);
$sql="
SELECT p.id,p.date_paiement,p.montant_paye,p.devise,p.taux_change,p.montant_converti_cdf,
 p.reference_transaction,p.statut,
 np.numero_np,np.type_np,np.statut statut_np,np.montant_initial,np.montant_total,
 ".cpRapportNomContribuableSql("ct")." contribuable,ct.nif,
 pr.nom province,ce.nom centre,mp.libelle mode_paiement
FROM paiements p
JOIN notes_perception np ON p.note_perception_id=np.id
JOIN notes_debit nd ON np.note_debit_id=nd.id JOIN notes_taxation nt ON nd.note_taxation_id=nt.id
JOIN contribuables ct ON nt.contribuable_id=ct.id JOIN centres ce ON nt.centre_id=ce.id JOIN provinces pr ON ce.province_id=pr.id
LEFT JOIN modes_paiement mp ON p.mode_paiement_id=mp.id
{$whereSql}
ORDER BY p.date_paiement DESC,p.id DESC
";
$stmt=$pdo->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
$total=0;foreach($rows as $r)$total+=(float)$r['montant_converti_cdf'];
cpRapportPageStart("Rapport — Attestations de paiement","Chaque paiement de NP/NPF peut être imprimé sous forme d’attestation sécurisée.");
?>
<section class="rp-panel"><?php cpRapportFilterHtml($f,$c);?></section>
<section class="rp-kpis"><article><small>Attestations / paiements</small><strong><?=count($rows)?></strong></article><article><small>Total payé CDF</small><strong><?=cpRapportMoney($total)?></strong></article></section>
<section class="rp-panel"><div class="rp-table-wrap"><table class="rp-table"><thead><tr><th>Date</th><th>NP / NPF</th><th>Assujetti</th><th>Montant payé</th><th>Équivalent CDF</th><th>Mode</th><th>Référence</th><th>Statut NP</th><th>Attestation PDF</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><?=cpRapportDate($r['date_paiement'])?></td><td><b><?=cpRapportH($r['numero_np'])?></b><small><?=cpRapportH(strtoupper($r['type_np']))?></small></td><td><?=cpRapportH($r['contribuable'])?><small><?=cpRapportH($r['nif'])?></small></td><td><?=cpRapportMoney($r['montant_paye'],$r['devise'])?></td><td><?=cpRapportMoney($r['montant_converti_cdf'])?></td><td><?=cpRapportH($r['mode_paiement']??'-')?></td><td><?=cpRapportH($r['reference_transaction']??'-')?></td><td><?=cpRapportStatusBadge($r['statut_np'])?></td><td><a class="rp-link" target="_blank" href="attestation_paiement_pdf.php?numero=<?=urlencode($r['numero_np'])?>">Ouvrir</a></td></tr><?php endforeach;?><?php if(!$rows):?><tr><td colspan="9" class="rp-empty">Aucun paiement pour la période.</td></tr><?php endif;?></tbody></table></div></section>
<?php cpRapportPageEnd(); ?>
