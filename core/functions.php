<?php
function redirect($url) {
    header("Location: $url");
    exit;
}
function formatMoney($amount) {
    return number_format((float)$amount, 2, ',', ' ');
}
function auditLog(
    $pdo,
    $userId,
    $action,
    $module,
    $referenceDocument = null,
    $details = null
) {

    try {

        $tableExiste = $pdo->query("
            SHOW TABLES LIKE 'audit_logs'
        ");

        if ($tableExiste->rowCount() == 0) {
            return;
        }

        $stmt = $pdo->prepare("
            INSERT INTO audit_logs
            (
                user_id,
                action,
                module,
                reference_document,
                details
            )
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $userId,
            $action,
            $module,
            $referenceDocument,
            $details
        ]);

    } catch (Exception $e) {

        /*
         * Ne jamais bloquer le système
         * si l'audit échoue.
         */
        return;
    }
}
function enregistrerImpressionDocument(
    $pdo,
    $type_document,
    $numero_document
) {

    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS total
        FROM impressions_documents
        WHERE type_document = ?
        AND numero_document = ?
    ");

    $stmt->execute([
        $type_document,
        $numero_document
    ]);

    $nombreImpressions = (int)$stmt->fetch()['total'];

    $estSuperAdmin =
        isset($_SESSION['role'])
        && $_SESSION['role'] === 'SUPER_ADMIN';

    /*
     * Original
     * Duplicata 1
     * Duplicata 2
     */

    if (!$estSuperAdmin && $nombreImpressions >= 3) {

        auditLog(
            $pdo,
            $_SESSION['user_id'] ?? null,
            'REIMPRESSION_REFUSEE',
            'SECURITE',
            $numero_document,
            'Limite de duplicata atteinte'
        );

        die("
            <h3>Réimpression refusée</h3>
            <p>
                Ce document a déjà atteint le nombre maximal
                de duplicatas autorisés.
            </p>
        ");
    }

    $stmt = $pdo->prepare("
        INSERT INTO impressions_documents
        (
            type_document,
            numero_document,
            user_id,
            ip_address
        )
        VALUES (?, ?, ?, ?)
    ");

    $stmt->execute([
        $type_document,
        $numero_document,
        $_SESSION['user_id'] ?? null,
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);

    auditLog(
        $pdo,
        $_SESSION['user_id'] ?? null,
        'IMPRESSION_DOCUMENT',
        $type_document,
        $numero_document,
        'Impression ou réimpression du document'
    );

    if ($nombreImpressions === 0) {
        return '';
    }

    return 'DUPLICATA ' . $nombreImpressions;
}