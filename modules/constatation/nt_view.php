<?php
/*
|--------------------------------------------------------------------------
| cOllect_Pay - Vue Note de Taxation
|--------------------------------------------------------------------------
| Sécurité corrigée :
| - Avant : requireRole(...)
| - Maintenant : requirePermission('constatation','view')
|--------------------------------------------------------------------------
*/

require_once "../../auth/check_auth.php";
require_once "../../core/functions.php";



$numero_nt = $_GET['numero'] ?? null;

if (!$numero_nt) {
    die("Numéro NT manquant.");
}

$stmt = $pdo->prepare("
    SELECT 
        nt.*,
        c.type_personne,
        c.raison_sociale,
        c.nom,
        c.postnom,
        c.prenom,
        c.nif,
        c.rccm,
        c.telephone,
        c.adresse,
        c.ville,
        s.nom_service,
        ce.nom AS centre_nom
    FROM notes_taxation nt
    JOIN contribuables c 
        ON nt.contribuable_id = c.id
    LEFT JOIN services_assiette s
        ON nt.service_id = s.id
    LEFT JOIN centres ce
        ON nt.centre_id = ce.id
    WHERE nt.numero_nt = ?
    LIMIT 1
");
$stmt->execute([$numero_nt]);
$nt = $stmt->fetch();

if (!$nt) {
    die("Note de taxation introuvable.");
}

/*
|--------------------------------------------------------------------------
| Articles budgétaires du même service uniquement
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT 
        ab.*
    FROM articles_budgetaires ab
    WHERE ab.actif = 1
    AND ab.service_id = ?
    ORDER BY ab.code_article ASC, ab.nature_acte ASC
");
$stmt->execute([$nt['service_id']]);
$articles = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Périodes taxation
|--------------------------------------------------------------------------
*/
$periodes = $pdo->query("
    SELECT *
    FROM periodes_taxation
    ORDER BY id ASC
")->fetchAll();

/*
|--------------------------------------------------------------------------
| Directions visibles dans la taxation
|--------------------------------------------------------------------------
*/
try {
    $directionsTaxation = $pdo->query("
        SELECT *
        FROM directions
        WHERE actif = 1
        AND (visible_taxation = 1 OR visible_taxation IS NULL)
        ORDER BY nom_direction ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $directionsTaxation = [];
}


/*
|--------------------------------------------------------------------------
| Détails déjà ajoutés
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT 
        d.*,
        ab.code_article,
        ab.nature_acte
    FROM notes_taxation_details d
    LEFT JOIN articles_budgetaires ab
        ON d.article_id = ab.id
    WHERE d.note_taxation_id = ?
    ORDER BY d.id ASC
");
$stmt->execute([$nt['id']]);
$details = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Fonctions
|--------------------------------------------------------------------------
*/
function montantDetailNTMulti($d)
{
    if (isset($d['total_ligne_cdf'])) {
        return (float)$d['total_ligne_cdf'];
    }

    if (isset($d['montant_cdf'])) {
        return (float)$d['montant_cdf'];
    }

    if (isset($d['montant_total'])) {
        return (float)$d['montant_total'];
    }

    if (isset($d['montant_acte'])) {
        return (float)$d['montant_acte'];
    }

    return 0;
}

function nomContribuableNTMulti($c)
{
    if (!empty($c['raison_sociale'])) {
        return $c['raison_sociale'];
    }

    return trim(
        ($c['nom'] ?? '') . ' ' .
        ($c['postnom'] ?? '') . ' ' .
        ($c['prenom'] ?? '')
    );
}

function baseDetailNTMulti($d)
{
    if (isset($d['base_calcul'])) {
        return (float)$d['base_calcul'];
    }

    if (isset($d['base_imposable'])) {
        return (float)$d['base_imposable'];
    }

    return 0;
}

function isArticleLoyerNTView($a)
{
    $txt = mb_strtolower(
        ($a['nature_acte'] ?? '') . ' ' .
        ($a['fait_generateur'] ?? '') . ' ' .
        ($a['acte_generateur'] ?? '') . ' ' .
        ($a['unite_assiette'] ?? '') . ' ' .
        ($a['base_calcul_libelle'] ?? '') . ' ' .
        ($a['libelle_taux'] ?? '')
    );

    return str_contains($txt, 'irl')
        || str_contains($txt, 'revenus locatifs')
        || str_contains($txt, 'revenu locatif')
        || str_contains($txt, 'retenu locative')
        || str_contains($txt, 'retenue locative')
        || preg_match('/\brl\b/i', $txt);
}

function affichageBaseDetailNTView($d)
{
    $loyer = isset($d['loyer_mensuel']) ? (float)$d['loyer_mensuel'] : 0;
    $base = baseDetailNTMulti($d);
    $quantite = isset($d['quantite']) ? (float)$d['quantite'] : 1;
    $type = strtolower($d['type_calcul'] ?? $d['mode_calcul'] ?? '');

    if ($loyer > 0 || in_array($type, ['irl', 'rl', 'irl_rl'], true)) {
        return "Montant du loyer : " . number_format($loyer, 2, ',', ' ') . " CDF\n" .
               "Base taxable : " . number_format($base, 2, ',', ' ') . " CDF";
    }

    if ($type === 'par_unite') {
        return "Quantité : " . number_format($quantite, 2, ',', ' ');
    }

    return "Base imposable : " . number_format($base, 2, ',', ' ') . " CDF";
}

function libelleActeNTMulti($d)
{
    if (!empty($d['libelle_acte'])) {
        return $d['libelle_acte'];
    }

    if (!empty($d['nature_acte'])) {
        return $d['nature_acte'];
    }

    if (!empty($d['acte_generateur'])) {
        return $d['acte_generateur'];
    }

    return '-';
}

function decodeDetailsCalculNTMulti($json)
{
    if (empty($json)) {
        return null;
    }

    $data = json_decode($json, true);

    return is_array($data) ? $data : null;
}

function formatMontantNTView($montant, $decimales = 2)
{
    return number_format((float)$montant, $decimales, ',', ' ');
}

function tauxArticleNTView($d)
{
    $base = (float)($d['base_imposable'] ?? 0);
    $qte = (float)($d['quantite'] ?? 1);
    $tauxChange = (float)($d['taux_change'] ?? 1);
    $devise = strtoupper($d['devise_source'] ?? 'CDF');
    $montantActe = (float)($d['montant_acte'] ?? 0);

    if ((float)($d['taux_pourcentage'] ?? 0) > 0) {
        return (float)$d['taux_pourcentage'];
    }

    if ($devise === 'USD' && $tauxChange > 0 && $base > 0) {
        return ($montantActe / $tauxChange) / $base;
    }

    if ($base > 0) {
        return $montantActe / $base;
    }

    if ($qte > 0) {
        return $montantActe / $qte;
    }

    return 0;
}

function detailsCalculTextNTMulti($d)
{
    if (!is_array($d)) {
        $data = decodeDetailsCalculNTMulti($d);

        if (!$data) {
            return '-';
        }

        $lines = [];

        if (!empty($data['bareme']['mention'])) {
            $lines[] = $data['bareme']['mention'];
            $lines[] = '';
        }

        if (!empty($data['periode'])) {
            $lines[] = 'Période : ' . ($data['periode']['libelle'] ?? '-');
            $lines[] = 'Mois : ' . ($data['periode']['mois'] ?? '-');
        }

        if (!empty($data['details']) && is_array($data['details'])) {
            foreach ($data['details'] as $ligne) {
                $lines[] =
                    ($ligne['libelle'] ?? '-') . ' : ' .
                    ($ligne['formule'] ?? '-') . ' = ' .
                    number_format((float)($ligne['montant'] ?? 0), 2, ',', ' ') . ' CDF';
            }
        }

        return implode("\n", $lines);
    }

    $lines = [];

    $libelle = libelleActeNTMulti($d);
    $base = (float)($d['base_imposable'] ?? 0);
    $quantite = (float)($d['quantite'] ?? 1);
    $tauxChange = (float)($d['taux_change'] ?? 1);
    $devise = strtoupper($d['devise_source'] ?? 'CDF');
    $montantActeCdf = (float)($d['montant_acte'] ?? 0);
    $fraisAdminCdf = (float)($d['montant_frais_admin'] ?? 0);
    $fraisTechCdf = (float)($d['montant_frais_tech'] ?? 0);
    $tauxArticle = tauxArticleNTView($d);

    $valeur = $base > 0 ? $base : $quantite;

    if ($devise === 'USD' && $tauxChange > 1) {
        $principalUsd = $tauxChange > 0 ? ($montantActeCdf / $tauxChange) : 0;
        $faUsd = $tauxChange > 0 ? ($fraisAdminCdf / $tauxChange) : 0;
        $ftUsd = $tauxChange > 0 ? ($fraisTechCdf / $tauxChange) : 0;

        $lines[] = $libelle . ' :';
        $lines[] =
            formatMontantNTView($valeur) . ' × ' .
            formatMontantNTView($tauxArticle) . ' USD = ' .
            formatMontantNTView($principalUsd) . ' USD';
        $lines[] = 'ou soit ' . formatMontantNTView($montantActeCdf) . ' CDF';

        if ($fraisAdminCdf > 0) {
            $lines[] =
                'Frais administratif : ' .
                formatMontantNTView($faUsd) . ' USD × ' .
                formatMontantNTView($tauxChange, 0) . ' = ' .
                formatMontantNTView($fraisAdminCdf) . ' CDF';
        }

        if ($fraisTechCdf > 0) {
            $lines[] =
                'Frais technique : ' .
                formatMontantNTView($ftUsd) . ' USD × ' .
                formatMontantNTView($tauxChange, 0) . ' = ' .
                formatMontantNTView($fraisTechCdf) . ' CDF';
        }

        return implode("\n", $lines);
    }

    $lines[] = $libelle . ' :';
    $isPourcentage =
        in_array(strtolower($d['type_calcul'] ?? ''), ['irl', 'rl', 'pourcentage'], true)
        || strtolower($d['type_taux'] ?? '') === 'pourcentage'
        || strtoupper($d['devise_base'] ?? '') === '%'
        || str_contains(strtolower($d['libelle_acte'] ?? ''), 'irl')
        || str_contains(strtolower($d['libelle_acte'] ?? ''), 'revenu locatif')
        || str_contains(strtolower($d['libelle_acte'] ?? ''), 'retenu locative')
        || str_contains(strtolower($d['libelle_acte'] ?? ''), 'retenue locative');

    if ($isPourcentage) {
        /*
        |--------------------------------------------------------------------------
        | Affichage correct des taux en pourcentage
        |--------------------------------------------------------------------------
        | Si le taux est stocké 0.15, on affiche 15%.
        | Si le taux est stocké 15, on affiche 15%.
        |--------------------------------------------------------------------------
        */
        if ($tauxArticle > 0 && $tauxArticle < 1) {
            $tauxArticle = $tauxArticle * 100;
        }

        $tauxAffiche = number_format($tauxArticle, 2, ',', ' ');
        $tauxAffiche = preg_replace('/,00$/', '', $tauxAffiche);

        $lines[] =
            formatMontantNTView($valeur) . ' × ' .
            $tauxAffiche . '% = ' .
            formatMontantNTView($montantActeCdf) . ' CDF';

    } else {
        $lines[] =
            formatMontantNTView($valeur) . ' × ' .
            formatMontantNTView($tauxArticle) . ' CDF = ' .
            formatMontantNTView($montantActeCdf) . ' CDF';
    }

    if ($fraisAdminCdf > 0) {
        $lines[] = 'Frais administratif : ' . formatMontantNTView($fraisAdminCdf) . ' CDF';
    }

    if ($fraisTechCdf > 0) {
        $lines[] = 'Frais technique : ' . formatMontantNTView($fraisTechCdf) . ' CDF';
    }

    return implode("\n", $lines);
}

function badgeNTMulti($statut)
{
    $statut = $statut ?: 'brouillon';
    $label = strtoupper(str_replace('_', ' ', $statut));

    if ($statut === 'brouillon') {
        return "<span class='badge-status'>$label</span>";
    }

    if ($statut === 'en_attente_liquidation') {
        return "<span class='badge-status orange'>$label</span>";
    }

    if ($statut === 'liquidee') {
        return "<span class='badge-status green'>$label</span>";
    }

    return "<span class='badge-status'>$label</span>";
}

/*
|--------------------------------------------------------------------------
| Totaux
|--------------------------------------------------------------------------
*/
$total_actes = 0;

foreach ($details as $d) {
    $total_actes += montantDetailNTMulti($d);
}

$penalite_assiette = (float)($nt['penalite_assiette'] ?? 0);
$penalite_recouvrement = (float)($nt['penalite_recouvrement'] ?? 0);
$total_general = $total_actes + $penalite_assiette + $penalite_recouvrement;

$page_title = "Note de Taxation";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($page_title) ?> | cOllect_Pay</title>
<link rel="stylesheet" href="/collect_pay/assets/css/admin.css">

<style>
.info-card{
    background:#fff;
    border-radius:18px;
    padding:20px;
    margin-bottom:20px;
    box-shadow:0 5px 20px rgba(0,0,0,.08);
}

.badge-status{
    background:#dbeafe;
    color:#1e40af;
    padding:8px 15px;
    border-radius:999px;
    font-weight:900;
}

.badge-status.orange{
    background:#ffedd5;
    color:#c2410c;
}

.badge-status.green{
    background:#dcfce7;
    color:#166534;
}

.grid-form{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(250px,1fr));
    gap:15px;
}

.btn-premium{
    background:#0f3460;
    color:#fff;
    border:none;
    padding:12px 18px;
    border-radius:12px;
    font-weight:900;
    text-decoration:none;
    display:inline-block;
    cursor:pointer;
}

.btn-danger{
    background:#dc2626;
    color:#fff;
    border:none;
    padding:8px 14px;
    border-radius:10px;
    text-decoration:none;
    font-weight:800;
}

.actions{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    margin-top:15px;
}

.hidden-field{
    display:none !important;
}

.form-hint{
    display:block;
    margin-top:6px;
    color:#64748b;
    font-size:12px;
    font-weight:700;
}
</style>


<style>
.cp-smart-taxation{
    background:#f8fafc;
    border:1px solid #e5e7eb;
    border-radius:18px;
    padding:16px;
    margin:14px 0;
}
.cp-smart-grid{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:12px;
}
.cp-smart-grid input,
.cp-smart-grid select{
    width:100%;
    padding:12px 14px;
    border:1px solid #d1d5db;
    border-radius:14px;
    font-weight:700;
}
.cp-results-box{
    margin-top:12px;
    max-height:280px;
    overflow-y:auto;
    border:1px solid #e5e7eb;
    border-radius:16px;
    background:white;
    display:none;
}
.cp-result-item{
    padding:12px 14px;
    border-bottom:1px solid #e5e7eb;
    cursor:pointer;
}
.cp-result-item:hover{
    background:#eff6ff;
}
.cp-result-title{
    font-weight:1000;
    color:#06152b;
}
.cp-result-meta{
    color:#64748b;
    font-size:12px;
    font-weight:800;
    margin-top:4px;
}
.cp-selected-article{
    background:#dcfce7;
    color:#166534;
    border-radius:14px;
    padding:12px 14px;
    font-weight:900;
    margin-top:12px;
    display:none;
}
@media(max-width:900px){
    .cp-smart-grid{grid-template-columns:1fr}
}
</style>

</head>
<body>

<div class="admin-layout">

<?php require_once "../../includes/sidebar.php"; ?>

<main class="main-content">

<?php require_once "../../includes/topbar.php"; ?>

<div class="panel">

    <h2>NOTE DE TAXATION</h2>

    <div class="info-card">

        <h3>
            <?= htmlspecialchars($nt['numero_nt']) ?>
            <?= badgeNTMulti($nt['statut']) ?>
        </h3>

        <p>
            <strong>Contribuable :</strong>
            <?= htmlspecialchars(
                nomContribuableNTMulti($nt)
            ) ?>
        </p>

        <p>
            <strong>Type :</strong>
            <?= strtoupper(
                htmlspecialchars(
                    $nt['type_personne']
                )
            ) ?>
        </p>

        <p>
            <strong>NIF :</strong>
            <?= htmlspecialchars(
                $nt['nif'] ?? '-'
            ) ?>
        </p>

        <p>
            <strong>Téléphone :</strong>
            <?= htmlspecialchars(
                $nt['telephone'] ?? '-'
            ) ?>
        </p>

        <p>
            <strong>Adresse :</strong>
            <?= htmlspecialchars(
                ($nt['ville'] ?? '') .
                ' - ' .
                ($nt['adresse'] ?? '')
            ) ?>
        </p>

        <p>
            <strong>Service d'assiette :</strong>
            <?= htmlspecialchars(
                $nt['nom_service'] ?? '-'
            ) ?>
        </p>

        <p>
            <strong>Centre :</strong>
            <?= htmlspecialchars(
                $nt['centre_nom'] ?? '-'
            ) ?>
        </p>

        <p>
            <strong>Exercice :</strong>
            <?= htmlspecialchars(
                $nt['exercice']
            ) ?>
        </p>

        <div class="actions">

            <a
                class="btn-premium"
                href="nt_list.php"
            >
                ← Retour à la liste
            </a>

            <a
                class="btn-premium"
                target="_blank"
                href="/collect_pay/reports/nt_pdf.php?numero=<?= urlencode($nt['numero_nt']) ?>"
            >
                🖨️ Imprimer la NT
            </a>

        </div>

    </div>

<?php if ($nt['statut'] === 'brouillon'): ?>

<div class="info-card">

    <h3>Ajouter un acte taxable</h3>

    <form
        method="POST"
        action="nt_add_detail.php?numero=<?= urlencode($nt['numero_nt']) ?>"
    >

        <div class="grid-form">

            <div>

                <label>Acte taxable</label>

                
<div class="cp-smart-taxation">
    <h4 style="margin:0 0 10px;color:#06152b;font-weight:1000;">Recherche intelligente de l’acte taxable</h4>

    <div class="cp-smart-grid">
        <div>
            <label>Ministère / Direction</label>
            <select id="cpDirectionFilter">
                <option value="">Toutes les directions visibles</option>
                <?php foreach (($directionsTaxation ?? []) as $dir): ?>
                    <option value="<?= (int)$dir['id'] ?>">
                        <?= htmlspecialchars($dir['nom_direction'] ?? $dir['nom'] ?? ('Direction #' . $dir['id'])) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label>Recherche rapide</label>
            <input type="text" id="cpSmartSearchArticle" placeholder="Code, IRL, Licence, taxe, catégorie...">
        </div>

        <div>
            <label>Résultat sélectionné</label>
            <div id="cpSelectedArticle" class="cp-selected-article"></div>
        </div>
    </div>

    <div id="cpArticleResults" class="cp-results-box"></div>

    <small style="display:block;margin-top:10px;color:#64748b;font-weight:800;">
        Tapez au moins 2 caractères pour rechercher rapidement l’article budgétaire.
    </small>
</div>

<select style="display:none"
                    name="article_id"
                    id="article_id"
                    required
                >

                    <option value="">
                        -- Sélectionner --
                    </option>

                    <?php foreach ($articles as $a): ?>

                    <?php
                        $isLoyerArticle = isArticleLoyerNTView($a);
                        $modeSaisie = $isLoyerArticle ? 'loyer' : 'base';
                    ?>
                    <option
                        value="<?= (int)$a['id'] ?>"
                        data-mode-saisie="<?= htmlspecialchars($modeSaisie) ?>"
                        data-mode-calcul="<?= htmlspecialchars($a['mode_calcul'] ?? $a['type_taux'] ?? '') ?>"
                        data-unite="<?= htmlspecialchars($a['unite_assiette'] ?? $a['unite'] ?? '') ?>"
                    >

                        <?= htmlspecialchars(
                            $a['nature_acte']
                        ) ?>

                        <?php if (!empty($a['code_article'])): ?>

                            —
                            <?= htmlspecialchars(
                                $a['code_article']
                            ) ?>

                        <?php endif; ?>

                    </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div id="bloc_base">
                <label>Base imposable / Montant</label>
                <input
                    type="number"
                    step="0.01"
                    min="0"
                    name="base_imposable"
                    id="base_imposable"
                    value="0"
                >
                <small class="form-hint">
                    Pour les taxes et autres impôts : montant, amende, valeur ou base taxable.
                </small>
            </div>

            <div id="bloc_loyer" class="hidden-field">
                <label>Montant du loyer</label>
                <input
                    type="number"
                    step="0.01"
                    min="0"
                    name="loyer_mensuel"
                    id="loyer_mensuel"
                    value=""
                >
                <small class="form-hint">
                    Uniquement pour IRL / RL. La base imposable sera calculée selon la période choisie.
                </small>
            </div>

            <div id="bloc_quantite">
                <label>Quantité</label>
                <input
                    type="number"
                    step="1"
                    min="1"
                    name="quantite"
                    id="quantite"
                    value="1"
                >
                <small class="form-hint">
                    La quantité reste un nombre simple. Elle ne doit jamais contenir CDF ou USD.
                </small>
            </div>

            <div>

                <label>Période</label>

                <select
                    name="periode_id"
                >

                    <option value="">
                        -- Sélectionner --
                    </option>

                    <?php foreach ($periodes as $p): ?>

                    <option
                        value="<?= $p['id'] ?>"
                    >
                        <?= htmlspecialchars(
                            $p['libelle']
                        ) ?>
                    </option>

                    <?php endforeach; ?>

                </select>

            </div>

        </div>

        <br>

        <button
            class="btn-premium"
            type="submit"
        >
            ➕ Ajouter l'acte
        </button>

    </form>

</div>

<?php endif; ?>
<div class="info-card">

    <h3>Actes déjà ajoutés</h3>

    <table class="table-premium">
        <tr>
            <th>Acte taxable</th>
            <th>Base / Loyer / Qté</th>
            <th>Période</th>
            <th>Détail calcul</th>
            <th>Total</th>
            <th>Action</th>
        </tr>

        <?php foreach ($details as $d): ?>
            <tr>
                <td>
                    <strong>
                        <?= htmlspecialchars(libelleActeNTMulti($d)) ?>
                    </strong>
                    <br>
                    <small>
                        Code :
                        <?= htmlspecialchars($d['code_article'] ?? '-') ?>
                    </small>
                </td>

                <td>
                    <?= nl2br(htmlspecialchars(affichageBaseDetailNTView($d))) ?>
                </td>

                <td>
                    <?= htmlspecialchars(
                        $d['periode_libelle']
                        ??
                        $d['periodicite_info']
                        ??
                        '-'
                    ) ?>

                    <?php if (!empty($d['mois_concernes'])): ?>
                        <br>
                        <small>
                            <?= htmlspecialchars($d['mois_concernes']) ?>
                        </small>
                    <?php endif; ?>
                </td>

                <td>
                    <div class="detail-box">
                        <?= nl2br(
                            htmlspecialchars(
                                detailsCalculTextNTMulti($d)
                            )
                        ) ?>
                    </div>
                </td>

                <td>
                    <strong>
                        <?= number_format(
                            montantDetailNTMulti($d),
                            2,
                            ',',
                            ' '
                        ) ?>
                        CDF
                    </strong>
                </td>

                <td>
                    <?php if (($nt['statut'] ?? 'brouillon') === 'brouillon'): ?>
                        <a
                            class="btn-danger"
                            href="nt_remove_detail.php?numero=<?= urlencode($nt['numero_nt']) ?>&detail_id=<?= (int)$d['id'] ?>"
                            onclick="return confirm('Voulez-vous vraiment retirer cet acte de la NT ?');"
                        >
                            Retirer
                        </a>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>

        <?php if (empty($details)): ?>
            <tr>
                <td colspan="6">
                    Aucun acte ajouté à cette Note de Taxation.
                </td>
            </tr>
        <?php endif; ?>
    </table>

</div>

<?php if (($nt['statut'] ?? 'brouillon') === 'brouillon'): ?>

<div class="info-card">

    <h3>Pénalités</h3>

    <form
        method="POST"
        action="nt_update_penalites.php"
    >

        <input
            type="hidden"
            name="numero_nt"
            value="<?= htmlspecialchars($nt['numero_nt']) ?>"
        >

        <div class="grid-form">

            <div>
                <label>Pénalité d’assiette</label>

                <input
                    type="number"
                    step="0.01"
                    min="0"
                    name="penalite_assiette"
                    value="<?= htmlspecialchars($penalite_assiette) ?>"
                >
            </div>

            <div>
                <label>Pénalité de recouvrement</label>

                <input
                    type="number"
                    step="0.01"
                    min="0"
                    name="penalite_recouvrement"
                    value="<?= htmlspecialchars($penalite_recouvrement) ?>"
                >
            </div>

        </div>

        <br>

        <button
            class="btn-premium"
            type="submit"
        >
            💾 Enregistrer les pénalités
        </button>

    </form>

</div>

<?php endif; ?>

<div class="info-card">

    <h3>Récapitulatif</h3>

    <table class="table-premium">
        <tr>
            <th>Total actes constatés</th>
            <td>
                <strong>
                    <?= number_format(
                        $total_actes,
                        2,
                        ',',
                        ' '
                    ) ?>
                    CDF
                </strong>
            </td>
        </tr>

        <tr>
            <th>Pénalité d’assiette</th>
            <td>
                <?= number_format(
                    $penalite_assiette,
                    2,
                    ',',
                    ' '
                ) ?>
                CDF
            </td>
        </tr>

        <tr>
            <th>Pénalité de recouvrement</th>
            <td>
                <?= number_format(
                    $penalite_recouvrement,
                    2,
                    ',',
                    ' '
                ) ?>
                CDF
            </td>
        </tr>

        <tr>
            <th>Total général NT</th>
            <td>
                <strong>
                    <?= number_format(
                        $total_general,
                        2,
                        ',',
                        ' '
                    ) ?>
                    CDF
                </strong>
            </td>
        </tr>
    </table>

    <br>

    <div class="actions">

        <?php if (($nt['statut'] ?? 'brouillon') === 'brouillon' && !empty($details)): ?>

            <a
                class="btn-premium"
                href="nt_liquider.php?numero=<?= urlencode($nt['numero_nt']) ?>"
                onclick="return confirm('Soumettre cette NT à la liquidation ?');"
            >
                ✅ Soumettre à liquidation
            </a>

        <?php endif; ?>

        <a
            class="btn-premium"
            href="nt_list.php"
        >
            ← Retour à la liste des NT
        </a>

        <a
            class="btn-premium"
            target="_blank"
            href="../../reports/nt_pdf.php?numero=<?= urlencode($nt['numero_nt']) ?>"
        >
            🖨️ Imprimer la NT
        </a>

    </div>

</div>

</div>

</main>
</div>

<style>
.detail-box{
    background:#f8fafc;
    border:1px solid #e5e7eb;
    padding:10px;
    border-radius:12px;
    font-size:12px;
    line-height:1.6;
}
</style>


<script>
function gererChampsSaisieActeNT() {
    const articleSelect = document.getElementById('article_id');
    const blocBase = document.getElementById('bloc_base');
    const blocLoyer = document.getElementById('bloc_loyer');
    const baseInput = document.getElementById('base_imposable');
    const loyerInput = document.getElementById('loyer_mensuel');
    const quantiteInput = document.getElementById('quantite');

    if (!articleSelect || !blocBase || !blocLoyer) {
        return;
    }

    const option = articleSelect.options[articleSelect.selectedIndex];
    const modeSaisie = option ? (option.getAttribute('data-mode-saisie') || 'base') : 'base';

    if (modeSaisie === 'loyer') {
        blocBase.classList.add('hidden-field');
        blocLoyer.classList.remove('hidden-field');
        if (baseInput) baseInput.value = '';
        if (quantiteInput && (!quantiteInput.value || parseFloat(quantiteInput.value) <= 0)) {
            quantiteInput.value = 1;
        }
    } else {
        blocBase.classList.remove('hidden-field');
        blocLoyer.classList.add('hidden-field');
        if (loyerInput) loyerInput.value = '';
        if (quantiteInput && (!quantiteInput.value || parseFloat(quantiteInput.value) <= 0)) {
            quantiteInput.value = 1;
        }
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const articleSelect = document.getElementById('article_id');
    if (articleSelect) {
        articleSelect.addEventListener('change', gererChampsSaisieActeNT);
        gererChampsSaisieActeNT();
    }
});
</script>


<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('cpSmartSearchArticle');
    const directionFilter = document.getElementById('cpDirectionFilter');
    const resultsBox = document.getElementById('cpArticleResults');
    const selectedBox = document.getElementById('cpSelectedArticle');

    const articleSelect =
        document.querySelector('select[name="article_id"]') ||
        document.querySelector('select[name="acte_taxable_id"]') ||
        document.getElementById('article_id');

    let timer = null;

    function cpEscapeHtml(str) {
        return String(str || '').replace(/[&<>"']/g, function (m) {
            return ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            })[m];
        });
    }

    function cpSetSelectedArticle(item) {
        if (!articleSelect) return;

        let exists = false;

        Array.from(articleSelect.options).forEach(opt => {
            if (String(opt.value) === String(item.id)) {
                exists = true;
            }
        });

        if (!exists) {
            const option = document.createElement('option');
            option.value = item.id;
            option.textContent = item.label;
            articleSelect.appendChild(option);
        }

        articleSelect.value = item.id;

        articleSelect.dispatchEvent(new Event('change', { bubbles: true }));

        if (selectedBox) {
            selectedBox.style.display = 'block';
            selectedBox.innerHTML =
                '✅ ' + cpEscapeHtml(item.label) +
                '<br><small>' +
                cpEscapeHtml(item.mode_calcul || '') +
                ' — ' +
                cpEscapeHtml(item.taux_label || '') +
                '</small>';
        }

        if (resultsBox) {
            resultsBox.style.display = 'none';
            resultsBox.innerHTML = '';
        }
    }

    async function cpSearchArticles() {
        if (!searchInput || !resultsBox) return;

        const q = searchInput.value.trim();
        const directionId = directionFilter ? directionFilter.value : '';

        if (q.length < 2 && !directionId) {
            resultsBox.style.display = 'none';
            resultsBox.innerHTML = '';
            return;
        }

        resultsBox.style.display = 'block';
        resultsBox.innerHTML = '<div class="cp-result-item">Recherche en cours...</div>';

        const params = new URLSearchParams();
        params.set('q', q);
        params.set('limit', '50');

        if (directionId) {
            params.set('direction_id', directionId);
        }

        try {
            const res = await fetch('../../api/articles_search.php?' + params.toString(), {
                credentials: 'same-origin'
            });

            const data = await res.json();

            if (!data.success) {
                resultsBox.innerHTML = '<div class="cp-result-item">Erreur : ' + cpEscapeHtml(data.message || 'Recherche impossible') + '</div>';
                return;
            }

            if (!data.items || data.items.length === 0) {
                resultsBox.innerHTML = '<div class="cp-result-item">Aucun article trouvé.</div>';
                return;
            }

            resultsBox.innerHTML = '';

            data.items.forEach(item => {
                const div = document.createElement('div');
                div.className = 'cp-result-item';
                div.innerHTML =
                    '<div class="cp-result-title">' + cpEscapeHtml(item.label) + '</div>' +
                    '<div class="cp-result-meta">' +
                    cpEscapeHtml(item.direction_nom || '') +
                    ' | Mode : ' + cpEscapeHtml(item.mode_calcul || '') +
                    ' | Taux : ' + cpEscapeHtml(item.taux_label || '') +
                    '</div>';

                div.addEventListener('click', function () {
                    cpSetSelectedArticle(item);
                });

                resultsBox.appendChild(div);
            });

        } catch (e) {
            resultsBox.innerHTML = '<div class="cp-result-item">Erreur serveur pendant la recherche.</div>';
        }
    }

    if (searchInput) {
        searchInput.addEventListener('keyup', function () {
            clearTimeout(timer);
            timer = setTimeout(cpSearchArticles, 250);
        });
    }

    if (directionFilter) {
        directionFilter.addEventListener('change', function () {
            clearTimeout(timer);
            timer = setTimeout(cpSearchArticles, 100);
        });
    }
});
</script>

</body>
</html>