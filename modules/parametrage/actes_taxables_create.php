<?php
require_once "../../config/database.php";
require_once "../../config/security.php";

checkAuth();

requireRole([
    'SUPER_ADMIN',
    'ADMIN',
    'PARAMETRAGE'
]);

$page_title = "Créer un acte taxable";

$success = "";
$error = "";

$articles = $pdo->query("
    SELECT 
        ab.id,
        ab.code_article,
        ab.nature_acte,
        s.nom_service
    FROM articles_budgetaires ab
    LEFT JOIN services_assiette s ON ab.service_id = s.id
    WHERE ab.actif = 1
    ORDER BY s.nom_service ASC, ab.code_article ASC
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $article_budgetaire_id = (int)($_POST['article_budgetaire_id'] ?? 0);
    $libelle_acte = trim($_POST['libelle_acte'] ?? '');
    $periodicite = $_POST['periodicite'] ?? 'ponctuelle';
    $mode_calcul = $_POST['mode_calcul'] ?? 'fixe';
    $unite_assiette = trim($_POST['unite_assiette'] ?? '');
    $devise_base = $_POST['devise_base'] ?? 'CDF';
    $taux_acte = (float)($_POST['taux_acte'] ?? 0);
    $type_calcul = $_POST['type_calcul'] ?? 'fixe';
    $taux_pourcentage = (float)($_POST['taux_pourcentage'] ?? 0);
    $taux_irl_physique = (float)($_POST['taux_irl_physique'] ?? 0);
    $taux_irl_entreprise = (float)($_POST['taux_irl_entreprise'] ?? 0);
    $taux_rl = (float)($_POST['taux_rl'] ?? 0);
    $base_calcul = trim($_POST['base_calcul'] ?? 'montant');
    $actif = isset($_POST['actif']) ? 1 : 0;

    if ($article_budgetaire_id <= 0) {
        $error = "Veuillez sélectionner un article budgétaire.";
    } elseif ($libelle_acte === '') {
        $error = "Veuillez saisir le libellé de l'acte taxable.";
    } else {

        if (in_array($type_calcul, ['irl', 'rl', 'irl_rl'])) {
            $base_calcul = 'loyer_mensuel';
            $unite_assiette = 'Montant du loyer mensuel';
            $mode_calcul = 'pourcentage';
        }

        if ($type_calcul === 'irl') {
            $taux_irl_physique = $taux_irl_physique > 0 ? $taux_irl_physique : 10;
            $taux_irl_entreprise = $taux_irl_entreprise > 0 ? $taux_irl_entreprise : 15;
            $taux_rl = 0;
        }

        if ($type_calcul === 'rl') {
            $taux_irl_physique = 0;
            $taux_irl_entreprise = 0;
            $taux_rl = $taux_rl > 0 ? $taux_rl : 2;
        }

        if ($type_calcul === 'irl_rl') {
            $taux_irl_physique = $taux_irl_physique > 0 ? $taux_irl_physique : 10;
            $taux_irl_entreprise = $taux_irl_entreprise > 0 ? $taux_irl_entreprise : 15;
            $taux_rl = $taux_rl > 0 ? $taux_rl : 2;
        }

        $stmt = $pdo->prepare("
            INSERT INTO actes_taxables
            (
                article_budgetaire_id,
                libelle_acte,
                periodicite,
                mode_calcul,
                unite_assiette,
                devise_base,
                taux_acte,
                type_calcul,
                taux_pourcentage,
                taux_irl,
                taux_rl,
                taux_irl_physique,
                taux_irl_entreprise,
                base_calcul,
                actif
            )
            VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?)
        ");

        try {
            $stmt->execute([
                $article_budgetaire_id,
                $libelle_acte,
                $periodicite,
                $mode_calcul,
                $unite_assiette,
                $devise_base,
                $taux_acte,
                $type_calcul,
                $taux_pourcentage,
                $taux_rl,
                $taux_irl_physique,
                $taux_irl_entreprise,
                $base_calcul,
                $actif
            ]);

            $success = "Acte taxable créé avec succès.";

        } catch (Exception $e) {
            $error = "Erreur création acte taxable : " . $e->getMessage();
        }
    }
}
?>