<?php
require_once "../../config/database.php";
require_once "../../config/security.php";
require_once "../../core/functions.php";
require_once "../../core/tax_engine.php";



$numero_nt = $_GET['numero'] ?? ($_POST['numero_nt'] ?? null);

if (!$numero_nt) {
    die("Numéro NT obligatoire.");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Méthode invalide.");
}

function cpColumnsNT(PDO $pdo, string $table): array
{
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `$table`");
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
    } catch (Exception $e) {
        return [];
    }
}

function cpInsertDynamicNT(PDO $pdo, string $table, array $data): int
{
    $cols = cpColumnsNT($pdo, $table);
    $insertCols = [];
    $values = [];

    foreach ($data as $col => $val) {
        if (in_array($col, $cols, true)) {
            $insertCols[] = $col;
            $values[] = $val;
        }
    }

    if (!$insertCols) {
        throw new Exception("Aucune colonne valide pour insertion dans $table.");
    }

    $sql = "INSERT INTO `$table` (`" . implode("`,`", $insertCols) . "`) VALUES (" . implode(",", array_fill(0, count($insertCols), "?")) . ")";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);

    return (int)$pdo->lastInsertId();
}

function cpUpdateDynamicNT(PDO $pdo, string $table, array $data, string $where, array $whereValues): void
{
    $cols = cpColumnsNT($pdo, $table);
    $sets = [];
    $values = [];

    foreach ($data as $col => $val) {
        if (in_array($col, $cols, true)) {
            $sets[] = "`$col` = ?";
            $values[] = $val;
        }
    }

    if (!$sets) {
        return;
    }

    $values = array_merge($values, $whereValues);

    $sql = "UPDATE `$table` SET " . implode(",", $sets) . " WHERE $where";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);
}

