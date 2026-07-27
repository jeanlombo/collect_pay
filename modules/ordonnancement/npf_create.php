<?php
require_once "../../config/database.php";
require_once "../../config/security.php";

checkAuth();
requireRole([
    'SUPER_ADMIN',
    'ORDONNATEUR'
]);

$page_title = "Génération des NPF";

$numero_avis = $_GET['numero_avis'] ?? ($_POST['numero_avis'] ?? null);

if (!$numero_avis) {
    die("Numéro d’avis de fractionnement obligatoire.");
}

function ajouterJoursOuvrablesNPF($dateDepart, $jours)
{
    $date = new DateTime($dateDepart);
    $ajoutes = 0;

    while ($ajoutes < $jours) {
        $date->modify('+1 day');

        if ((int)$date->format('N') <= 5) {
            $ajoutes++;
        }
    }

    return $date->format('Y-m-d H:i:s');
}

function nomContribuableNPF($c)
{
    if (!empty($c['raison_sociale'])) {
        return $c['raison_sociale'];
    }

    return trim(($c['nom'] ?? '') . ' ' . ($c['postnom'] ?? '') . ' ' . ($c['prenom'] ?? ''));
}

$stmt = $pdo->prepare("
    SELECT 
        av.*,
        np.numero_np AS numero_np_mere,
        np.id AS np_mere_id,
        np.note_debit_id,
        np.solde_restant,
        nd.numero_nd,
        nt.numero_nt,
        c.raison_sociale,
        c.nom,
        c.postnom,
        c.prenom,
        c.nif
    FROM avis_fractionnement av
    JOIN notes_perception np ON av.note_perception_id = np.id
    JOIN notes_debit nd ON np.note_debit_id = nd.id
    JOIN notes_taxation nt ON nd.note_taxation_id = nt.id
    JOIN contribuables c ON nt.contribuable_id = c.id
    WHERE av.numero_avis = ?
    AND av.statut = 'accorde'
    LIMIT 1
");
$stmt->execute([$numero_avis]);
$avis = $stmt->fetch();

if (!$avis) {
    die("Avis de fractionnement introuvable ou non accordé.");
}

$nombre_tranches = (int)$avis['nombre_tranches'];
$montant_total = (float)$avis['montant_total'];

if ($nombre_tranches < 2) {
    die("Le nombre de tranches doit être au minimum de 2.");
}

$stmt = $pdo->prepare("
    SELECT COUNT(*) AS total
    FROM notes_perception
    WHERE avis_fractionnement_id = ?
    AND type_np = 'fractionnee'
");
$stmt->execute([$avis['id']]);
$dejaGenere = (int)$stmt->fetch()['total'];

if ($dejaGenere > 0) {
    header("Location: fractions_list.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $declarant_nom = trim($_POST['declarant_nom'] ?? '');
    $sceau_appose = isset($_POST['sceau_appose']) ? 1 : 0;
    $montants = $_POST['montants'] ?? [];

    if ($declarant_nom === '') {
        die("Le nom du déclarant est obligatoire.");
    }

    if (count($montants) !== $nombre_tranches) {
        die("Le nombre de montants saisis ne correspond pas au nombre de tranches.");
    }

    $total_saisi = 0;
    $montantsPropres = [];

    for ($i = 1; $i <= $nombre_tranches; $i++) {
        $valeur = (float)str_replace(',', '.', $montants[$i] ?? 0);

        if ($valeur <= 0) {
            die("Le montant de la tranche " . str_pad($i, 3, '0', STR_PAD_LEFT) . " doit être supérieur à zéro.");
        }

        $montantsPropres[$i] = round($valeur, 2);
        $total_saisi += round($valeur, 2);
    }

    if (round($total_saisi, 2) != round($montant_total, 2)) {
        die(
            "Erreur : la somme des tranches doit être exactement égale au montant global. " .
            "Montant global : " . number_format($montant_total, 2, ',', ' ') . " CDF. " .
            "Total saisi : " . number_format($total_saisi, 2, ',', ' ') . " CDF."
        );
    }

    try {

        for ($i = 1; $i <= $nombre_tranches; $i++) {

            $numero_npf = $avis['numero_np_mere'] . '-' . str_pad($i, 3, '0', STR_PAD_LEFT);

            $stmt = $pdo->prepare("
                SELECT id
                FROM notes_perception
                WHERE numero_np = ?
                LIMIT 1
            ");
            $stmt->execute([$numero_npf]);

            if ($stmt->fetch()) {
                continue;
            }

            $date_emission = date('Y-m-d H:i:s');

            if ($i === 1) {
                $date_echeance = $date_emission;
            } else {
                $date_echeance = ajouterJoursOuvrablesNPF($date_emission, 8 * ($i - 1));
            }

            $stmt = $pdo->prepare("
                INSERT INTO notes_perception
                (
                    numero_np,
                    note_debit_id,
                    type_np,
                    np_mere_id,
                    avis_fractionnement_id,
                    numero_tranche,
                    declarant_nom,
                    user_ordonnateur_id,
                    montant_initial,
                    montant_paye,
                    solde_restant,
                    penalite_assiette,
                    penalite_recouvrement,
                    date_emission,
                    date_echeance,
                    statut,
                    annotation_autorite,
                    sceau_appose
                )
                VALUES
                (
                    ?, ?,
                    'fractionnee',
                    ?, ?,
                    ?, ?, ?,
                    ?, 0, ?,
                    0, 0,
                    ?, ?,
                    'en_attente',
                    ?,
                    ?
                )
            ");

            $stmt->execute([
                $numero_npf,
                $avis['note_debit_id'],
                $avis['np_mere_id'],
                $avis['id'],
                $i,
                $declarant_nom,
                $_SESSION['user_id'],
                $montantsPropres[$i],
                $montantsPropres[$i],
                $date_emission,
                $date_echeance,
                $avis['annotation'],
                $sceau_appose
            ]);
        }

        $stmt = $pdo->prepare("
            UPDATE notes_perception
            SET annotation_autorite = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $avis['annotation'],
            $avis['np_mere_id']
        ]);

        header("Location: fractions_list.php");
        exit;

    } catch (Exception $e) {
        die("Erreur création NPF : " . $e->getMessage());
    }
}

$montant_indicatif = $montant_total / $nombre_tranches;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($page_title) ?> | cOllect_Pay</title>
<link rel="stylesheet" href="/collect_pay/assets/css/admin.css">

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
.tranche-card{
    background:#f8fafc;
    border:1px solid #e5e7eb;
    border-radius:18px;
    padding:16px;
}
label{
    font-weight:900;
    color:#0f3460;
    display:block;
    margin-bottom:6px;
}
</style>
</head>

<body>
<div class="admin-layout">

<?php require_once "../../includes/sidebar.php"; ?>

<main class="main-content">
<?php require_once "../../includes/topbar.php"; ?>

<div class="hero-luxoria">
    <h2>Génération des NPF</h2>
    <p>Les NPF seront numérotées selon la NP mère : NP-MERE-001, NP-MERE-002...</p>
</div>

<div class="panel">
    <h3>I. Avis de fractionnement</h3>

    <div class="info-box">
        Avis accordé : <strong><?= htmlspecialchars($avis['numero_avis']) ?></strong><br>
        NP mère : <strong><?= htmlspecialchars($avis['numero_np_mere']) ?></strong><br>
        Nombre de tranches : <strong><?= $nombre_tranches ?></strong>
    </div>

    <div class="warning-box">
        La première NPF doit être payée le même jour. Son échéance sera égale à la date de génération.
    </div>

    <table class="table-premium">
        <tr><th>ND</th><td><?= htmlspecialchars($avis['numero_nd']) ?></td></tr>
        <tr><th>NT</th><td><?= htmlspecialchars($avis['numero_nt']) ?></td></tr>
        <tr><th>Contribuable</th><td><?= htmlspecialchars(nomContribuableNPF($avis)) ?></td></tr>
        <tr><th>NIF</th><td><?= htmlspecialchars($avis['nif'] ?? '-') ?></td></tr>
        <tr><th>Autorité</th><td><?= htmlspecialchars(($avis['autorite_type'] ?? '-') . ' - ' . ($avis['autorite_nom'] ?? '-')) ?></td></tr>
        <tr>
            <th>Montant global à répartir</th>
            <td><span class="amount-big"><?= number_format($montant_total, 2, ',', ' ') ?> CDF</span></td>
        </tr>
        <tr>
            <th>Montant indicatif / tranche</th>
            <td><?= number_format($montant_indicatif, 2, ',', ' ') ?> CDF</td>
        </tr>
    </table>
</div>

<div class="panel">
    <h3>II. Répartition manuelle des tranches</h3>

    <form method="POST">
        <input type="hidden" name="numero_avis" value="<?= htmlspecialchars($avis['numero_avis']) ?>">

        <div class="grid-2">
            <div>
                <label>Nom du déclarant / signataire</label>
                <input type="text" name="declarant_nom" required>
            </div>

            <div>
                <label>Sceau</label>
                <label>
                    <input type="checkbox" name="sceau_appose" checked>
                    Sceau apposé sur les NPF
                </label>
            </div>
        </div>

        <div class="grid-2">
            <?php for ($i = 1; $i <= $nombre_tranches; $i++): ?>
                <div class="tranche-card">
                    <label>
                        Montant NPF-<?= str_pad($i, 3, '0', STR_PAD_LEFT) ?>
                        <?php if ($i === 1): ?>
                            <small>(échéance même jour)</small>
                        <?php endif; ?>
                    </label>

                    <input type="number"
                           step="0.01"
                           name="montants[<?= $i ?>]"
                           placeholder="Montant de la tranche <?= str_pad($i, 3, '0', STR_PAD_LEFT) ?>"
                           required>
                </div>
            <?php endfor; ?>
        </div>

        <button type="submit">
            Générer les NPF
        </button>
    </form>
</div>

</main>
</div>
</body>
</html>