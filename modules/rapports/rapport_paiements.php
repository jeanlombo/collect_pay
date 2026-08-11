<?php
require_once "_rapport_bootstrap.php";
$f=cpRapportFilters();$c=cpRapportCatalogues($pdo);
[$where,$params]=cpRapportScopeWhere($f,"p.date_paiement");
$where[]="p.statut<>'annule'";
$whereSql="WHERE ".implode(" AND ",$where);

$sql="
SELECT p.*,mp.libelle mode_paiement,
 COALESCE(np.numero_np,fr.numero_fraction,'-') reference_document,
 COALESCE(np2.numero_np,np.numero_np,'-') np_mere,
 ".cpRapportNomContribuableSql("ct")." contribuable,ct.nif,
 pr.nom province,ce.nom centre,u.nom comptable
FROM paiements p
LEFT JOIN modes_paiement mp ON p.mode_paiement_id=mp.id
LEFT JOIN notes_perception np ON p.note_perception_id=np.id
LEFT JOIN notes_perception_fractions fr ON p.fraction_id=fr.id
LEFT JOIN notes_perception np2 ON fr.note_mere_id=np2.id
JOIN notes_perception npx ON npx.id=COALESCE(np.id,np2.id)
JOIN notes_debit nd ON npx.note_debit_id=nd.id
JOIN notes_taxation nt ON nd.note_taxation_id=nt.id
JOIN contribuables ct ON nt.contribuable_id=ct.id
JOIN centres ce ON nt.centre_id=ce.id
JOIN provinces pr ON ce.province_id=pr.id
LEFT JOIN services_assiette s ON nt.service_id=s.id
LEFT JOIN directions d ON s.direction_id=d.id
LEFT JOIN users u ON p.user_comptable_id=u.id
{$whereSql}
ORDER BY p.date_paiement DESC,p.id DESC
";
$stmt=$pdo->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
$totalCDF=0;$byMode=[];
foreach($rows as $r){$totalCDF+=(float)$r['montant_converti_cdf'];$m=$r['mode_paiement']?:'Non défini';$byMode[$m]=($byMode[$m]??0)+(float)$r['montant_converti_cdf'];}

cpRapportPageStart("Rapport des paiements","Encaissements par date, mode de paiement, devise et référence.");
?>
<section class="rp-panel"><?php cpRapportFilterHtml($f,$c); ?></section>
<section class="rp-kpis"><article><small>Paiements</small><strong><?=count($rows)?></strong></article><article><small>Total converti CDF</small><strong><?=cpRapportMoney($totalCDF)?></strong></article><?php foreach(array_slice($byMode,0,4,true) as $m=>$v):?><article><small><?=cpRapportH($m)?></small><strong><?=cpRapportMoney($v)?></strong></article><?php endforeach;?></section>
<section class="rp-panel"><div class="rp-table-wrap"><table class="rp-table"><thead><tr><th>Date</th><th>Document</th><th>Assujetti</th><th>Montant</th><th>Converti CDF</th><th>Mode</th><th>Référence</th><th>Banque / compte</th><th>Comptable</th></tr></thead><tbody>
<?php foreach($rows as $r):?><tr><td><?=cpRapportDate($r['date_paiement'])?></td><td><b><?=cpRapportH($r['reference_document'])?></b></td><td><?=cpRapportH($r['contribuable'])?><small><?=cpRapportH($r['nif'])?></small></td><td><?=cpRapportMoney($r['montant_paye'],$r['devise'])?></td><td><?=cpRapportMoney($r['montant_converti_cdf'])?></td><td><?=cpRapportH($r['mode_paiement'])?></td><td><?=cpRapportH($r['reference_transaction']?:'-')?></td><td><?=cpRapportH(trim(($r['banque']??'').' '.($r['numero_compte']??''))?:'-')?></td><td><?=cpRapportH($r['comptable']??'-')?></td></tr><?php endforeach;?><?php if(!$rows):?><tr><td colspan="9" class="rp-empty">Aucun paiement.</td></tr><?php endif;?></tbody></table></div></section>
<?php cpRapportPageEnd(); ?>
