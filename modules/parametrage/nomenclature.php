<?php
require_once "../../config/database.php";
require_once "../../config/security.php";
require_once "../../core/functions.php";

checkAuth();
requireRole(['SUPER_ADMIN','ADMIN','PARAMETRAGE']);

$page_title = "Nomenclature fiscale";
$message = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {
        $direction_id = !empty($_POST['direction_id']) ? (int)$_POST['direction_id'] : null;
        $service_id   = !empty($_POST['service_id']) ? (int)$_POST['service_id'] : null;

        $code_article = trim($_POST['code_article'] ?? '');
        $secteur = trim($_POST['secteur'] ?? '');
        $art_par = trim($_POST['art_par'] ?? '');
        $acte_generateur = trim($_POST['acte_generateur'] ?? '');
        $libelle_taux = trim($_POST['libelle_taux'] ?? '');
        $nature_acte = trim($_POST['nature_acte'] ?? '');
        $fait_generateur = trim($_POST['fait_generateur'] ?? '');

        $periodicite = $_POST['periodicite'] ?? 'ponctuelle';
        $type_taux = $_POST['type_taux'] ?? 'fixe';
        $mode_calcul = $_POST['mode_calcul'] ?? 'fixe';

        $taux_acte = (float)($_POST['taux_acte'] ?? 0);
        $frais_administratif = (float)($_POST['frais_administratif'] ?? 0);
        $frais_technique = (float)($_POST['frais_technique'] ?? 0);

        $unite = trim($_POST['unite'] ?? '');
        $unite_assiette = trim($_POST['unite_assiette'] ?? '');
        $base_calcul_libelle = trim($_POST['base_calcul_libelle'] ?? '');

        $devise_base = $_POST['devise_base'] ?? 'USD';

/*
|--------------------------------------------------------------------------
| Devise spéciale pour les taux en pourcentage
|--------------------------------------------------------------------------
| Pour IRL/RL/pourcentage, le taux n'est pas en CDF ni USD.
| Exemple : 15 = 15%
|--------------------------------------------------------------------------
*/
if ($type_taux === 'pourcentage' || $mode_calcul === 'pourcentage') {
    $devise_base = '%';
}

$natureLower = mb_strtolower($nature_acte . ' ' . $code_article . ' ' . $libelle_taux, 'UTF-8');

if (
    str_contains($natureLower, 'irl') ||
    str_contains($natureLower, 'revenu locatif') ||
    str_contains($natureLower, 'retenu locative') ||
    str_contains($natureLower, 'retenue locative') ||
    preg_match('/\brl\b/i', $natureLower)
) {
    $type_taux = 'pourcentage';
    $mode_calcul = 'pourcentage';
    $devise_base = '%';
}

        $formule_personnalisee = trim($_POST['formule_personnalisee'] ?? '');
        $rapportable = isset($_POST['rapportable']) ? 1 : 0;

        if ($code_article === '' || $nature_acte === '') {
            throw new Exception("Le code article et l’acte taxable sont obligatoires.");
        }

        if ($acte_generateur === '') {
            throw new Exception("L’acte générateur est obligatoire.");
        }

        if ($libelle_taux === '') {
            $libelle_taux = "Taux standard";
        }

        if ($secteur === '' && $service_id) {
            $stmt = $pdo->prepare("SELECT nom_service FROM services_assiette WHERE id = ?");
            $stmt->execute([$service_id]);
            $s = $stmt->fetch();
            $secteur = $s['nom_service'] ?? '';
        }

        $stmt = $pdo->prepare("
            INSERT INTO articles_budgetaires
            (
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
                formule_personnalisee,
                actif,
                direction_id,
                service_id,
                art_par,
                acte_generateur,
                libelle_taux,
                mode_calcul,
                unite_assiette,
                base_calcul_libelle,
                rapportable
            )
            VALUES
            (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1,
                ?, ?, ?, ?, ?, ?, ?, ?, ?
            )
        ");

        $stmt->execute([
            $code_article,
            $secteur,
            $nature_acte,
            $fait_generateur,
            $periodicite,
            $type_taux,
            $taux_acte,
            $frais_administratif,
            $frais_technique,
            $unite,
            $devise_base,
            $formule_personnalisee,
            $direction_id,
            $service_id,
            $art_par,
            $acte_generateur,
            $libelle_taux,
            $mode_calcul,
            $unite_assiette,
            $base_calcul_libelle,
            $rapportable
        ]);

        $message = "Acte taxable enregistré avec succès dans la nomenclature.";

    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            $error = "Doublon détecté : ce code article + acte taxable + acte générateur + catégorie tarifaire existe déjà.";
        } else {
            $error = "Erreur base de données : " . $e->getMessage();
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$directions = $pdo->query("
    SELECT *
    FROM directions
    WHERE actif = 1
    ORDER BY nom_direction ASC
")->fetchAll();

$services = $pdo->query("
    SELECT 
        s.*,
        d.nom_direction
    FROM services_assiette s
    LEFT JOIN directions d ON s.direction_id = d.id
    WHERE s.actif = 1
    ORDER BY d.nom_direction ASC, s.nom_service ASC
")->fetchAll();

$nomenclature = $pdo->query("
    SELECT 
        a.*,
        d.nom_direction,
        s.nom_service
    FROM articles_budgetaires a
    LEFT JOIN directions d ON a.direction_id = d.id
    LEFT JOIN services_assiette s ON a.service_id = s.id
    ORDER BY 
        d.nom_direction ASC,
        s.nom_service ASC,
        a.secteur ASC,
        a.code_article ASC,
        a.acte_generateur ASC,
        a.libelle_taux ASC
")->fetchAll();
?>

<?php
if (!function_exists('formatTauxNomenclature')) {
    function formatTauxNomenclature($taux, $devise, $modeCalcul = '', $typeTaux = ''): string
    {
        $taux = (float)($taux ?? 0);
        $devise = trim((string)($devise ?? ''));
        $modeCalcul = strtolower((string)$modeCalcul);
        $typeTaux = strtolower((string)$typeTaux);

        if ($devise === '%' || $modeCalcul === 'pourcentage' || $typeTaux === 'pourcentage') {
            $txt = number_format($taux, 2, ',', ' ');
            $txt = preg_replace('/,00$/', '', $txt);
            return $txt . ' %';
        }

        return number_format($taux, 2, ',', ' ') . ' ' . $devise;
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
        .grid-2 { display:grid; grid-template-columns:repeat(2,1fr); gap:14px; }
        .grid-3 { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; }
        label { font-weight:900; color:#0f3460; display:block; margin-bottom:6px; }
        .success-box { background:#ecfdf5; color:#047857; border:1px solid #bbf7d0; padding:14px; border-radius:14px; font-weight:900; margin-bottom:18px; }
        .error-box { background:#fef2f2; color:#991b1b; border:1px solid #fecaca; padding:14px; border-radius:14px; font-weight:900; margin-bottom:18px; }
        .mini-note { color:#6b7280; font-size:13px; margin-top:4px; }
        textarea { min-height:90px; }
    </style>

<style>
.btn-edit{
    display:inline-block;
    background:#f59e0b;
    color:#111827!important;
    padding:7px 12px;
    border-radius:10px;
    font-weight:900;
    text-decoration:none;
    white-space:nowrap;
}
.btn-edit:hover{
    background:#d97706;
    color:#fff!important;
}
.text-center{text-align:center;}
</style>

<link rel="stylesheet" href="../../assets/css/parametrage.css">
</head>

<body class="cp-parametrage-page">
<div class="admin-layout">

<?php require_once "../../includes/sidebar.php"; ?>

<main class="main-content">
<?php require_once "../../includes/topbar.php"; ?>

<div class="panel cp-parametrage-panel">
    <h2>Nomenclature fiscale</h2>
    <p>
        Configuration réelle :
        <strong>Direction → Service → Code article → Acte générateur → Catégorie tarifaire → Acte taxable → Taux</strong>
    </p>

    <?php if ($message): ?>
        <div class="success-box"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="error-box"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
</div>

<div class="panel cp-parametrage-panel">
    <h3>Ajouter un acte taxable dans la nomenclature</h3>

    <form method="POST">

        <div class="grid-2">
            <div>
                <label>Direction</label>
                <select name="direction_id">
                    <option value="">-- Direction --</option>
                    <?php foreach ($directions as $d): ?>
                        <option value="<?= $d['id'] ?>">
                            <?= htmlspecialchars($d['nom_direction']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label>Service / Secteur d’assiette</label>
                <select name="service_id">
                    <option value="">-- Service --</option>
                    <?php foreach ($services as $s): ?>
                        <option value="<?= $s['id'] ?>">
                            <?= htmlspecialchars($s['nom_direction'] ?? 'Sans direction') ?>
                            —
                            <?= htmlspecialchars($s['nom_service']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="mini-note">Le service sera utilisé pour les rapports par service d’assiette.</div>
            </div>
        </div>

        <div class="grid-3">
            <div>
                <label>Code article budgétaire</label>
                <input type="text" name="code_article" placeholder="Ex: 27425820" required>
            </div>

            <div>
                <label>Article / Paragraphe</label>
                <input type="text" name="art_par" placeholder="Ex: Art. 27425820">
            </div>

            <div>
                <label>Secteur affiché</label>
                <input type="text" name="secteur" placeholder="Ex: TRANSPORT, INTERIEUR">
            </div>
        </div>

        <label>Acte générateur</label>
        <textarea name="acte_generateur" placeholder="Ex: Demande de certificat, visite médicale, demande de carte..." required></textarea>

        <label>Acte taxable / Nature d’acte</label>
        <textarea name="nature_acte" placeholder="Ex: Certificat de non contagiosité de transport des personnes" required></textarea>

        <div class="grid-2">
            <div>
                <label>Catégorie tarifaire / Libellé du taux</label>
                <input type="text" name="libelle_taux" placeholder="Ex: Pour les nationaux, Pour les étrangers, Catégorie A">
                <div class="mini-note">Permet plusieurs taux pour un même code article et un même acte.</div>
            </div>

            <div>
                <label>Fait générateur</label>
                <input type="text" name="fait_generateur" placeholder="Ex: Demande de certificat">
            </div>
        </div>

        <div class="grid-3">
            <div>
                <label>Périodicité</label>
                <select name="periodicite">
                    <option value="ponctuelle">Ponctuelle</option>
                    <option value="mensuelle">Mensuelle</option>
                    <option value="trimestrielle">Trimestrielle</option>
                    <option value="semestrielle">Semestrielle</option>
                    <option value="annuelle">Annuelle</option>
                    <option value="non_renouvelable">Non renouvelable</option>
                </select>
            </div>

            <div>
                <label>Type taux</label>
                <select name="type_taux" id="type_taux">
                    <option value="fixe">Fixe</option>
                    <option value="pourcentage">Pourcentage</option>
                    <option value="mixte">Mixte</option>
                </select>
            </div>

            <div>
                <label>Mode calcul</label>
                <select name="mode_calcul" id="mode_calcul">
                    <option value="fixe">Fixe</option>
                    <option value="par_unite">Par unité</option>
                    <option value="pourcentage">Pourcentage</option>
                    <option value="mixte">Mixte</option>
                    <option value="formule">Formule</option>
                </select>
            </div>
        </div>

        <div class="grid-3">
            <div>
                <label>Taux de l’acte / Pourcentage</label>
                <input type="number" step="0.000001" name="taux_acte" value="0" placeholder="Ex: 15 pour 15%">
            </div>

            <div>
                <label>Frais administratif</label>
                <input type="number" step="0.000001" name="frais_administratif" value="0">
            </div>

            <div>
                <label>Frais technique</label>
                <input type="number" step="0.000001" name="frais_technique" value="0">
            </div>
        </div>

        <div class="grid-3">
            <div>
                <label>Devise</label>
                <select name="devise_base" id="devise_base">
                    <option value="CDF">CDF</option>
                    <option value="USD">USD</option>
                    <option value="%">%</option>
                </select>
                <div class="mini-note">
                    Pour les IRL/RL et les calculs en pourcentage, choisir <strong>%</strong>.
                </div>
            </div>

            <div>
                <label>Unité</label>
                <input type="text" name="unite" placeholder="Ex: certificat, carte, dossier">
            </div>

            <div>
                <label>Unité d’assiette</label>
                <input type="text" name="unite_assiette" placeholder="Ex: nombre, surface, valeur">
            </div>
        </div>

        <div class="grid-2">
            <div>
                <label>Base de calcul</label>
                <input type="text" name="base_calcul_libelle" placeholder="Ex: Nombre de certificats">
            </div>

            <div>
                <label>Formule personnalisée</label>
                <input type="text" name="formule_personnalisee" placeholder="Optionnel">
            </div>
        </div>

        <label>
            <input type="checkbox" name="rapportable" checked>
            Inclure dans les rapports
        </label>

        <br><br>
        <button type="submit">Enregistrer dans la nomenclature</button>
    </form>
</div>

<div class="panel cp-parametrage-panel">
    <h3>Liste de la nomenclature configurée</h3>

    <table class="table-premium cp-parametrage-table">
        <tr>
            <th>Direction</th>
            <th>Service</th>
            <th>Secteur</th>
            <th>Code article</th>
            <th>Acte générateur</th>
            <th>Catégorie tarifaire</th>
            <th>Acte taxable</th>
            <th>Périodicité</th>
            <th>Mode</th>
            <th>Taux</th>
            <th>Frais Admin</th>
            <th>Frais Tech</th>
            <th>Devise</th>
            <th>Actions</th>
        </tr>

        <?php foreach ($nomenclature as $n): ?>
            <tr>
                <td><?= htmlspecialchars($n['nom_direction'] ?? '-') ?></td>
                <td><?= htmlspecialchars($n['nom_service'] ?? '-') ?></td>
                <td><?= htmlspecialchars($n['secteur'] ?? '-') ?></td>
                <td><strong><?= htmlspecialchars($n['code_article']) ?></strong></td>
                <td><?= htmlspecialchars(mb_strimwidth($n['acte_generateur'] ?? '-', 0, 45, '...')) ?></td>
                <td><?= htmlspecialchars($n['libelle_taux'] ?? '-') ?></td>
                <td><?= htmlspecialchars(mb_strimwidth($n['nature_acte'], 0, 55, '...')) ?></td>
                <td><?= htmlspecialchars($n['periodicite']) ?></td>
                <td><?= htmlspecialchars($n['mode_calcul']) ?></td>
                <td><?= formatTauxNomenclature($n['taux_acte'], $n['devise_base'] ?? '', $n['mode_calcul'] ?? '', $n['type_taux'] ?? '') ?></td>
                <td><?= number_format($n['frais_administratif'], 2, ',', ' ') ?></td>
                <td><?= number_format($n['frais_technique'], 2, ',', ' ') ?></td>
                <td><?= htmlspecialchars($n['devise_base']) ?></td>
                <td>
                    <a href="nomenclature_edit.php?id=<?= (int)$n['id'] ?>" class="btn btn-warning btn-sm">✏ Modifier</a>
                </td>
            </tr>
        <?php endforeach; ?>

        <?php if (empty($nomenclature)): ?>
            <tr>
                <td colspan="14">Aucune nomenclature configurée.</td>
            </tr>
        <?php endif; ?>
    </table>
</div>

</main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const typeTaux = document.getElementById('type_taux');
    const modeCalcul = document.getElementById('mode_calcul');
    const devise = document.getElementById('devise_base');
    const nature = document.querySelector('[name="nature_acte"]');
    const libelle = document.querySelector('[name="libelle_taux"]');
    const code = document.querySelector('[name="code_article"]');

    function isLocatifText() {
        const txt = [
            nature ? nature.value : '',
            libelle ? libelle.value : '',
            code ? code.value : ''
        ].join(' ').toLowerCase();

        return txt.includes('irl') ||
               txt.includes('revenu locatif') ||
               txt.includes('retenu locative') ||
               txt.includes('retenue locative') ||
               /\brl\b/.test(txt);
    }

    function syncDevisePourcentage() {
        if (!devise) return;

        const isPercent =
            (typeTaux && typeTaux.value === 'pourcentage') ||
            (modeCalcul && modeCalcul.value === 'pourcentage') ||
            isLocatifText();

        if (isPercent) {
            devise.value = '%';

            if (typeTaux) typeTaux.value = 'pourcentage';
            if (modeCalcul) modeCalcul.value = 'pourcentage';
        }
    }

    [typeTaux, modeCalcul, nature, libelle, code].forEach(el => {
        if (el) {
            el.addEventListener('change', syncDevisePourcentage);
            el.addEventListener('keyup', syncDevisePourcentage);
        }
    });

    syncDevisePourcentage();
});
</script>

</body>
</html>
<!-- Ajouter dans la colonne Actions : <a href="nomenclature_edit.php?id=<?= (int)$n['id'] ?>" class="btn btn-warning btn-sm">✏ Modifier</a> -->
