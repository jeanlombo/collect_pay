<?php
/*
|--------------------------------------------------------------------------
| cOllect_Pay - API Synchronisation PWA Offline
|--------------------------------------------------------------------------
| Rôle :
| - Recevoir les taxations offline depuis IndexedDB
| - Éviter les doublons via local_id
| - Créer / récupérer le contribuable
| - Créer automatiquement une NT
| - Créer le détail de la NT
| - Enregistrer la synchronisation dans taxations_offline_sync
|--------------------------------------------------------------------------
| Important :
| Cette API renvoie TOUJOURS du JSON.
|--------------------------------------------------------------------------
*/

ob_start();

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . "/../config/database.php";

function apiJson($success, $message, $items = [], $extra = [])
{
    while (ob_get_level()) {
        ob_end_clean();
    }

    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
        'items'   => $items
    ], $extra), JSON_UNESCAPED_UNICODE);

    exit;
}

set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function ($e) {
    apiJson(false, "Erreur serveur : " . $e->getMessage(), [], [
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
});

function tableHasColumn(PDO $pdo, $table, $column)
{
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
    $stmt->execute([$column]);
    return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
}

function insertDynamic(PDO $pdo, $table, array $data)
{
    $clean = [];

    foreach ($data as $field => $value) {
        if (tableHasColumn($pdo, $table, $field)) {
            $clean[$field] = $value;
        }
    }

    if (empty($clean)) {
        throw new Exception("Aucune colonne valide pour insertion dans $table.");
    }

    $cols = array_keys($clean);
    $placeholders = array_fill(0, count($cols), '?');

    $sql = "
        INSERT INTO `$table`
        (`" . implode("`,`", $cols) . "`)
        VALUES
        (" . implode(",", $placeholders) . ")
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_values($clean));

    return (int)$pdo->lastInsertId();
}

function updateDynamic(PDO $pdo, $table, array $data, $whereField, $whereValue)
{
    $clean = [];

    foreach ($data as $field => $value) {
        if (tableHasColumn($pdo, $table, $field)) {
            $clean[$field] = $value;
        }
    }

    if (empty($clean)) {
        return false;
    }

    $sets = [];
    $params = [];

    foreach ($clean as $field => $value) {
        $sets[] = "`$field` = ?";
        $params[] = $value;
    }

    $params[] = $whereValue;

    $sql = "
        UPDATE `$table`
        SET " . implode(", ", $sets) . "
        WHERE `$whereField` = ?
    ";

    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}

