<?php
require_once "_rapport_bootstrap.php";
$f=cpRapportFilters(); $c=cpRapportCatalogues($pdo);
$search=trim((string)($_GET['search']??'')); $statut=trim((string)($_GET['statut']??'tous'));
$rows=[];

if($search!==''){
 [$where,$params]=cpRapportScopeWhere($f,"COALESCE(np.date_emission,np.created_at)");
 $like="%{$search}%";
 $where[]="(ct.raison_sociale LIKE ? OR ct.nom LIKE ? OR ct.postnom LIKE ? OR ct.prenom LIKE ? OR ct.nif LIKE ? OR ct.telephone LIKE ?)";
 array_push($params,$like,$like,$like,$like,$like,$like);
 if($statut==='payees')$where[]="np.statut='payee'";
 elseif($statut==='partielles')$where[]="np.statut='partiellement_payee'";
 elseif($statut==='non_payees')$where[]="np.statut IN ('en_attente','non_payee','defaillante')";
 $whereSql="WHERE ".implode(" AND ",$where);

 $paySub="
  SELECT note_perception_id,
         SUM(CASE WHEN statut<>'annule' THEN montant_converti_cdf ELSE 0 END) total_paye,
         COUNT(CASE WHEN statut<>'annule' THEN 1 END) nb_paiements,
         GROUP_CONCAT(CASE WHEN statut<>'annule' THEN CONCAT(DATE_FORMAT(date_paiement,'%d/%m/%Y'),' — ',FORMAT(montant_paye,2),' ',devise,' — ',IFNULL(reference_transaction,'-')) END SEPARATOR ' | ') details
  FROM paiements WHERE note_perception_id IS NOT NULL GROUP BY note_perception_id
 ";
 $sql="
 SELECT np.*,nd.numero_nd,nt.numero_nt,
        ".cpRapportNomContribuableSql("ct")." contribuable,
        ct.nif,ct.telephone,pr.nom province,ce.nom centre,s.nom_service,
        COALESCE(pp.total_paye,0) total_paye_reel,COALESCE(pp.nb_paiements,0) nb_paiements,pp.details
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
 ORDER BY COALESCE(np.date_emission,np.created_at) DESC,np.id DESC
 ";
 $stmt=$pdo->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
}
$totalDu=$totalPaye=0;
foreach($rows as $r){$totalDu+=(float)($r['montant_initial']?:$r['montant_total']);$totalPaye+=(float)$r['total_paye_reel'];}
$solde=max(0,$totalDu-$totalPaye);

cpRapportPageStart("Rapport par assujetti","Toutes les notes d’un contribuable, payées ou non payées.");
?>
<section class="rp-panel">
<form method="GET" class="rp-search-form">
 <label class="grow">Assujetti / NIF / téléphone<input type="text" name="search" required value="<?=cpRapportH($search)?>" placeholder="Ex. Société, nom, NIF, téléphone"></label>
 <label>Statut<select name="statut"><option value="tous" <?=$statut==='tous'?'selected':''?>>Toutes les notes</option><option value="payees" <?=$statut==='payees'?'selected':''?>>Payées</option><option value="non_payees" <?=$statut==='non_payees'?'selected':''?>>Non payées</option><option value="partielles" <?=$statut==='partielles'?'selected':''?>>Partielles</option></select></label>
 <label>Du<input type="date" name="date_debut" value="<?=cpRapportH($f['date_debut'])?>"></label>
 <label>Au<input type="date" name="date_fin" value="<?=cpRapportH($f['date_fin'])?>"></label>
 <button>Rechercher</button>
</form>
</section>
<?php if($search!==''):?>
<section class="rp-kpis"><article><small>Notes trouvées</small><strong><?=count($rows)?></strong></article><article><small>Total dû</small><strong><?=cpRapportMoney($totalDu)?></strong></article><article><small>Total payé</small><strong><?=cpRapportMoney($totalPaye)?></strong></article><article><small>Solde</small><strong><?=cpRapportMoney($solde)?></strong></article></section>
<section class="rp-panel"><div class="rp-table-wrap"><table class="rp-table"><thead><tr><th>Date</th><th>Assujetti</th><th>NT</th><th>ND</th><th>NP/NPF</th><th>Dû</th><th>Payé</th><th>Solde</th><th>Statut</th><th>Paiements</th></tr></thead><tbody>
<?php foreach($rows as $r):$du=(float)($r['montant_initial']?:$r['montant_total']);$pa=(float)$r['total_paye_reel'];$so=max(0,$du-$pa);?>
<tr><td><?=cpRapportDate($r['date_emission']?:$r['created_at'])?></td><td><b><?=cpRapportH($r['contribuable'])?></b><small>NIF: <?=cpRapportH($r['nif'])?></small></td><td><?=cpRapportH($r['numero_nt'])?></td><td><?=cpRapportH($r['numero_nd'])?></td><td><?=cpRapportH($r['numero_np'])?></td><td><?=cpRapportMoney($du)?></td><td><?=cpRapportMoney($pa)?></td><td><?=cpRapportMoney($so)?></td><td><?=cpRapportStatusBadge($r['statut'])?></td><td class="rp-details"><?=cpRapportH($r['details']?:'Aucun paiement')?></td></tr>
<?php endforeach;?>
<?php if(!$rows):?><tr><td colspan="10" class="rp-empty">Aucune note trouvée.</td></tr><?php endif;?>
</tbody></table></div></section>
<?php endif;?>
<?php cpRapportPageEnd(); ?>
