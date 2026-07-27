<?php
/*
|--------------------------------------------------------------------------
| cOllect_Pay - Aperçu et édition document à corriger
|--------------------------------------------------------------------------
*/
require_once "../../auth/check_auth.php";

checkAuth();
requirePermission('corrections','create');

function corrSafe($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

function corrDetectDocument($numero){
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

function corrFetchByNumero(PDO $pdo,$numero){
    $m = corrDetectDocument($numero);
    if(!$m) return [null,null];

    $stmt = $pdo->prepare("SELECT * FROM `{$m['table']}` WHERE `{$m['field']}`=? LIMIT 1");
    $stmt->execute([$numero]);

    return [$m,$stmt->fetch(PDO::FETCH_ASSOC) ?: null];
}

function corrBlockedFields(){
    return [
        'id',
        'numero_nt','numero_nd','numero_np','numero_amr','numero_quittance','numero_avis',
        'note_taxation_id','note_debit_id','note_perception_id','apurement_id',
        'contribuable_id','centre_id','service_id',
        'user_taxateur_id','user_liquidateur_id','user_validateur_id','user_ordonnateur_id',
        'user_emission_id','user_validation_id','user_comptable_id',
        'created_at','date_emission','date_validation','date_signature_receptionniste','date_signature_comptable',
        'qr_hash','qr_data',
        'montant_total','montant_initial','montant_paye','montant_converti_cdf',
        'montant_penalite','montant_du','solde_restant','total_estime',
        'total_exigible','montant_acte','montant_frais_admin','montant_frais_tech',
        'montant_acquitte','montant_cdf','total_ligne_cdf','total_ligne',
        'montant_principal','penalite_assiette','penalite_recouvrement'
    ];
}

function corrEditableFields($type){
    if($type==='NT')  return ['exercice','statut','devise','taux_change'];
    if($type==='ND')  return ['observation','decision','statut','date_liquidation'];
    if($type==='NP')  return ['date_echeance','statut','declarant_nom','annotation_autorite','compte_bancaire'];
    if($type==='NPF') return ['date_echeance','statut','declarant_nom','annotation_autorite'];
    if($type==='AMR') return ['motif','statut','jours_retard'];
    if($type==='AVF') return ['annotation','statut'];
    if($type==='QT')  return ['nom_receptionniste','fonction_receptionniste','observation_signature'];

    return ['observation','motif','annotation','date_echeance','statut'];
}

function corrEditableContribuableFields(){
    return ['raison_sociale','nom','postnom','prenom','nif','rccm','telephone','adresse','ville'];
}

function corrLoadContribuable(PDO $pdo,$m,$doc){
    if(!$m || !$doc) return null;

    try{
        if($m['type']==='NT' && !empty($doc['contribuable_id'])){
            $s=$pdo->prepare("SELECT * FROM contribuables WHERE id=? LIMIT 1");
            $s->execute([$doc['contribuable_id']]);
            return $s->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        if($m['type']==='ND' && !empty($doc['note_taxation_id'])){
            $s=$pdo->prepare("SELECT c.* FROM notes_taxation nt JOIN contribuables c ON nt.contribuable_id=c.id WHERE nt.id=? LIMIT 1");
            $s->execute([$doc['note_taxation_id']]);
            return $s->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        if(($m['type']==='NP' || $m['type']==='NPF') && !empty($doc['note_debit_id'])){
            $s=$pdo->prepare("SELECT c.* FROM notes_debit nd JOIN notes_taxation nt ON nd.note_taxation_id=nt.id JOIN contribuables c ON nt.contribuable_id=c.id WHERE nd.id=? LIMIT 1");
            $s->execute([$doc['note_debit_id']]);
            return $s->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        if($m['type']==='QT' && !empty($doc['apurement_id'])){
            $s=$pdo->prepare("SELECT c.* FROM quittances q JOIN apurements ap ON q.apurement_id=ap.id JOIN notes_perception np ON ap.reference_id=np.id JOIN notes_debit nd ON np.note_debit_id=nd.id JOIN notes_taxation nt ON nd.note_taxation_id=nt.id JOIN contribuables c ON nt.contribuable_id=c.id WHERE q.id=? LIMIT 1");
            $s->execute([$doc['id']]);
            return $s->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        if($m['type']==='AMR' && !empty($doc['note_perception_id'])){
            $s=$pdo->prepare("SELECT c.* FROM notes_perception np JOIN notes_debit nd ON np.note_debit_id=nd.id JOIN notes_taxation nt ON nd.note_taxation_id=nt.id JOIN contribuables c ON nt.contribuable_id=c.id WHERE np.id=? LIMIT 1");
            $s->execute([$doc['note_perception_id']]);
            return $s->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        if($m['type']==='AVF' && !empty($doc['note_perception_id'])){
            $s=$pdo->prepare("SELECT c.* FROM notes_perception np JOIN notes_debit nd ON np.note_debit_id=nd.id JOIN notes_taxation nt ON nd.note_taxation_id=nt.id JOIN contribuables c ON nt.contribuable_id=c.id WHERE np.id=? LIMIT 1");
            $s->execute([$doc['note_perception_id']]);
            return $s->fetch(PDO::FETCH_ASSOC) ?: null;
        }

    }catch(Throwable $e){
        return null;
    }

    return null;
}

function corrColumnExists(PDO $pdo,$table,$column){
    try{
        $s=$pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $s->execute([$column]);
        return (bool)$s->fetch();
    }catch(Throwable $e){return false;}
}

function corrLoadComptesBancaires(PDO $pdo){
    foreach(['comptes_bancaires','banques_comptes','comptes_paiement'] as $t){
        try{
            $pdo->query("SELECT 1 FROM `$t` LIMIT 1");
            $where = corrColumnExists($pdo,$t,'statut') ? "WHERE statut IN ('actif','Actif','ACTIF',1)" : "";
            $rows=$pdo->query("SELECT * FROM `$t` $where ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
            return [$t,$rows];
        }catch(Throwable $e){}
    }
    return [null,[]];
}

function corrCompteLabel($c){
    $banque=$c['banque']??$c['nom_banque']??$c['intitule_banque']??'';
    $numero=$c['numero_compte']??$c['compte']??$c['iban']??'';
    $devise=$c['devise']??$c['monnaie']??'';
    $intitule=$c['intitule_compte']??$c['libelle']??'';
    return trim($banque.' '.$intitule.' ('.$numero.' / '.$devise.')');
}

$numero = $_GET['numero'] ?? null;
if (!$numero) die("Numéro document obligatoire.");

[$meta, $doc] = corrFetchByNumero($pdo, $numero);
if (!$meta || !$doc) die("Document introuvable.");

$contribuable = corrLoadContribuable($pdo, $meta, $doc);
[$tableComptes, $comptes] = corrLoadComptesBancaires($pdo);

$editableDoc = corrEditableFields($meta['type']);
$editableContrib = corrEditableContribuableFields();

$page_title = "Modifier document";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?= corrSafe($page_title) ?> | cOllect_Pay</title>
<link rel="stylesheet" href="/collect_pay/assets/css/admin.css">
<style>
.head{background:linear-gradient(135deg,#7c2d12,#f59e0b);color:#111827;padding:26px;border-radius:24px;margin-bottom:22px}
.head h2{margin:0;font-weight:1000}.head p{font-weight:900}
.locked{background:#f1f5f9!important;color:#64748b!important}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.reason{background:#fff7ed;border:1px solid #fdba74;padding:16px;border-radius:16px;margin-top:18px}
.small{color:#64748b;font-size:12px;font-weight:700}
label{display:block;font-weight:900;margin-top:10px}
input,select,textarea{width:100%;padding:12px;border:1px solid #d1d5db;border-radius:12px;font-weight:800}
textarea{min-height:110px}
.btn{display:inline-block;background:#0f3460;color:white;padding:10px 14px;border-radius:12px;text-decoration:none;font-weight:900;border:0;cursor:pointer}
.btn-gray{background:#e5e7eb;color:#111827}
@media(max-width:900px){.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="admin-layout">
<?php require_once "../../includes/sidebar.php"; ?>

<main class="main-content">
<?php require_once "../../includes/topbar.php"; ?>

<div class="head">
    <h2>Rectification administrative</h2>
    <p>Document : <strong><?= corrSafe($numero) ?></strong> — Type : <strong><?= corrSafe($meta['type']) ?></strong></p>
</div>

<form method="POST" action="/collect_pay/modules/corrections/correction_save.php">
<input type="hidden" name="numero_document" value="<?= corrSafe($numero) ?>">
<input type="hidden" name="type_document" value="<?= corrSafe($meta['type']) ?>">

<div class="panel">
    <h3>I. Aperçu du contribuable</h3>

    <?php if($contribuable): ?>
        <input type="hidden" name="contribuable_id" value="<?= (int)$contribuable['id'] ?>">
        <div class="grid">
            <?php foreach($editableContrib as $field): ?>
                <?php if(array_key_exists($field, $contribuable)): ?>
                    <div>
                        <label><?= corrSafe($field) ?></label>
                        <input type="text" name="contribuable[<?= corrSafe($field) ?>]" value="<?= corrSafe($contribuable[$field]) ?>">
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p>Aucun contribuable lié trouvé.</p>
    <?php endif; ?>
</div>

<div class="panel">
    <h3>II. Aperçu du document et champs éditables</h3>

    <div class="grid">
        <?php foreach($doc as $k => $v): ?>
            <?php if(is_numeric($k)) continue; ?>
            <?php
                $blocked = in_array($k, corrBlockedFields(), true);
                $editable = in_array($k, $editableDoc, true) && !$blocked;
            ?>
            <div>
                <label><?= corrSafe($k) ?> <?= $blocked ? '🔒' : '' ?></label>

                <?php if($editable): ?>
                    <?php if(strlen((string)$v) > 120 || in_array($k, ['observation','motif','annotation','annotation_autorite','observation_signature'])): ?>
                        <textarea name="document[<?= corrSafe($k) ?>]"><?= corrSafe($v) ?></textarea>
                    <?php else: ?>
                        <input type="text" name="document[<?= corrSafe($k) ?>]" value="<?= corrSafe($v) ?>">
                    <?php endif; ?>
                <?php else: ?>
                    <input class="locked" type="text" value="<?= corrSafe($v) ?>" disabled>
                    <?php if($blocked): ?>
                        <div class="small">Champ non modifiable / sécurisé.</div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="panel">
    <h3>III. Comptes bancaires autorisés</h3>

    <?php if(!empty($comptes) && in_array($meta['type'], ['NP','NPF'], true)): ?>
        <p class="small">Choisir uniquement un compte configuré dans le système.</p>

        <label>Compte bancaire à associer / remplacer</label>
        <select name="compte_bancaire_id">
            <option value="">Ne pas modifier</option>
            <?php foreach($comptes as $c): ?>
                <option value="<?= (int)$c['id'] ?>"><?= corrSafe(corrCompteLabel($c)) ?></option>
            <?php endforeach; ?>
        </select>

        <input type="hidden" name="table_comptes" value="<?= corrSafe($tableComptes) ?>">
    <?php else: ?>
        <div class="reason">Aucun compte bancaire à modifier pour ce document.</div>
    <?php endif; ?>
</div>

<div class="panel">
    <h3>IV. Raison obligatoire</h3>

    <div class="reason">
        <label>Raison de la modification</label>
        <textarea name="raison_modification" required placeholder="Ex : erreur de saisie, correction téléphone, remplacement compte bancaire autorisé..."></textarea>
    </div>

    <br>
    <button type="submit" class="btn">💾 Enregistrer la correction</button>
    <a href="/collect_pay/modules/corrections/correction_create.php" class="btn btn-gray">Annuler</a>
</div>

</form>

</main>
</div>
</body>
</html>