function cpGetPeriodeDbNT(PDO $pdo, ?int $periodeId): ?array
{
    if (!$periodeId) {
        return null;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM periodes_taxation WHERE id = ? LIMIT 1");
        $stmt->execute([$periodeId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        return null;
    }
}

function cpUpdateTotalNT(PDO $pdo, int $ntId): void
{
    $stmt = $pdo->prepare("
        SELECT IFNULL(SUM(total_ligne_cdf),0) AS total_cdf
        FROM notes_taxation_details
        WHERE note_taxation_id = ?
    ");
    $stmt->execute([$ntId]);
    $totalActes = (float)($stmt->fetch()['total_cdf'] ?? 0);

    $cols = cpColumnsNT($pdo, 'notes_taxation');
    $data = [];

    if (in_array('montant_total', $cols, true)) {
        $data['montant_total'] = $totalActes;
    }

    if (in_array('total_cdf', $cols, true)) {
        $data['total_cdf'] = $totalActes;
    }

    if (in_array('updated_at', $cols, true)) {
        $data['updated_at'] = date('Y-m-d H:i:s');
    }

    if ($data) {
        cpUpdateDynamicNT($pdo, 'notes_taxation', $data, 'id = ?', [$ntId]);
    }
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT * FROM notes_taxation WHERE numero_nt = ? LIMIT 1");
    $stmt->execute([$numero_nt]);
    $nt = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$nt) {
        throw new Exception("NT introuvable.");
    }

    $articleId = (int)($_POST['article_id'] ?? $_POST['acte_taxable_id'] ?? 0);

    if ($articleId <= 0) {
        throw new Exception("Article budgétaire obligatoire.");
    }

    $stmt = $pdo->prepare("SELECT * FROM articles_budgetaires WHERE id = ? LIMIT 1");
    $stmt->execute([$articleId]);
    $article = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$article) {
        throw new Exception("Article budgétaire introuvable.");
    }

    $periodeDb = cpGetPeriodeDbNT($pdo, isset($_POST['periode_id']) ? (int)$_POST['periode_id'] : null);

    $dataCalcul = [
        'periode_code' => $_POST['periode_code'] ?? ($periodeDb['code'] ?? ($article['periodicite'] ?? 'ponctuelle')),
        'periode_libelle' => $_POST['periode_libelle'] ?? ($periodeDb['libelle'] ?? ($article['periodicite'] ?? 'Ponctuelle')),
        'mois' => $_POST['mois'] ?? ($_POST['mois_concerne'] ?? null),

        'quantite' => (float)($_POST['quantite'] ?? 1),
        'base_imposable' => (float)($_POST['base_imposable'] ?? $_POST['base_montant'] ?? 0),
        'loyer_mensuel' => (float)($_POST['loyer_mensuel'] ?? $_POST['montant_loyer'] ?? 0),

        'type_personne' => $nt['type_personne'] ?? ($_POST['type_personne'] ?? ''),
        'type_contribuable' => $nt['type_contribuable'] ?? ($_POST['type_contribuable'] ?? ''),
        'est_commercant' => $_POST['est_commercant'] ?? $_POST['commercant'] ?? 0,
    ];

    $calcul = calculerTaxeActe($pdo, $article, $dataCalcul);

    $deviseSource = $calcul['devise_source'] ?? strtoupper($article['devise_base'] ?? 'CDF');

    $detailsJson = json_encode([
        'engine' => 'FiscalEngineRefonte',
        'article' => [
            'id' => $articleId,
            'code_article' => $article['code_article'] ?? null,
            'nature_acte' => $article['nature_acte'] ?? null,
            'libelle_taux' => $article['libelle_taux'] ?? null,
        ],
        'calcul' => $calcul,
        'details' => $calcul['details'] ?? [],
        'periode' => [
            'code' => $calcul['periode_code'] ?? null,
            'libelle' => $calcul['periode_libelle'] ?? null,
            'mois_concernes' => $calcul['mois_concernes'] ?? null,
            'mois_liste' => $calcul['mois_liste'] ?? null,
        ],
    ], JSON_UNESCAPED_UNICODE);

    $insert = [
        'note_taxation_id' => (int)$nt['id'],
        'article_id' => $articleId,
        'acte_taxable_id' => $articleId,

        'code_article' => $article['code_article'] ?? null,
        'libelle_acte' => $article['nature_acte'] ?? null,
        'nature_acte' => $article['nature_acte'] ?? null,
        'libelle_taux' => $article['libelle_taux'] ?? null,

        'type_calcul' => $calcul['type_calcul'] ?? ($article['mode_calcul'] ?? $article['type_taux'] ?? 'fixe'),
        'mode_calcul' => $calcul['type_calcul'] ?? ($article['mode_calcul'] ?? 'fixe'),
        'type_taux' => $article['type_taux'] ?? null,

        'periode_code' => $calcul['periode_code'] ?? null,
        'periode_libelle' => $calcul['periode_libelle'] ?? null,
        'mois_concernes' => $calcul['mois_concernes'] ?? null,
        'mois_liste' => $calcul['mois_liste'] ?? null,
        'periodicite' => $article['periodicite'] ?? null,
        'periodicite_info' => $calcul['periode_libelle'] ?? null,

        'quantite' => $calcul['quantite'] ?? ($dataCalcul['quantite'] ?? 1),
        'base_imposable' => $calcul['base_imposable'] ?? 0,
        'loyer_mensuel' => $calcul['loyer_mensuel'] ?? null,

        'taux_pourcentage' => $calcul['taux_pourcentage'] ?? 0,
        'taux_irl' => ($calcul['type_calcul'] ?? '') === 'irl' ? ($calcul['taux'] ?? 0) : 0,
        'taux_rl' => ($calcul['type_calcul'] ?? '') === 'rl' ? ($calcul['taux'] ?? 0) : 0,

        'montant_acte' => $calcul['montant_acte_cdf'] ?? 0,
        'montant_frais_admin' => $calcul['montant_frais_admin_cdf'] ?? 0,
        'montant_frais_tech' => $calcul['montant_frais_tech_cdf'] ?? 0,
        'total_ligne' => $calcul['total_ligne_cdf'] ?? 0,

        'montant_acte_cdf' => $calcul['montant_acte_cdf'] ?? 0,
        'montant_frais_admin_cdf' => $calcul['montant_frais_admin_cdf'] ?? 0,
        'montant_frais_tech_cdf' => $calcul['montant_frais_tech_cdf'] ?? 0,
        'total_ligne_cdf' => $calcul['total_ligne_cdf'] ?? 0,

        'montant_acte_source' => $calcul['principal_source'] ?? 0,
        'montant_frais_admin_source' => $calcul['frais_admin_source'] ?? 0,
        'montant_frais_tech_source' => $calcul['frais_tech_source'] ?? 0,
        'total_ligne_source' => $calcul['total_source'] ?? 0,

        'devise_source' => $deviseSource,
        'devise_base' => $deviseSource,
        'taux_change' => $calcul['taux_change'] ?? 1,

        'details_calcul' => $detailsJson,
        'created_at' => date('Y-m-d H:i:s'),
    ];

    cpInsertDynamicNT($pdo, 'notes_taxation_details', $insert);
    cpUpdateTotalNT($pdo, (int)$nt['id']);

    $pdo->commit();

    header("Location: nt_view.php?numero=" . urlencode($numero_nt));
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    die("Erreur ajout détail NT : " . $e->getMessage());
}
