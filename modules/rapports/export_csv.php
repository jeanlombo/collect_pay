<?php
require_once "_rapport_bootstrap.php";

$type=trim((string)($_GET['type']??'paiements'));
$date_debut=trim((string)($_GET['date_debut']??date('Y-m-01')));
$date_fin=trim((string)($_GET['date_fin']??date('Y-m-d')));

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="collectpay_'.$type.'_'.date('Ymd_His').'.csv"');
echo "\xEF\xBB\xBF";
$out=fopen('php://output','w');

if($type==='paiements'){
 fputcsv($out,['Date','Document','Montant','Devise','Montant CDF','Référence','Statut'],';');
 $stmt=$pdo->prepare("
  SELECT p.date_paiement,COALESCE(np.numero_np,fr.numero_fraction,'-') document,
         p.montant_paye,p.devise,p.montant_converti_cdf,p.reference_transaction,p.statut
  FROM paiements p
  LEFT JOIN notes_perception np ON p.note_perception_id=np.id
  LEFT JOIN notes_perception_fractions fr ON p.fraction_id=fr.id
  WHERE p.date_paiement BETWEEN ? AND ?
  ORDER BY p.date_paiement DESC
 ");
 $stmt->execute([$date_debut,$date_fin]);
 foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r)fputcsv($out,$r,';');
}elseif($type==='penalites'){
 fputcsv($out,['Date','Type','Référence type','Référence ID','Base','Taux','Pénalité','Jours retard','Statut'],';');
 $stmt=$pdo->prepare("
  SELECT date_application,type,reference_type,reference_id,montant_base,taux_applique,montant_penalite,jours_retard,statut
  FROM penalites_historique WHERE date_application BETWEEN ? AND ? ORDER BY date_application DESC
 ");
 $stmt->execute([$date_debut,$date_fin]);
 foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r)fputcsv($out,$r,';');
}else{
 fputcsv($out,['Erreur'],';');
 fputcsv($out,['Type d’export non supporté. Utiliser paiements ou penalites.'],';');
}
fclose($out);
exit;
