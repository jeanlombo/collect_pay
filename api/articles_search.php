<?php
/*
|--------------------------------------------------------------------------
| cOllect_Pay - API Recherche Articles Budgétaires
|--------------------------------------------------------------------------
| Utilisé par la taxation intelligente :
| - Filtre ministère/direction
| - Filtre service
| - Recherche code, acte, catégorie
| - Uniquement directions visibles taxation
|--------------------------------------------------------------------------
*/

require_once "../config/database.php";
require_once "../config/security.php";

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Session expirée.'
        ]);
        exit;
    }

    $q = trim($_GET['q'] ?? '');
    $direction_id = isset($_GET['direction_id']) ? (int)$_GET['direction_id'] : 0;
    $service_id = isset($_GET['service_id']) ? (int)$_GET['service_id'] : 0;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;

    if ($limit <= 0 || $limit > 100) {
        $limit = 50;
    }

    /*
    |--------------------------------------------------------------------------
    | Colonnes dynamiques pour compatibilité AwardSpace / local
    |--------------------------------------------------------------------------
    */

    function cpApiColumns(PDO $pdo, string $table): array
    {
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM `$table`");
            return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
        } catch (Exception $e) {
            return [];
        }
    }

    $dirCols = cpApiColumns($pdo, 'directions');
    $artCols = cpApiColumns($pdo, 'articles_budgetaires');

    $directionNameCol = in_array('nom_direction', $dirCols, true) ? 'nom_direction' : 'nom';
    $directionCodeCol = in_array('code_direction', $dirCols, true) ? 'code_direction' : 'code';

    $where = [];
    $params = [];

    $where[] = "a.actif = 1";

    if (in_array('visible_taxation', $dirCols, true)) {
        $where[] = "(d.visible_taxation = 1 OR d.visible_taxation IS NULL)";
    }

    if ($direction_id > 0) {
        $where[] = "a.direction_id = ?";
        $params[] = $direction_id;
    }

    if ($service_id > 0) {
        $where[] = "a.service_id = ?";
        $params[] = $service_id;
    }

    if ($q !== '') {
        $searchParts = [
            "a.code_article LIKE ?",
            "a.nature_acte LIKE ?",
            "a.acte_generateur LIKE ?",
            "a.fait_generateur LIKE ?",
            "a.libelle_taux LIKE ?",
            "a.secteur LIKE ?"
        ];

        if (in_array('art_par', $artCols, true)) {
            $searchParts[] = "a.art_par LIKE ?";
        }

        $where[] = "(" . implode(" OR ", $searchParts) . ")";

        for ($i = 0; $i < count($searchParts); $i++) {
            $params[] = "%$q%";
        }
    }

    $sql = "
        SELECT
            a.id,
            a.code_article,
            a.nature_acte,
            a.libelle_taux,
            a.acte_generateur,
            a.fait_generateur,
            a.secteur,
            a.periodicite,
            a.type_taux,
            a.mode_calcul,
            a.taux_acte,
            a.devise_base,
            a.frais_administratif,
            a.frais_technique,
            a.direction_id,
            a.service_id,
            d.`$directionNameCol` AS direction_nom,
            d.`$directionCodeCol` AS direction_code
        FROM articles_budgetaires a
        LEFT JOIN directions d ON d.id = a.direction_id
        WHERE " . implode(" AND ", $where) . "
        ORDER BY
            d.`$directionNameCol` ASC,
            a.code_article ASC,
            a.nature_acte ASC
        LIMIT $limit
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $items = [];

    foreach ($rows as $r) {
        $mode = strtolower($r['mode_calcul'] ?? $r['type_taux'] ?? 'fixe');
        $devise = $r['devise_base'] ?? 'CDF';
        $taux = (float)($r['taux_acte'] ?? 0);

        $tauxLabel = number_format($taux, 2, ',', ' ');

        if ($devise === '%' || $mode === 'pourcentage') {
            $tauxLabel = preg_replace('/,00$/', '', $tauxLabel) . '%';
        } else {
            $tauxLabel .= ' ' . $devise;
        }

        $label = trim(
            ($r['code_article'] ?? '') .
            ' — ' .
            ($r['nature_acte'] ?? '') .
            (!empty($r['libelle_taux']) ? ' — ' . $r['libelle_taux'] : '')
        );

        $items[] = [
            'id' => (int)$r['id'],
            'label' => $label,
            'code_article' => $r['code_article'],
            'nature_acte' => $r['nature_acte'],
            'libelle_taux' => $r['libelle_taux'],
            'direction_id' => $r['direction_id'],
            'direction_nom' => $r['direction_nom'],
            'service_id' => $r['service_id'],
            'periodicite' => $r['periodicite'],
            'type_taux' => $r['type_taux'],
            'mode_calcul' => $r['mode_calcul'],
            'taux_acte' => $r['taux_acte'],
            'devise_base' => $r['devise_base'],
            'frais_administratif' => $r['frais_administratif'],
            'frais_technique' => $r['frais_technique'],
            'taux_label' => $tauxLabel
        ];
    }

    echo json_encode([
        'success' => true,
        'count' => count($items),
        'items' => $items
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
