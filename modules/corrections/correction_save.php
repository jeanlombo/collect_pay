<?php
/*
|--------------------------------------------------------------------------
| cOllect_Pay - Sauvegarde correction document
|--------------------------------------------------------------------------
*/
require_once "../../auth/check_auth.php";

checkAuth();
requirePermission('corrections','create');

function corrCurrentUserId(PDO $pdo): ?int {
    $id = (int)($_SESSION['user_id'] ?? 0);
    if ($id > 0) return $id;

    $email = trim((string)($_SESSION['email'] ?? $_SESSION['user_email'] ?? ''));
    if ($email !== '') {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $id = (int)($row['id'] ?? 0);
        if ($id > 0) {
            $_SESSION['user_id'] = $id;
            return $id;
        }
    }
    return null;
}


function corrDetectDocumentSave($numero){
    $n = strtoupper(trim((string)$numero));

    if (strpos($n,'NT') === 0)  return ['type'=>'NT','table'=>'notes_taxation','field'=>'numero_nt'];
    if (strpos($n,'ND') === 0)  return ['type'=>'ND','table'=>'notes_debit','field'=>'numero_nd'];
    if (strpos($n,'NPF') === 0) return ['type'=>'NPF','table'=>'notes_perception','field'=>'numero_np'];
    if (strpos($n,'NP') === 0)  return ['type'=>'NP','table'=>'notes_perception','field'=>'numero_np'];
    if (strpos($n,'QT') === 0)  return ['type'=>'QT','table'=>'quittances','field'=>'numero_quittance'];
    if (strpos($n,'AMR') === 0) return ['type'=>'AMR','table'=>'amr','field'=>'numero_amr'];
    if (strpos($n,'AVF') === 0) return ['type'=>'AVF','table'=>'avis_fractionnement','field'=>'numero_avis'];

    return null;
}

function corrAllowedFieldsSave($type){
    if($type==='NT')  return ['exercice','statut','devise','taux_change'];
    if($type==='ND')  return ['observation','decision','statut','date_liquidation'];
    if($type==='NP')  return ['date_echeance','statut','declarant_nom','annotation_autorite','compte_bancaire'];
    if($type==='NPF') return ['date_echeance','statut','declarant_nom','annotation_autorite'];
    if($type==='AMR') return ['motif','statut','jours_retard'];
    if($type==='AVF') return ['annotation','statut'];
    if($type==='QT')  return ['nom_receptionniste','fonction_receptionniste','observation_signature'];
    return [];
}

function corrAllowedContribSave(){
    return ['raison_sociale','nom','postnom','prenom','nif','rccm','telephone','adresse','ville'];
}

function corrColumnExistsSave(PDO $pdo,$table,$column){
    try{
        $s=$pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $s->execute([$column]);
        return (bool)$s->fetch();
    }catch(Throwable $e){return false;}
}

function corrGetCompteLabelSave(PDO $pdo,$table,$id){
    if(!$table || !$id) return null;
    try{
        $s=$pdo->prepare("SELECT * FROM `$table` WHERE id=? LIMIT 1");
        $s->execute([$id]);
        $c=$s->fetch(PDO::FETCH_ASSOC);
        if(!$c) return null;

        $banque=$c['banque']??$c['nom_banque']??$c['intitule_banque']??'';
        $numero=$c['numero_compte']??$c['compte']??$c['iban']??'';
        $devise=$c['devise']??$c['monnaie']??'';
        $intitule=$c['intitule_compte']??$c['libelle']??'';

        return trim($banque.' '.$intitule.' ('.$numero.' / '.$devise.')');
    }catch(Throwable $e){
        return null;
    }
}

$numero = trim($_POST['numero_document'] ?? '');
$type = trim($_POST['type_document'] ?? '');
$raison = trim($_POST['raison_modification'] ?? '');

if($numero === '' || $type === '' || $raison === ''){
    die("Correction impossible : informations obligatoires manquantes.");
}

$meta = corrDetectDocumentSave($numero);
if(!$meta){
    die("Type de document non reconnu.");
}

$stmt = $pdo->prepare("SELECT * FROM `{$meta['table']}` WHERE `{$meta['field']}`=? LIMIT 1");
$stmt->execute([$numero]);
$doc = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$doc){
    die("Document introuvable.");
}

$ancienne = [];
$nouvelle = [];

