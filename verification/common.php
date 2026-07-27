<?php
require_once __DIR__ . "/../config/database.php";

function vSafe($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function vMoney($v){ return number_format((float)$v, 2, ',', ' ') . ' CDF'; }

function vDocs(){
    return [
        'NT'=>['label'=>'Note de Taxation','table'=>'notes_taxation','numero'=>'numero_nt','amount'=>'total_estime','date'=>'created_at'],
        'ND'=>['label'=>'Note de Débit','table'=>'notes_debit','numero'=>'numero_nd','amount'=>'montant_total','date'=>'created_at'],

        // Important : pour les NP/NPF, on affiche le montant payé si disponible.
        'NP'=>['label'=>'Note de Perception','table'=>'notes_perception','numero'=>'numero_np','amount'=>'montant_paye','date'=>'date_emission'],
        'NPF'=>['label'=>'Note de Perception Fractionnée','table'=>'notes_perception','numero'=>'numero_np','amount'=>'montant_paye','date'=>'date_emission'],

        'AMR'=>['label'=>'Avis de Mise en Recouvrement','table'=>'amr','numero'=>'numero_amr','amount'=>'montant_total','date'=>'date_emission'],
        'QT'=>['label'=>'Quittance','table'=>'quittances','numero'=>'numero_quittance','amount'=>'montant_acquitte','date'=>'date_emission']
    ];
}

function vAmount($type, $doc, $meta){
    if(($type === 'NP' || $type === 'NPF') && isset($doc['montant_paye']) && (float)$doc['montant_paye'] > 0){
        return $doc['montant_paye'];
    }

    if(($type === 'NP' || $type === 'NPF') && isset($doc['montant_initial']) && (float)$doc['montant_initial'] > 0){
        return $doc['montant_initial'];
    }

    return $doc[$meta['amount']] ?? 0;
}

function vFind(PDO $pdo, string $type, string $numero): array {
    $docs = vDocs();
    $types = ($type && $type !== 'ALL' && isset($docs[$type])) ? [$type] : array_keys($docs);

    foreach($types as $t){
        $c = $docs[$t];

        try{
            $s = $pdo->prepare("SELECT * FROM `{$c['table']}` WHERE `{$c['numero']}`=? LIMIT 1");
            $s->execute([$numero]);
            $r = $s->fetch(PDO::FETCH_ASSOC);

            if($r) return [$t, $c, $r];

        }catch(Throwable $e){}
    }

    return [null, null, null];
}

function vContrib(PDO $pdo, string $type, array $doc): ?array {
    try{
        if($type==='NT' && !empty($doc['contribuable_id'])){
            $s=$pdo->prepare("SELECT * FROM contribuables WHERE id=? LIMIT 1");
            $s->execute([$doc['contribuable_id']]);
            return $s->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        if($type==='ND' && !empty($doc['note_taxation_id'])){
            $s=$pdo->prepare("
                SELECT c.*
                FROM notes_taxation nt
                JOIN contribuables c ON nt.contribuable_id=c.id
                WHERE nt.id=?
                LIMIT 1
            ");
            $s->execute([$doc['note_taxation_id']]);
            return $s->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        if(($type==='NP'||$type==='NPF') && !empty($doc['note_debit_id'])){
            $s=$pdo->prepare("
                SELECT c.*
                FROM notes_debit nd
                JOIN notes_taxation nt ON nd.note_taxation_id=nt.id
                JOIN contribuables c ON nt.contribuable_id=c.id
                WHERE nd.id=?
                LIMIT 1
            ");
            $s->execute([$doc['note_debit_id']]);
            return $s->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        if($type==='AMR' && !empty($doc['note_perception_id'])){
            $s=$pdo->prepare("
                SELECT c.*
                FROM notes_perception np
                JOIN notes_debit nd ON np.note_debit_id=nd.id
                JOIN notes_taxation nt ON nd.note_taxation_id=nt.id
                JOIN contribuables c ON nt.contribuable_id=c.id
                WHERE np.id=?
                LIMIT 1
            ");
            $s->execute([$doc['note_perception_id']]);
            return $s->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        if($type==='QT' && !empty($doc['apurement_id'])){
            $s=$pdo->prepare("
                SELECT c.*
                FROM quittances q
                JOIN apurements ap ON q.apurement_id=ap.id
                JOIN notes_perception np ON ap.reference_id=np.id
                JOIN notes_debit nd ON np.note_debit_id=nd.id
                JOIN notes_taxation nt ON nd.note_taxation_id=nt.id
                JOIN contribuables c ON nt.contribuable_id=c.id
                WHERE q.id=?
                LIMIT 1
            ");
            $s->execute([$doc['id']]);
            return $s->fetch(PDO::FETCH_ASSOC) ?: null;
        }

    }catch(Throwable $e){}

    return null;
}

function vName($c): string {
    if(!$c) return '-';

    if(!empty($c['raison_sociale'])) {
        return $c['raison_sociale'];
    }

    return trim(($c['nom']??'').' '.($c['postnom']??'').' '.($c['prenom']??'')) ?: '-';
}

function vCreateLog(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS verification_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            reference_verification VARCHAR(80) NOT NULL UNIQUE,
            type_document VARCHAR(30) NULL,
            numero_document VARCHAR(120) NULL,
            resultat ENUM('AUTHENTIQUE','NON_TROUVE') NOT NULL,
            ip_address VARCHAR(80) NULL,
            user_agent TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function vLog(PDO $pdo, ?string $type, string $numero, string $resultat): string {
    try{
        vCreateLog($pdo);

        $ref = "VERIFY-".date("Y")."-".str_pad((string)(time()%1000000000),9,"0",STR_PAD_LEFT);

        $s=$pdo->prepare("
            INSERT INTO verification_logs
            (reference_verification,type_document,numero_document,resultat,ip_address,user_agent)
            VALUES(?,?,?,?,?,?)
        ");

        $s->execute([
            $ref,
            $type,
            $numero,
            $resultat,
            $_SERVER['REMOTE_ADDR']??null,
            $_SERVER['HTTP_USER_AGENT']??null
        ]);

        return $ref;

    }catch(Throwable $e){
        return "VERIFY-".date("Y")."-".time();
    }
}
?>