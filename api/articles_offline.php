<?php
require_once __DIR__ . "/../config/database.php";

header('Content-Type: application/json; charset=utf-8');

function columnExists(PDO $pdo, $table, $column)
{
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $stmt->execute([$column]);
        return (bool)$stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
}

function getUsdRate(PDO $pdo)
{
    $tables = [
        'taux_change',
        'taux_changes',
        'taux_devise',
        'devises'
    ];

    foreach ($tables as $table) {
        try {
            $pdo->query("SELECT 1 FROM `$table` LIMIT 1");

            $deviseCol = columnExists($pdo, $table, 'devise')
                ? 'devise'
                : null;

            $rateCols = [
                'taux',
                'taux_change',
                'valeur',
                'montant',
                'cours'
            ];

            foreach ($rateCols as $rateCol) {
                if (!columnExists($pdo, $table, $rateCol)) {
                    continue;
                }

                if ($deviseCol) {
                    $stmt = $pdo->prepare("
                        SELECT `$rateCol` AS taux
                        FROM `$table`
                        WHERE UPPER(`$deviseCol`) = 'USD'
                        ORDER BY id DESC
                        LIMIT 1
                    ");
                    $stmt->execute();
                } else {
                    $stmt = $pdo->query("
                        SELECT `$rateCol` AS taux
                        FROM `$table`
                        ORDER BY id DESC
                        LIMIT 1
                    ");
                }

                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($row && (float)$row['taux'] > 0) {
                    return (float)$row['taux'];
                }
            }

        } catch (Exception $e) {
            // On continue avec la table suivante
        }
    }

    return 1;
}

try {
    $usdRate = getUsdRate($pdo);

    $whereActif = "";

    if (columnExists($pdo, 'articles_budgetaires', 'actif')) {
        $whereActif = "WHERE actif = 1";
    }

    $stmt = $pdo->query("
        SELECT 
            id,
            code_article,
            secteur,
            nature_acte,
            fait_generateur,
            periodicite,
            type_taux,
            taux_acte,
            frais_administratif,
            frais_technique,
            unite,
            devise_base,
            direction_id,
            service_id
        FROM articles_budgetaires
        $whereActif
        ORDER BY nature_acte ASC
    ");

    $items = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {

        $devise = strtoupper($row['devise_base'] ?? 'CDF');

        $row['taux_change'] = ($devise === 'USD')
            ? $usdRate
            : 1;

        $row['taux_acte'] = (float)($row['taux_acte'] ?? 0);
        $row['frais_administratif'] = (float)($row['frais_administratif'] ?? 0);
        $row['frais_technique'] = (float)($row['frais_technique'] ?? 0);

        $items[] = $row;
    }

    echo json_encode([
        'success' => true,
        'taux_usd' => $usdRate,
        'items' => $items
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'items' => []
    ], JSON_UNESCAPED_UNICODE);
}
?>