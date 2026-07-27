<?php
require_once "../../config/database.php";
require_once "../../config/security.php";
require_once "../../core/functions.php";

checkAuth();

$page_title = "Taux par Province";

$article_id = $_GET['article_id'] ?? null;

if (!$article_id) {
    die("Article budgétaire manquant.");
}

$stmt = $pdo->prepare("
    SELECT 
        a.*,
        d.nom_direction,
        s.nom_service
    FROM articles_budgetaires a
    LEFT JOIN directions d ON a.direction_id = d.id
    LEFT JOIN services_assiette s ON a.service_id = s.id
    WHERE a.id = ?
");
$stmt->execute([$article_id]);
$article = $stmt->fetch();

if (!$article) {
    die("Article introuvable.");
}

$message = "";

$provinces = $pdo->query("
    SELECT * FROM provinces
    WHERE actif = 1
    ORDER BY nom ASC
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $province_id = $_POST['province_id'];
    $devise = $_POST['devise'];
    $taux_acte = $_POST['taux_acte'] ?: 0;
    $frais_administratif = $_POST['frais_administratif'] ?: 0;
    $frais_technique = $_POST['frais_technique'] ?: 0;
    $taux_pourcentage = $_POST['taux_pourcentage'] ?: 0;
    $frais_admin_type = $_POST['frais_admin_type'];
    $frais_tech_type = $_POST['frais_tech_type'];

    $taux_total = (float)$taux_acte + (float)$frais_administratif + (float)$frais_technique;

    $check = $pdo->prepare("
        SELECT id 
        FROM article_taux_province
        WHERE article_id = ?
        AND province_id = ?
        LIMIT 1
    ");
    $check->execute([$article_id, $province_id]);
    $existing = $check->fetch();

    if ($existing) {
        $stmt = $pdo->prepare("
            UPDATE article_taux_province
            SET 
                devise = ?,
                taux_acte = ?,
                frais_administratif = ?,
                frais_technique = ?,
                taux_total = ?,
                taux_pourcentage = ?,
                frais_admin_type = ?,
                frais_tech_type = ?,
                actif = 1
            WHERE id = ?
        ");

        $stmt->execute([
            $devise,
            $taux_acte,
            $frais_administratif,
            $frais_technique,
            $taux_total,
            $taux_pourcentage,
            $frais_admin_type,
            $frais_tech_type,
            $existing['id']
        ]);

        $message = "Taux mis à jour avec succès.";
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO article_taux_province
            (
                article_id,
                province_id,
                devise,
                taux_acte,
                frais_administratif,
                frais_technique,
                taux_total,
                taux_pourcentage,
                frais_admin_type,
                frais_tech_type,
                actif
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
        ");

        $stmt->execute([
            $article_id,
            $province_id,
            $devise,
            $taux_acte,
            $frais_administratif,
            $frais_technique,
            $taux_total,
            $taux_pourcentage,
            $frais_admin_type,
            $frais_tech_type
        ]);

        $message = "Taux enregistré avec succès.";
    }
}

$stmt = $pdo->prepare("
    SELECT 
        tp.*,
        p.nom AS province,
        p.code_province
    FROM article_taux_province tp
    JOIN provinces p ON tp.province_id = p.id
    WHERE tp.article_id = ?
    ORDER BY p.nom ASC
");
$stmt->execute([$article_id]);
$taux = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($page_title) ?> | cOllect_Pay</title>
    <link rel="stylesheet" href="/collect_pay/assets/css/admin.css">

    <style>
        .article-box {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            padding: 18px;
            border-radius: 18px;
            margin-bottom: 20px;
        }

        .grid-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 14px;
        }

        .success {
            background: #ecfdf5;
            color: #047857;
            padding: 12px;
            border-radius: 14px;
            font-weight: 800;
            margin-bottom: 15px;
        }

        .badge {
            padding: 6px 10px;
            border-radius: 999px;
            background: #e0ecff;
            color: #0f3460;
            font-weight: 800;
            font-size: 12px;
        }
    </style>
</head>

<body>

<div class="admin-layout">

    <?php require_once "../../includes/sidebar.php"; ?>

    <main class="main-content">

        <?php require_once "../../includes/topbar.php"; ?>

        <div class="panel">

            <h3>Configuration des taux par Province</h3>

            <div class="article-box">
                <strong>Code article :</strong> <?= htmlspecialchars($article['code_article']) ?><br>
                <strong>ART-PAR :</strong> <?= htmlspecialchars($article['art_par'] ?? '-') ?><br>
                <strong>Secteur :</strong> <?= htmlspecialchars($article['secteur'] ?? '-') ?><br>
                <strong>Direction :</strong> <?= htmlspecialchars($article['nom_direction'] ?? '-') ?><br>
                <strong>Service :</strong> <?= htmlspecialchars($article['nom_service'] ?? '-') ?><br>
                <strong>Nature d’acte :</strong><br>
                <?= nl2br(htmlspecialchars($article['nature_acte'])) ?><br>
                <strong>Périodicité :</strong> <?= htmlspecialchars($article['periodicite']) ?><br>
                <strong>Mode de calcul :</strong> <?= htmlspecialchars($article['mode_calcul']) ?>
            </div>

            <?php if ($message): ?>
                <div class="success"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form method="POST">

                <div class="grid-3">
                    <select name="province_id" required>
                        <option value="">-- Province --</option>
                        <?php foreach($provinces as $p): ?>
                            <option value="<?= $p['id'] ?>">
                                <?= htmlspecialchars($p['nom']) ?> (<?= htmlspecialchars($p['code_province']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="devise" required>
                        <option value="USD">USD</option>
                        <option value="CDF">CDF</option>
                    </select>

                    <input type="number" step="0.000001" name="taux_acte" placeholder="Taux acte / Principal">
                </div>

                <div class="grid-3">
                    <input type="number" step="0.000001" name="frais_administratif" placeholder="Frais administratif">
                    <input type="number" step="0.000001" name="frais_technique" placeholder="Frais technique">
                    <input type="number" step="0.000001" name="taux_pourcentage" placeholder="Taux % si applicable">
                </div>

                <div class="grid-2">
                    <select name="frais_admin_type" required>
                        <option value="fixe">Frais admin fixe</option>
                        <option value="pourcentage_de_taxe">Frais admin % de la taxe</option>
                        <option value="aucun">Aucun frais admin</option>
                    </select>

                    <select name="frais_tech_type" required>
                        <option value="fixe">Frais tech fixe</option>
                        <option value="pourcentage_de_taxe">Frais tech % de la taxe</option>
                        <option value="aucun">Aucun frais tech</option>
                    </select>
                </div>

                <button type="submit">Enregistrer le taux</button>
            </form>
        </div>

        <div class="panel">
            <h3>Taux configurés</h3>

            <table class="table-premium">
                <tr>
                    <th>Province</th>
                    <th>Devise</th>
                    <th>Taux acte</th>
                    <th>Frais admin</th>
                    <th>Frais tech</th>
                    <th>Taux total</th>
                    <th>Taux %</th>
                    <th>Types frais</th>
                    <th>Statut</th>
                </tr>

                <?php foreach($taux as $t): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($t['province']) ?></strong><br>
                            <?= htmlspecialchars($t['code_province']) ?>
                        </td>
                        <td><span class="badge"><?= htmlspecialchars($t['devise']) ?></span></td>
                        <td><?= number_format($t['taux_acte'], 6, ',', ' ') ?></td>
                        <td><?= number_format($t['frais_administratif'], 6, ',', ' ') ?></td>
                        <td><?= number_format($t['frais_technique'], 6, ',', ' ') ?></td>
                        <td><strong><?= number_format($t['taux_total'], 6, ',', ' ') ?></strong></td>
                        <td><?= number_format($t['taux_pourcentage'], 6, ',', ' ') ?>%</td>
                        <td>
                            Admin : <?= htmlspecialchars($t['frais_admin_type']) ?><br>
                            Tech : <?= htmlspecialchars($t['frais_tech_type']) ?>
                        </td>
                        <td><?= $t['actif'] ? 'Actif' : 'Inactif' ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php if(empty($taux)): ?>
                    <tr>
                        <td colspan="9">Aucun taux configuré pour cet article.</td>
                    </tr>
                <?php endif; ?>
            </table>

            <br>
            <a href="articles_budgetaires.php">← Retour à la nomenclature</a>
        </div>

    </main>
</div>

</body>
</html>