function getDefaultCentreId(PDO $pdo)
{
    try {
        $stmt = $pdo->query("SELECT id FROM centres ORDER BY id ASC LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) return (int)$row['id'];
    } catch (Throwable $e) {}

    return 1;
}

function getDefaultServiceId(PDO $pdo)
{
    try {
        $stmt = $pdo->query("SELECT id FROM services_assiette ORDER BY id ASC LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) return (int)$row['id'];
    } catch (Throwable $e) {}

    return 1;
}

function getArticle(PDO $pdo, $articleId)
{
    if (!$articleId) {
        return null;
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM articles_budgetaires
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([(int)$articleId]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function generateOfflineNTNumber(PDO $pdo)
{
    $prefix = "NT-OFF-" . date('y') . "-";

    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS total
        FROM notes_taxation
        WHERE numero_nt LIKE ?
    ");
    $stmt->execute([$prefix . "%"]);
    $total = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'] + 1;

    return $prefix . str_pad($total, 6, '0', STR_PAD_LEFT);
}

function getOrCreateContribuable(PDO $pdo, array $t)
{
    $nom = trim((string)($t['contribuable_nom'] ?? ''));
    $telephone = trim((string)($t['telephone'] ?? ''));

    if ($nom === '') {
        $nom = "Assujetti spontané";
    }

    if ($telephone !== '' && tableHasColumn($pdo, 'contribuables', 'telephone')) {
        $stmt = $pdo->prepare("
            SELECT id
            FROM contribuables
            WHERE telephone = ?
            LIMIT 1
        ");
        $stmt->execute([$telephone]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            return (int)$row['id'];
        }
    }

    return insertDynamic($pdo, 'contribuables', [
        'type_personne' => 'physique',
        'raison_sociale' => $nom,
        'nom' => $nom,
        'telephone' => $telephone,
        'adresse' => 'Taxation spontanée PWA Offline',
        'ville' => 'Non précisée',
        'nif' => null,
        'rccm' => null,
        'created_at' => date('Y-m-d H:i:s')
    ]);
}

function buildDetailsCalcul(array $t, array $article)
{
    return json_encode([
        'source' => 'PWA_OFFLINE',
        'type_taxe' => $t['type_taxe'] ?? null,
        'plaque' => $t['plaque'] ?? null,
        'gps' => [
            'lat' => $t['gps_lat'] ?? null,
            'lng' => $t['gps_lng'] ?? null
        ],
        'calcul' => [
            'base_imposable' => (float)($t['base_imposable'] ?? 0),
            'quantite' => (float)($t['quantite'] ?? 1),
            'montant_cdf' => (float)($t['montant_cdf'] ?? 0)
        ],
        'article' => [
            'id' => $article['id'] ?? null,
            'code_article' => $article['code_article'] ?? null,
            'nature_acte' => $article['nature_acte'] ?? null
        ],
        'mention' => 'Taxation créée automatiquement depuis la PWA Offline'
    ], JSON_UNESCAPED_UNICODE);
}

try {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        apiJson(false, "Méthode non autorisée. Utilisez POST.");
    }

    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);

    if (!is_array($payload)) {
        apiJson(false, "JSON invalide reçu par le serveur.");
    }

    if (empty($payload['taxations']) || !is_array($payload['taxations'])) {
        apiJson(false, "Aucune taxation reçue.");
    }

    $itemsSynced = [];

    $pdo->beginTransaction();

    foreach ($payload['taxations'] as $t) {

        $localId = trim((string)($t['local_id'] ?? ''));

        if ($localId === '') {
            continue;
        }

        /*
        |--------------------------------------------------------------------------
        | Anti-doublon
        |--------------------------------------------------------------------------
        */
        $stmt = $pdo->prepare("
            SELECT *
            FROM taxations_offline_sync
            WHERE local_id = ?
            LIMIT 1
        ");
        $stmt->execute([$localId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing && !empty($existing['numero_nt'])) {
            $itemsSynced[] = [
                'local_id' => $localId,
                'numero_nt' => $existing['numero_nt'],
                'status' => 'already_synced'
            ];
            continue;
        }

        /*
        |--------------------------------------------------------------------------
        | Validation article
        |--------------------------------------------------------------------------
        */
        $articleId = !empty($t['article_id']) ? (int)$t['article_id'] : 0;

        if ($articleId <= 0) {
            throw new Exception("Article budgétaire manquant pour la taxation : $localId");
        }

        $article = getArticle($pdo, $articleId);

        if (!$article) {
            throw new Exception("Article budgétaire introuvable ID=$articleId pour la taxation : $localId");
        }

        /*
        |--------------------------------------------------------------------------
        | Données de base
        |--------------------------------------------------------------------------
        */
        $centreId = !empty($t['centre_id']) ? (int)$t['centre_id'] : getDefaultCentreId($pdo);
        $serviceId = !empty($article['service_id']) ? (int)$article['service_id'] : getDefaultServiceId($pdo);
        $userTaxateurId = !empty($t['agent_id']) ? (int)$t['agent_id'] : null;

        $base = (float)($t['base_imposable'] ?? 0);
        $quantite = (float)($t['quantite'] ?? 1);
        if ($quantite <= 0) $quantite = 1;

        $montantCdf = (float)($t['montant_cdf'] ?? 0);

        if ($montantCdf <= 0) {
            $tauxActe = (float)($article['taux_acte'] ?? 0);
            $montantCdf = ($base > 0 ? $base : $tauxActe) * $quantite;
        }

        if ($montantCdf <= 0) {
            throw new Exception("Montant CDF invalide pour la taxation : $localId");
        }

        /*
        |--------------------------------------------------------------------------
        | 1. Contribuable spontané
        |--------------------------------------------------------------------------
        */
        $contribuableId = getOrCreateContribuable($pdo, $t);

        /*
        |--------------------------------------------------------------------------
        | 2. Note de Taxation
        |--------------------------------------------------------------------------
        */
        $numeroNt = generateOfflineNTNumber($pdo);

        $noteTaxationId = insertDynamic($pdo, 'notes_taxation', [
            'numero_nt' => $numeroNt,
            'contribuable_id' => $contribuableId,
            'centre_id' => $centreId,
            'service_id' => $serviceId,
            'exercice' => (int)date('Y'),
            'statut' => 'en_attente_liquidation',
            'total_estime' => $montantCdf,
            'devise' => 'CDF',
            'taux_change' => 1,
            'user_taxateur_id' => $userTaxateurId,
            'created_at' => date('Y-m-d H:i:s'),
            'montant_acte_total' => $montantCdf,
            'montant_frais_admin_total' => 0,
            'montant_frais_tech_total' => 0,
            'penalite_assiette' => 0,
            'penalite_recouvrement' => 0,
            'source_creation' => 'PWA_OFFLINE'
        ]);

        /*
        |--------------------------------------------------------------------------
        | 3. Détail NT
        |--------------------------------------------------------------------------
        */
        $libelleActe = $article['nature_acte']
            ?? $article['acte_generateur']
            ?? $article['fait_generateur']
            ?? 'Taxation PWA Offline';

        $detailsCalcul = buildDetailsCalcul($t, $article);

        insertDynamic($pdo, 'notes_taxation_details', [
            'note_taxation_id' => $noteTaxationId,
            'article_id' => $articleId,
            'acte_taxable_id' => $articleId,
            'libelle_acte' => $libelleActe,
            'type_calcul' => $article['mode_calcul'] ?? ($article['type_taux'] ?? 'fixe'),
            'periode_code' => null,
            'periode_libelle' => 'Taxation spontanée PWA Offline',
            'mois_concernes' => null,
            'details_calcul' => $detailsCalcul,
            'base_imposable' => $base,
            'quantite' => $quantite,
            'montant_acte' => $montantCdf,
            'montant_frais_admin' => 0,
            'montant_frais_tech' => 0,
            'total_ligne' => $montantCdf,
            'devise_source' => 'CDF',
            'taux_change' => 1,
            'total_ligne_source' => $montantCdf,
            'total_ligne_cdf' => $montantCdf,
            'direction_id' => $article['direction_id'] ?? null,
            'service_id' => $article['service_id'] ?? $serviceId,
            'art_par' => $article['code_article'] ?? null,
            'acte_generateur' => $article['fait_generateur'] ?? ($article['acte_generateur'] ?? $libelleActe),
            'periodicite' => $article['periodicite'] ?? 'ponctuelle',
            'mode_calcul' => $article['mode_calcul'] ?? ($article['type_taux'] ?? 'fixe'),
            'unite_assiette' => $article['unite_assiette'] ?? ($article['unite'] ?? null),
            'montant_source' => $montantCdf,
            'montant_cdf' => $montantCdf,
            'periodicite_info' => $article['periodicite'] ?? 'ponctuelle'
        ]);

        /*
        |--------------------------------------------------------------------------
        | 4. Table tampon offline
        |--------------------------------------------------------------------------
        */
        $syncData = [
            'local_id' => $localId,
            'agent_id' => $userTaxateurId,
            'contribuable_nom' => $t['contribuable_nom'] ?? null,
            'telephone' => $t['telephone'] ?? null,
            'plaque' => $t['plaque'] ?? null,
            'type_taxe' => $t['type_taxe'] ?? null,
            'article_id' => $articleId,
            'base_imposable' => $base,
            'quantite' => $quantite,
            'montant_cdf' => $montantCdf,
            'gps_lat' => $t['gps_lat'] ?? null,
            'gps_lng' => $t['gps_lng'] ?? null,
            'photo' => $t['photo'] ?? null,
            'signature' => $t['signature'] ?? null,
            'statut' => 'synchronise',
            'numero_nt' => $numeroNt,
            'created_at' => date('Y-m-d H:i:s'),
            'colonne_sync_nt' => 1,
            'note_taxation_id' => $noteTaxationId,
            'contribuable_id' => $contribuableId,
            'message_sync' => 'NT créée automatiquement depuis PWA Offline'
        ];

        if ($existing) {
            updateDynamic($pdo, 'taxations_offline_sync', $syncData, 'local_id', $localId);
        } else {
            insertDynamic($pdo, 'taxations_offline_sync', $syncData);
        }

        $itemsSynced[] = [
            'local_id' => $localId,
            'numero_nt' => $numeroNt,
            'note_taxation_id' => $noteTaxationId,
            'contribuable_id' => $contribuableId,
            'status' => 'nt_created'
        ];
    }

    $pdo->commit();

    apiJson(true, "Synchronisation réussie. NT créées automatiquement.", $itemsSynced);

} catch (Throwable $e) {

    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    apiJson(false, "Erreur serveur : " . $e->getMessage(), [], [
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}
?>