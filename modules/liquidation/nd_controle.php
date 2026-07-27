<?php
/*
|--------------------------------------------------------------------------
| cOllect_Pay - Contrôle / Vérification ND
|--------------------------------------------------------------------------
| Fichier : modules/liquidation/nd_controle.php
|--------------------------------------------------------------------------
*/

require_once "../../auth/check_auth.php";
require_once "../../core/functions.php";



$numero_nd = $_GET['numero'] ?? null;

if (!$numero_nd) {
    die("Numéro ND manquant.");
}

$stmt = $pdo->prepare("
    SELECT 
        nd.*,
        nt.numero_nt,
        c.raison_sociale,
        c.nom,
        c.postnom,
        c.prenom,
        c.telephone
    FROM notes_debit nd
    JOIN notes_taxation nt ON nd.note_taxation_id = nt.id
    JOIN contribuables c ON nt.contribuable_id = c.id
    WHERE nd.numero_nd = ?
    LIMIT 1
");
$stmt->execute([$numero_nd]);
$nd = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$nd) {
    die("ND introuvable.");
}

if (!in_array($nd['statut'], ['brouillon', 'en_controle'], true)) {
    die("Cette ND est déjà traitée.");
}

function nomContribuableControleND(array $c): string
{
    if (!empty($c['raison_sociale'])) {
        return $c['raison_sociale'];
    }

    return trim(($c['nom'] ?? '') . ' ' . ($c['postnom'] ?? '') . ' ' . ($c['prenom'] ?? ''));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $decision = $_POST['decision'] ?? '';
    $observation = trim($_POST['observation'] ?? '');

    if (!in_array($decision, ['conforme', 'rejeter', 'corriger'], true)) {
        die("Décision invalide.");
    }

    if ($decision === 'conforme') {
        $statut = 'validee';
    } elseif ($decision === 'rejeter') {
        $statut = 'rejete';
    } else {
        $statut = 'en_controle';
    }

    if (($decision === 'rejeter' || $decision === 'corriger') && $observation === '') {
        die("Observation obligatoire pour une correction ou un rejet.");
    }

    $stmt = $pdo->prepare("
        UPDATE notes_debit
        SET 
            statut = ?,
            decision = ?,
            observation = ?,
            date_validation = NOW(),
            user_validateur_id = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $statut,
        $decision,
        $observation,
        $_SESSION['user_id'] ?? null,
        $nd['id']
    ]);

    if (function_exists('logAction')) {
        logAction('controle', 'validate', "Contrôle ND : " . $numero_nd . " décision : " . $decision);
    }

    if ($statut === 'validee') {
        header("Location: nd_view.php?numero=" . urlencode($numero_nd) . "&controle=validee");
        exit;
    }

    header("Location: nd_view.php?numero=" . urlencode($numero_nd) . "&controle=" . urlencode($statut));
    exit;
}

$page_title = "Contrôle / Vérification ND";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($page_title) ?> | cOllect_Pay</title>
    <link rel="stylesheet" href="/collect_pay/assets/css/admin.css">

    <style>
        .verify-box {
            background: white;
            border-radius: 24px;
            padding: 28px;
            box-shadow: 0 15px 35px rgba(0,0,0,.08);
        }

        .verify-header {
            background: linear-gradient(135deg, #06152b, #0f3460);
            color: white;
            padding: 24px;
            border-radius: 20px;
            margin-bottom: 25px;
        }

        .verify-header h2 {
            margin: 0 0 10px;
            font-weight: 900;
        }

        .verify-header p {
            margin: 6px 0;
        }

        .decision-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 20px;
        }

        .decision-card {
            border: 2px solid #e5e7eb;
            border-radius: 18px;
            padding: 18px;
            cursor: pointer;
            background: #f8fafc;
            font-weight: 800;
            display:flex;
            align-items:center;
            gap:8px;
        }

        .decision-card:hover {
            border-color: #0f3460;
            background:#eff6ff;
        }

        textarea {
            width: 100%;
            min-height: 130px;
            border-radius: 16px;
            border: 1px solid #d1d5db;
            padding: 14px;
            font-size: 15px;
            font-family: inherit;
        }

        .btn-submit {
            background: #0f3460;
            color: white;
            border: none;
            padding: 14px 22px;
            border-radius: 14px;
            font-weight: 900;
            cursor: pointer;
            margin-top: 18px;
        }

        .btn-back {
            display: inline-block;
            margin-top: 18px;
            text-decoration: none;
            color: #0f3460;
            font-weight: 800;
        }

        .alert-info{
            background:#eff6ff;
            color:#1e3a8a;
            border:1px solid #bfdbfe;
            padding:12px 14px;
            border-radius:14px;
            font-weight:800;
            margin-bottom:18px;
        }

        @media(max-width:900px){
            .decision-grid{
                grid-template-columns:1fr;
            }
        }
    </style>
</head>
<body>

<div class="admin-layout">

    <?php require_once "../../includes/sidebar.php"; ?>

    <main class="main-content">

        <?php require_once "../../includes/topbar.php"; ?>

        <div class="verify-box">

            <div class="verify-header">
                <h2>Contrôle / Vérification de la Note de Débit</h2>
                <p>ND : <strong><?= htmlspecialchars($nd['numero_nd']) ?></strong></p>
                <p>Référence NT : <strong><?= htmlspecialchars($nd['numero_nt']) ?></strong></p>
                <p>Contribuable : <strong><?= htmlspecialchars(nomContribuableControleND($nd)) ?></strong></p>
                <p>Montant : <strong><?= number_format((float)($nd['montant_total'] ?: ($nd['total_exigible'] ?? 0)), 2, ',', ' ') ?> CDF</strong></p>
            </div>

            <div class="alert-info">
                Le contrôleur peut valider la ND, demander une correction ou rejeter la ND avec observation.
            </div>

            <form method="POST">

                <h3>Décision du vérificateur</h3>

                <div class="decision-grid">
                    <label class="decision-card">
                        <input type="radio" name="decision" value="conforme" required>
                        ✅ Conforme
                    </label>

                    <label class="decision-card">
                        <input type="radio" name="decision" value="corriger" required>
                        🛠️ À corriger
                    </label>

                    <label class="decision-card">
                        <input type="radio" name="decision" value="rejeter" required>
                        ❌ Rejeter
                    </label>
                </div>

                <h3>Observation / Motif</h3>

                <textarea name="observation"
                          placeholder="Saisir l'observation du vérificateur ou le motif du rejet/correction..."></textarea>

                <button type="submit" class="btn-submit">
                    Valider la décision
                </button>
            </form>

            <a class="btn-back" href="nd_view.php?numero=<?= urlencode($nd['numero_nd']) ?>">
                ← Retour à la ND
            </a>

        </div>

    </main>
</div>

</body>
</html>
