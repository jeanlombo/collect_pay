<?php
require_once __DIR__ . "/../config/app.php";

/**
 * Génère la signature serveur du document.
 */
function generateQrSignature($type_document, $numero_document, $montant, $token)
{
    return hash_hmac(
        'sha256',
        $type_document . '|' . $numero_document . '|' . (float)$montant . '|' . $token,
        QR_SECRET_KEY
    );
}

/**
 * Crée ou récupère le token officiel du document.
 */
function getOrCreateDocumentToken($pdo, $type_document, $numero_document, $montant = 0)
{
    $stmt = $pdo->prepare("
        SELECT token
        FROM document_tokens
        WHERE type_document = ?
        AND numero_document = ?
        AND statut = 'actif'
        LIMIT 1
    ");
    $stmt->execute([$type_document, $numero_document]);
    $existing = $stmt->fetch();

    if ($existing) {
        return $existing['token'];
    }

    $token = bin2hex(random_bytes(16));

    $signature = generateQrSignature(
        $type_document,
        $numero_document,
        $montant,
        $token
    );

    $stmt = $pdo->prepare("
        INSERT INTO document_tokens
        (
            type_document,
            numero_document,
            token,
            signature_hash,
            hash_signature,
            montant,
            statut,
            created_at
        )
        VALUES (?, ?, ?, ?, ?, ?, 'actif', NOW())
    ");

    $stmt->execute([
        $type_document,
        $numero_document,
        $token,
        $signature,
        $signature,
        $montant
    ]);

    return $token;
}

/**
 * Vérifie la signature serveur.
 */
function verifierSignatureDocument($doc)
{
    if (
        empty($doc['type_document']) ||
        empty($doc['numero_document']) ||
        empty($doc['token']) ||
        empty($doc['signature_hash'])
    ) {
        return false;
    }

    $expected = generateQrSignature(
        $doc['type_document'],
        $doc['numero_document'],
        $doc['montant'] ?? 0,
        $doc['token']
    );

    return hash_equals($expected, $doc['signature_hash']);
}

/**
 * Chiffre un contenu QR court.
 */
function encryptQrPayload($payload)
{
    $iv = random_bytes(16);

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);

    $cipherText = openssl_encrypt(
        $json,
        'AES-256-CBC',
        QR_ENCRYPTION_KEY,
        OPENSSL_RAW_DATA,
        $iv
    );

    if ($cipherText === false) {
        return false;
    }

    return 'CP:' . base64_encode($iv . $cipherText);
}

/**
 * Déchiffre un QR Collect_Pay.
 */
function decryptQrPayload($qrContent)
{
    if (strpos($qrContent, 'CP:') !== 0) {
        return false;
    }

    $raw = base64_decode(substr($qrContent, 3), true);

    if (!$raw || strlen($raw) <= 16) {
        return false;
    }

    $iv = substr($raw, 0, 16);
    $cipherText = substr($raw, 16);

    $json = openssl_decrypt(
        $cipherText,
        'AES-256-CBC',
        QR_ENCRYPTION_KEY,
        OPENSSL_RAW_DATA,
        $iv
    );

    if (!$json) {
        return false;
    }

    return json_decode($json, true);
}

/**
 * Construit le contenu QR chiffré.
 * Le QR contient seulement le token court.
 */
function buildEncryptedQrContent($pdo, $type_document, $numero_document, $montant = 0)
{
    $token = getOrCreateDocumentToken(
        $pdo,
        $type_document,
        $numero_document,
        $montant
    );

    return encryptQrPayload([
        't' => $token
    ]);
}

/**
 * Vérifie un contenu QR scanné par le lecteur Collect_Pay.
 */
function verifyEncryptedQrPayload($pdo, $qrContent)
{
    $payload = decryptQrPayload($qrContent);

    if (!$payload || empty($payload['t'])) {
        return [
            'valid' => false,
            'message' => 'QR Code non reconnu par Collect_Pay.'
        ];
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM document_tokens
        WHERE token = ?
        AND statut = 'actif'
        LIMIT 1
    ");
    $stmt->execute([$payload['t']]);
    $doc = $stmt->fetch();

    if (!$doc) {
        return [
            'valid' => false,
            'message' => 'Document non retrouvé dans le système officiel.'
        ];
    }

    if (!verifierSignatureDocument($doc)) {
        return [
            'valid' => false,
            'message' => 'Signature serveur invalide. Document contrefait.'
        ];
    }

    return [
        'valid' => true,
        'message' => 'Document authentique.',
        'document_token' => $doc
    ];
}
?>