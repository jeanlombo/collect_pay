<?php
require_once __DIR__ . "/config/database.php";

$type = strtoupper(trim($_GET['type_document'] ?? ''));
$numero = trim($_GET['numero_document'] ?? '');

$documents = [
    'NT' => ['label'=>'Note de Taxation','table'=>'notes_taxation','numero'=>'numero_nt','amount'=>'total_estime','date'=>'created_at'],
    'ND' => ['label'=>'Note de Débit','table'=>'notes_debit','numero'=>'numero_nd','amount'=>'montant_total','date'=>'created_at'],
    'NP' => ['label'=>'Note de Perception','table'=>'notes_perception','numero'=>'numero_np','amount'=>'montant_total','date'=>'date_emission'],
    'NPF'=> ['label'=>'Note de Perception Fractionnée','table'=>'notes_perception','numero'=>'numero_np','amount'=>'montant_total','date'=>'date_emission'],
    'AMR'=> ['label'=>'Avis de Mise en Recouvrement','table'=>'amr','numero'=>'numero_amr','amount'=>'montant_total','date'=>'date_emission'],
    'QT' => ['label'=>'Quittance','table'=>'quittances','numero'=>'numero_quittance','amount'=>'montant_acquitte','date'=>'date_emission'],
];

function gSafe($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function gMoney($v){ return number_format((float)$v, 2, ',', ' ') . ' CDF'; }

function gFindDoc(PDO $pdo, array $docs, string $type, string $numero): array {
    if ($numero === '') return [null, null];
    $types = ($type !== '' && $type !== 'ALL' && isset($docs[$type])) ? [$type] : array_keys($docs);

    foreach ($types as $t) {
        $cfg = $docs[$t];
        try {
            $stmt = $pdo->prepare("SELECT * FROM `{$cfg['table']}` WHERE `{$cfg['numero']}`=? LIMIT 1");
            $stmt->execute([$numero]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) return [$t, $row];
        } catch(Throwable $e) {}
    }
    return [null, null];
}

function gLoadContribuable(PDO $pdo, string $type, array $doc): ?array {
    try {
        if ($type === 'NT' && !empty($doc['contribuable_id'])) {
            $s=$pdo->prepare("SELECT * FROM contribuables WHERE id=? LIMIT 1");
            $s->execute([$doc['contribuable_id']]);
            return $s->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        if ($type === 'ND' && !empty($doc['note_taxation_id'])) {
            $s=$pdo->prepare("SELECT c.* FROM notes_taxation nt JOIN contribuables c ON nt.contribuable_id=c.id WHERE nt.id=? LIMIT 1");
            $s->execute([$doc['note_taxation_id']]);
            return $s->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        if (($type === 'NP' || $type === 'NPF') && !empty($doc['note_debit_id'])) {
            $s=$pdo->prepare("SELECT c.* FROM notes_debit nd JOIN notes_taxation nt ON nd.note_taxation_id=nt.id JOIN contribuables c ON nt.contribuable_id=c.id WHERE nd.id=? LIMIT 1");
            $s->execute([$doc['note_debit_id']]);
            return $s->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        if ($type === 'AMR' && !empty($doc['note_perception_id'])) {
            $s=$pdo->prepare("SELECT c.* FROM notes_perception np JOIN notes_debit nd ON np.note_debit_id=nd.id JOIN notes_taxation nt ON nd.note_taxation_id=nt.id JOIN contribuables c ON nt.contribuable_id=c.id WHERE np.id=? LIMIT 1");
            $s->execute([$doc['note_perception_id']]);
            return $s->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        if ($type === 'QT' && !empty($doc['apurement_id'])) {
            $s=$pdo->prepare("SELECT c.* FROM quittances q JOIN apurements ap ON q.apurement_id=ap.id JOIN notes_perception np ON ap.reference_id=np.id JOIN notes_debit nd ON np.note_debit_id=nd.id JOIN notes_taxation nt ON nd.note_taxation_id=nt.id JOIN contribuables c ON nt.contribuable_id=c.id WHERE q.id=? LIMIT 1");
            $s->execute([$doc['id']]);
            return $s->fetch(PDO::FETCH_ASSOC) ?: null;
        }
    } catch(Throwable $e) {}
    return null;
}

function gName($c): string {
    if (!$c) return '-';
    if (!empty($c['raison_sociale'])) return $c['raison_sociale'];
    return trim(($c['nom']??'').' '.($c['postnom']??'').' '.($c['prenom']??'')) ?: '-';
}

$found=false; $error=''; $result=null; $meta=null; $contribuable=null;

if ($numero !== '') {
    [$detectedType, $result] = gFindDoc($pdo, $documents, $type, $numero);
    if ($result) {
        $found=true;
        $type=$detectedType;
        $meta=$documents[$type];
        $contribuable=gLoadContribuable($pdo, $type, $result);
    } else {
        $error="Aucun document trouvé avec ce numéro dans cOllect_Pay.";
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Gateway de vérification | cOllect_Pay</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
*{box-sizing:border-box}body{margin:0;background:#f4f7fb;font-family:Segoe UI,Arial,sans-serif;color:#0f172a}.header{background:linear-gradient(135deg,#06152b,#0f3460);color:white;padding:26px 18px}.wrap{max-width:1050px;margin:auto}.brand{display:flex;align-items:center;gap:12px}.logo{width:46px;height:46px;border-radius:15px;background:#f6b21a;color:#06152b;display:flex;align-items:center;justify-content:center;font-weight:1000}h1{margin:0;font-size:26px}.sub{margin:6px 0 0;color:#dbeafe;font-weight:700}.card{background:white;border:1px solid #e5e7eb;border-radius:22px;padding:22px;box-shadow:0 14px 34px rgba(15,23,42,.08);margin-top:20px}.form{display:grid;grid-template-columns:1fr 2fr auto;gap:12px}label{display:block;font-weight:900;margin-bottom:6px}select,input{width:100%;padding:13px;border:1px solid #d1d5db;border-radius:13px;font-weight:800}button,.btn{background:#0f3460;color:white;border:none;border-radius:13px;padding:13px 17px;font-weight:1000;text-decoration:none;cursor:pointer;display:inline-block}.btn-gray{background:#e5e7eb;color:#111827}.ok{background:#dcfce7;color:#166534;border:1px solid #86efac;padding:14px;border-radius:16px;font-weight:1000}.bad{background:#fee2e2;color:#991b1b;border:1px solid #fecaca;padding:14px;border-radius:16px;font-weight:1000}.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}.info{background:#f8fafc;border:1px solid #e5e7eb;border-radius:16px;padding:14px}.info small{display:block;color:#64748b;font-weight:900;margin-bottom:5px}.table{width:100%;border-collapse:collapse;margin-top:10px}.table th,.table td{border-bottom:1px solid #e5e7eb;padding:10px;text-align:left;font-size:13px}.table th{background:#f8fafc;color:#0f3460}.footer{text-align:center;color:#64748b;font-weight:700;padding:25px}@media(max-width:850px){.form,.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="header"><div class="wrap"><div class="brand"><div class="logo">cP</div><div><h1>Gateway de vérification documentaire</h1><p class="sub">Vérification publique des documents émis par cOllect_Pay.</p></div></div></div></div>
<div class="wrap">
<div class="card">
<form method="GET" class="form">
<div><label>Type document</label><select name="type_document">
<option value="ALL" <?= $type===''||$type==='ALL'?'selected':'' ?>>Tous les documents</option>
<option value="NT" <?= $type==='NT'?'selected':'' ?>>Note de Taxation</option>
<option value="ND" <?= $type==='ND'?'selected':'' ?>>Note de Débit</option>
<option value="NP" <?= $type==='NP'?'selected':'' ?>>Note de Perception</option>
<option value="NPF" <?= $type==='NPF'?'selected':'' ?>>NP Fractionnée</option>
<option value="AMR" <?= $type==='AMR'?'selected':'' ?>>AMR</option>
<option value="QT" <?= $type==='QT'?'selected':'' ?>>Quittance</option>
</select></div>
<div><label>Numéro du document</label><input name="numero_document" value="<?= gSafe($numero) ?>" placeholder="Ex : NP-BU-CPR-26-000012" required></div>
<div style="align-self:end"><button type="submit">Rechercher</button></div>
</form>
</div>
<?php if($numero !== ''): ?>
<div class="card">
<?php if($found): ?>
<div class="ok">✅ Document authentique trouvé dans le système cOllect_Pay.</div><br>
<div class="grid">
<div class="info"><small>Type</small><strong><?= gSafe($meta['label']) ?></strong></div>
<div class="info"><small>Numéro</small><strong><?= gSafe($result[$meta['numero']] ?? $numero) ?></strong></div>
<div class="info"><small>Statut</small><strong><?= gSafe(strtoupper($result['statut'] ?? '-')) ?></strong></div>
<div class="info"><small>Montant</small><strong><?= gMoney($result[$meta['amount']] ?? 0) ?></strong></div>
<div class="info"><small>Date</small><strong><?= gSafe($result[$meta['date']] ?? '-') ?></strong></div>
<div class="info"><small>Contribuable</small><strong><?= gSafe(gName($contribuable)) ?></strong></div>
</div>
<br><h3>Aperçu du document</h3>
<table class="table"><tr><th>Champ</th><th>Valeur</th></tr>
<?php foreach($result as $k=>$v): if(is_numeric($k)) continue; if(in_array($k,['qr_hash','qr_data','password','signature_receptionniste'],true)) continue; ?>
<tr><td><?= gSafe($k) ?></td><td><?= gSafe($v) ?></td></tr>
<?php endforeach; ?>
</table>
<?php else: ?>
<div class="bad">⛔ <?= gSafe($error) ?></div><p>Ce numéro peut être faux, mal saisi ou ne pas encore être enregistré dans le système.</p>
<?php endif; ?>
</div>
<?php endif; ?>
<div class="footer">cOllect_Pay — Vérification documentaire sécurisée</div>
</div>
</body>
</html>