try{
    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | 1. Correction du contribuable lié
    |--------------------------------------------------------------------------
    */
    $contribId = isset($_POST['contribuable_id']) ? (int)$_POST['contribuable_id'] : 0;

    if($contribId > 0 && !empty($_POST['contribuable']) && is_array($_POST['contribuable'])){
        $stmt = $pdo->prepare("SELECT * FROM contribuables WHERE id=? LIMIT 1");
        $stmt->execute([$contribId]);
        $oldContrib = $stmt->fetch(PDO::FETCH_ASSOC);

        if($oldContrib){
            $sets = [];
            $params = [];

            foreach($_POST['contribuable'] as $field => $value){
                if(!in_array($field, corrAllowedContribSave(), true)) continue;
                if(!array_key_exists($field, $oldContrib)) continue;

                $old = $oldContrib[$field] ?? null;
                $new = trim((string)$value);

                if((string)$old !== (string)$new){
                    $ancienne['contribuable'][$field] = $old;
                    $nouvelle['contribuable'][$field] = $new;
                    $sets[] = "`$field`=?";
                    $params[] = $new;
                }
            }

            if(!empty($sets)){
                $params[] = $contribId;
                $sql = "UPDATE contribuables SET ".implode(",",$sets)." WHERE id=?";
                $pdo->prepare($sql)->execute($params);
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 2. Correction du document
    |--------------------------------------------------------------------------
    */
    $allowedDoc = corrAllowedFieldsSave($meta['type']);
    $sets = [];
    $params = [];

    if(!empty($_POST['document']) && is_array($_POST['document'])){
        foreach($_POST['document'] as $field => $value){
            if(!in_array($field, $allowedDoc, true)) continue;
            if(!array_key_exists($field, $doc)) continue;

            $old = $doc[$field] ?? null;
            $new = trim((string)$value);

            if((string)$old !== (string)$new){
                $ancienne['document'][$field] = $old;
                $nouvelle['document'][$field] = $new;
                $sets[] = "`$field`=?";
                $params[] = $new;
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 3. Remplacement compte bancaire autorisé sur NP / NPF
    |--------------------------------------------------------------------------
    */
    $compteId = isset($_POST['compte_bancaire_id']) ? (int)$_POST['compte_bancaire_id'] : 0;
    $tableComptes = trim($_POST['table_comptes'] ?? '');

    if($compteId > 0 && $tableComptes !== '' && in_array($meta['type'], ['NP','NPF'], true)){
        $labelCompte = corrGetCompteLabelSave($pdo,$tableComptes,$compteId);

        if($labelCompte !== null && array_key_exists('compte_bancaire', $doc)){
            $old = $doc['compte_bancaire'] ?? null;
            $new = $labelCompte;

            if((string)$old !== (string)$new){
                $ancienne['document']['compte_bancaire'] = $old;
                $nouvelle['document']['compte_bancaire'] = $new;
                $sets[] = "`compte_bancaire`=?";
                $params[] = $new;
            }

            if(array_key_exists('banque_id', $doc)){
                $ancienne['document']['banque_id'] = $doc['banque_id'] ?? null;
                $nouvelle['document']['banque_id'] = $compteId;
                $sets[] = "`banque_id`=?";
                $params[] = $compteId;
            }
        }
    }

    if(!empty($sets)){
        $params[] = $doc['id'];
        $sql = "UPDATE `{$meta['table']}` SET ".implode(",",$sets)." WHERE id=?";
        $pdo->prepare($sql)->execute($params);
    }

    /*
    |--------------------------------------------------------------------------
    | 4. Historique correction
    |--------------------------------------------------------------------------
    */
    if(empty($ancienne) && empty($nouvelle)){
        throw new Exception("Aucune modification détectée.");
    }

    $stmt = $pdo->prepare("
        INSERT INTO corrections_documents
        (
            type_document,
            numero_document,
            reference_table,
            reference_id,
            raison_modification,
            ancienne_valeur,
            nouvelle_valeur,
            motif,
            user_id,
            date_modification
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $stmt->execute([
        $meta['type'],
        $numero,
        $meta['table'],
        $doc['id'],
        $raison,
        json_encode($ancienne, JSON_UNESCAPED_UNICODE),
        json_encode($nouvelle, JSON_UNESCAPED_UNICODE),
        $raison,
        corrCurrentUserId($pdo)
    ]);

    $pdo->commit();

    header("Location: corrections_list.php?updated=1");
    exit;

}catch(Throwable $e){
    if($pdo->inTransaction()){
        $pdo->rollBack();
    }

    die("Erreur correction : ".$e->getMessage());
}
