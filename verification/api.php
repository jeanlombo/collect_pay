<?php
require_once __DIR__."/common.php"; header("Content-Type: application/json; charset=utf-8");
$type=strtoupper(trim($_GET['type_document']??'ALL')); $numero=trim($_GET['numero_document']??'');
[$foundType,$meta,$doc]=vFind($pdo,$type,$numero); $contrib=$doc?vContrib($pdo,$foundType,$doc):null;
if($doc){ vLog($pdo,$foundType,$numero,'AUTHENTIQUE'); echo json_encode(['status'=>'AUTHENTIQUE','type'=>$foundType,'label'=>$meta['label'],'numero'=>$doc[$meta['numero']]??$numero,'contribuable'=>vName($contrib),'montant'=>$doc[$meta['amount']]??0,'etat'=>$doc['statut']??null,'date'=>$doc[$meta['date']]??null], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT); }
else{ vLog($pdo,$type,$numero,'NON_TROUVE'); echo json_encode(['status'=>'NON_TROUVE','numero'=>$numero,'message'=>'Document introuvable'], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT); }
?>