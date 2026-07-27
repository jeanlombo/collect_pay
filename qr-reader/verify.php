<?php
header('Content-Type: application/json; charset=utf-8');

require_once "../config/database.php";
require_once "../core/secure_qr_engine.php";

$qrContent = trim($_POST['qr_content'] ?? '');

if ($qrContent === '') {
    echo json_encode([
        'valid' => false,
        'message' => 'Aucun QR Code reçu.'
    ]);
    exit;
}

$result = verifyEncryptedQrPayload($pdo, $qrContent);

$token = null;
$typeDocument = null;
$numeroDocument = null;

if (!empty($result['document_token'])) {
    $token = $result['document_token']['token'] ?? null;
    $typeDocument = $result['document_token']['type_document'] ?? null;
    $numeroDocument = $result['document_token']['numero_document'] ?? null;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO qr_verifications
        (
            token,
            type_document,
            numero_document,
            resultat,
            message,
            adresse_ip,
            user_agent
        )
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $token,
        $typeDocument,
        $numeroDocument,
        $result['valid'] ? 'authentique' : 'contrefait',
        $result['message'] ?? null,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
} catch (Exception $e) {
    // Ne bloque jamais la vérification si le journal échoue.
}

if (!$result['valid']) {
    echo json_encode([
        'valid' => false,
        'message' => $result['message']
    ]);
    exit;
}

$doc = $result['document_token'];

echo json_encode([
    'valid' => true,
    'message' => 'Document authentique.',
    'document' => [
        'type_document' => $doc['type_document'],
        'numero_document' => $doc['numero_document'],
        'montant' => number_format((float)($doc['montant'] ?? 0), 2, ',', ' ') . ' CDF',
        'statut' => strtoupper($doc['statut'] ?? '-')
    ]
]);
exit;