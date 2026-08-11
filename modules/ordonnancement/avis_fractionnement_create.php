<?php
require_once "../../config/database.php";
require_once "../../config/security.php";
require_once "../../core/numero_generator.php";
require_once "../../core/functions.php";

checkAuth();
requireRole([
    'SUPER_ADMIN',
    'ORDONNATEUR'
]);
$page_title = "Créer avis de fractionnement";

$numero_np = $_GET['numero_np'] ?? null;
if (!$numero_np) die("Numéro NP obligatoire.");

function tableColumns($pdo, $table) {
    $cols = [];
    $stmt = $pdo->query("SHOW COLUMNS FROM $table");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $cols[] = $c['Field'];
    }
    return $cols;
}

function hasCol($cols, $name) {
    return in_array($name, $cols);
}

function nomContribuableAvis($c) {
    if (!empty($c['raison_sociale'])) return $c['raison_sociale'];
    return trim(($c['nom'] ?? '') . ' ' . ($c['postnom'] ?? '') . ' ' . ($c['prenom'] ?? ''));
}

$colsAvis = tableColumns($pdo, "avis_fractionnement");

$stmt = $pdo->prepare("
    SELECT 
        np.*,
        nd.numero_nd,
        nt.numero_nt,
        c.raison_sociale,
        c.nom,
        c.postnom,
        c.prenom,
        c.nif
    FROM notes_perception np
    JOIN notes_debit nd ON np.note_debit_id = nd.id
    JOIN notes_taxation nt ON nd.note_taxation_id = nt.id
    JOIN contribuables c ON nt.contribuable_id = c.id
    WHERE np.numero_np = ?
    LIMIT 1
");
$stmt->execute([$numero_np]);
$np = $stmt->fetch();

if (!$np) die("NP introuvable.");

if (($np['type_np'] ?? '') !== 'globale') {
    die("Le fractionnement concerne uniquement une NP globale.");
}

if (!in_array(($np['statut'] ?? ''), ['non_payee', 'en_attente'])) {
    die("Cette NP ne peut pas être fractionnée. Statut actuel : " . ($np['statut'] ?? '-'));
}

/*
|--------------------------------------------------------------------------
| Sécurité : une NP globale ne peut être fractionnée qu'une seule fois
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT *
    FROM avis_fractionnement
    WHERE note_perception_id = ?
    LIMIT 1
");
$stmt->execute([$np['id']]);
$avisExistant = $stmt->fetch();

if ($avisExistant) {

    if (hasCol($colsAvis, 'statut') && empty($avisExistant['statut'])) {
        $stmt = $pdo->prepare("
            UPDATE avis_fractionnement 
            SET statut = 'accorde' 
            WHERE id = ?
        ");
        $stmt->execute([$avisExistant['id']]);
    }

    die("
        <h3>Fractionnement déjà effectué</h3>
        <p>
            Cette Note de Perception a déjà fait l'objet d'un fractionnement.
            Une NP globale ne peut être fractionnée qu'une seule fois.
        </p>
        <p>
            Avis existant : <strong>" . htmlspecialchars($avisExistant['numero_avis']) . "</strong>
        </p>
        <p>
            <a href='npf_create.php?numero_avis=" . urlencode($avisExistant['numero_avis']) . "'>
                Voir / générer les NPF liées à cet avis
            </a>
        </p>
        <p>
            <a href='np_view.php?numero=" . urlencode($np['numero_np']) . "'>
                Retour à la NP mère
            </a>
        </p>
    ");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $autorite_type = $_POST['autorite_type'] ?? '';
    $autorite_nom = trim($_POST['autorite_nom'] ?? '');
    $nombre_tranches = (int)($_POST['nombre_tranches'] ?? 0);
    $annotation = trim($_POST['annotation'] ?? '');

    if (!in_array($autorite_type, ['MIN_FIN', 'DG', 'DGA'])) {
        die("Autorité invalide.");
    }

    if ($autorite_nom === '') {
        die("Nom de l'autorité obligatoire.");
    }

    if ($nombre_tranches < 2) {
        die("Le fractionnement doit contenir au moins 2 tranches.");
    }

    if ($annotation === '') {
        die("Annotation du chef obligatoire.");
    }

    /*
    |--------------------------------------------------------------------------
    | Double contrôle avant insertion
    |--------------------------------------------------------------------------
    */
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS total
        FROM avis_fractionnement
        WHERE note_perception_id = ?
    ");
    $stmt->execute([$np['id']]);
    $dejaFractionnee = (int)$stmt->fetch()['total'];

    if ($dejaFractionnee > 0) {
        die("Cette NP a déjà fait l'objet d'un fractionnement. Impossible de la fractionner une deuxième fois.");
    }

    $numero_avis = genererNumero(
        'AVF',
        $_SESSION['province_id'],
        $_SESSION['centre_id'],
        $pdo
    );

    try {

        $insertCols = [];
        $values = [];

        $map = [
            'numero_avis' => $numero_avis,
            'note_perception_id' => $np['id'],
            'autorite_type' => $autorite_type,
            'autorite_nom' => $autorite_nom,
            'annotation' => $annotation,
            'user_directeur_recouvrement_id' => $_SESSION['user_id'],
            'montant_total' => $np['solde_restant'],
            'nombre_tranches' => $nombre_tranches,
            'statut' => 'accorde',
            'date_avis' => date('Y-m-d'),
            'created_at' => date('Y-m-d H:i:s')
        ];

        foreach ($map as $col => $val) {
            if (hasCol($colsAvis, $col)) {
                $insertCols[] = $col;
                $values[] = $val;
            }
        }

        $placeholders = implode(',', array_fill(0, count($insertCols), '?'));
        $sql = "INSERT INTO avis_fractionnement (" . implode(',', $insertCols) . ") VALUES ($placeholders)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);

        $stmt = $pdo->prepare("
            UPDATE notes_perception
            SET annotation_autorite = ?
            WHERE id = ?
        ");
        $stmt->execute([$annotation, $np['id']]);

        auditLog(
            $pdo,
            $_SESSION['user_id'] ?? null,
            "Création avis de fractionnement",
            "Ordonnancement",
            $numero_avis,
            "Avis créé pour la NP mère " . $np['numero_np']
        );

        header("Location: npf_create.php?numero_avis=" . urlencode($numero_avis));
        exit;

    } catch (Exception $e) {
        die("Erreur création avis : " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($page_title) ?> | cOllect_Pay</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../../assets/css/admin.css">

<style>
.hero-luxoria{
    background:linear-gradient(135deg,#06152b,#0f3460);
    color:white;
    border-radius:24px;
    padding:24px;
    margin-bottom:22px;
}
.grid-2{
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:14px;
}
.info-box{
    background:#eff6ff;
    border:1px solid #bfdbfe;
    color:#1e3a8a;
    padding:14px;
    border-radius:14px;
    font-weight:800;
    margin-bottom:18px;
}
.warning-box{
    background:#fff7ed;
    border:1px solid #fed7aa;
    color:#9a3412;
    padding:14px;
    border-radius:14px;
    font-weight:800;
    margin-bottom:18px;
}
.amount-big{
    font-size:26px;
    font-weight:900;
    color:#0f3460;
}
label{
    font-weight:900;
    color:#0f3460;
    display:block;
    margin-bottom:6px;
}
textarea{
    min-height:130px;
}
</style>
<link rel="stylesheet" href="../../assets/css/ordonnancement.css">
</head>

<body class="cp-ordonnancement-page">
<div class="admin-layout">

<?php require_once "../../includes/sidebar.php"; ?>

<main class="main-content">
<?php require_once "../../includes/topbar.php"; ?>

<div class="hero-luxoria cp-hero">
    <h2>Avis de fractionnement</h2>
    <p>Le numéro d’avis sera généré automatiquement par le système.</p>
</div>

<div class="panel cp-panel cp-form-shell">
    <h3>I. NP globale concernée</h3>

    <div class="info-box">
        Le Directeur de Recouvrement renseigne l’autorité, le nombre de tranches et l’annotation portée sur la NP globale.
    </div>

    <div class="warning-box">
        Une NP globale ne peut être fractionnée qu’une seule fois. Après création de l’avis, aucun deuxième fractionnement ne sera accepté.
    </div>

    <table class="table-premium cp-ord-table">
        <tr>
            <th>NP globale</th>
            <td><strong><?= htmlspecialchars($np['numero_np']) ?></strong></td>
        </tr>
        <tr>
            <th>ND</th>
            <td><?= htmlspecialchars($np['numero_nd']) ?></td>
        </tr>
        <tr>
            <th>NT</th>
            <td><?= htmlspecialchars($np['numero_nt']) ?></td>
        </tr>
        <tr>
            <th>Contribuable</th>
            <td><?= htmlspecialchars(nomContribuableAvis($np)) ?></td>
        </tr>
        <tr>
            <th>NIF</th>
            <td><?= htmlspecialchars($np['nif'] ?? '-') ?></td>
        </tr>
        <tr>
            <th>Montant à fractionner</th>
            <td><span class="amount-big"><?= number_format($np['solde_restant'], 2, ',', ' ') ?> CDF</span></td>
        </tr>
    </table>
</div>

<div class="panel">
    <h3>II. Décision de l’autorité</h3>

    <form method="POST" class="cp-form">

        <div class="grid-2">
            <div>
                <label>Autorité ayant accordé</label>
                <select name="autorite_type" required>
                    <option value="">-- Sélectionner --</option>
                    <option value="MIN_FIN">MIN FIN</option>
                    <option value="DG">DG</option>
                    <option value="DGA">DGA</option>
                </select>
            </div>

            <div>
                <label>Nom de l’autorité</label>
                <input type="text" name="autorite_nom" placeholder="Nom complet de l'autorité" required>
            </div>
        </div>

        <div>
            <label>Nombre de tranches accordées</label>
            <input type="number" name="nombre_tranches" min="2" value="2" required>
        </div>

        <div>
            <label>Annotation / Avis du chef</label>
            <textarea name="annotation" placeholder="Ex : Fractionnement accordé en 3 tranches. Première tranche exigible immédiatement..." required></textarea>
        </div>

        <button type="submit">
            Générer automatiquement l’avis de fractionnement
        </button>

    </form>
</div>

</main>
</div>
</body>
</html>