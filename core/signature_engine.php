<?php
require_once(__DIR__ . "/../config/constants.php");

function genererHashDocument($data)
{
    return hash('sha256', json_encode($data));
}

function signerDocument($document_type, $document_id, $data, $user_dg_id, $pdo)
{
    $hash_document = genererHashDocument($data);

    $signature_hash = hash('sha256', $hash_document . CLE_SIGNATURE_DG);

    $stmt = $pdo->prepare("
        INSERT INTO signatures_numeriques
        (document_type, document_id, user_dg_id, hash_document, signature_hash)
        VALUES (?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $document_type,
        $document_id,
        $user_dg_id,
        $hash_document,
        $signature_hash
    ]);

    return $signature_hash;
}

function verifierSignature($document_type, $document_id, $pdo)
{
    $stmt = $pdo->prepare("
        SELECT * FROM signatures_numeriques
        WHERE document_type = ?
        AND document_id = ?
    ");

    $stmt->execute([$document_type, $document_id]);
    return $stmt->fetch();
}
?>