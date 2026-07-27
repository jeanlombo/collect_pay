<?php
function genererNumero($type_document, $province_id, $centre_id, $pdo)
{
    $annee = date('y');

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT * FROM document_sequences
        WHERE type_document = ?
        AND province_id = ?
        AND centre_id = ?
        AND annee = ?
        FOR UPDATE
    ");
    $stmt->execute([$type_document, $province_id, $centre_id, $annee]);
    $sequence = $stmt->fetch();

    if ($sequence) {
        $nouveauNumero = $sequence['dernier_numero'] + 1;
        $pdo->prepare("UPDATE document_sequences SET dernier_numero = ? WHERE id = ?")
            ->execute([$nouveauNumero, $sequence['id']]);
    } else {
        $nouveauNumero = 1;
        $pdo->prepare("
            INSERT INTO document_sequences
            (type_document, province_id, centre_id, annee, dernier_numero)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([$type_document, $province_id, $centre_id, $annee, $nouveauNumero]);
    }

    $province = $pdo->prepare("SELECT code_province FROM provinces WHERE id=?");
    $province->execute([$province_id]);
    $province = $province->fetch();

    $centre = $pdo->prepare("SELECT code_centre_short FROM centres WHERE id=?");
    $centre->execute([$centre_id]);
    $centre = $centre->fetch();

    $numero = str_pad($nouveauNumero, 6, "0", STR_PAD_LEFT);

    $pdo->commit();

    return "$type_document-{$province['code_province']}-{$centre['code_centre_short']}-$annee-$numero";
}
?>