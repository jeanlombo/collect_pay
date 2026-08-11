<?php
require_once "../../config/database.php";
require_once "../../config/security.php";
require_once "../../core/functions.php";

checkAuth();
requireRole(['SUPER_ADMIN','ADMIN','PARAMETRAGE']);


function cpParamUserId(PDO $pdo): ?int
{
    $id = (int)($_SESSION['user_id'] ?? 0);
    if ($id > 0) return $id;

    $email = trim((string)($_SESSION['email'] ?? $_SESSION['user_email'] ?? ''));
    if ($email !== '') {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $id = (int)($row['id'] ?? 0);
        if ($id > 0) {
            $_SESSION['user_id'] = $id;
            return $id;
        }
    }
    return null;
}

$page_title = "Taux de change officiel";
$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $devise = $_POST['devise'];
    $taux = $_POST['taux'];
    $date_application = $_POST['date_application'];

    // Désactiver l'ancien taux actif de cette devise
    $stmt = $pdo->prepare("
        UPDATE taux_change_officiel
        SET actif = 0
        WHERE devise = ?
    ");
    $stmt->execute([$devise]);

    // Insérer le nouveau taux
    $stmt = $pdo->prepare("
        INSERT INTO taux_change_officiel
        (devise, taux, date_application, actif, user_direction_id)
        VALUES (?, ?, ?, 1, ?)
    ");

    $stmt->execute([
        $devise,
        $taux,
        $date_application,
        cpParamUserId($pdo)
    ]);

    $message = "Taux de change officiel mis à jour avec succès.";
}

$tauxActifs = $pdo->query("
    SELECT t.*, u.nom AS utilisateur
    FROM taux_change_officiel t
    LEFT JOIN users u ON t.user_direction_id = u.id
    WHERE t.actif = 1
    ORDER BY t.date_application DESC
")->fetchAll();

$historique = $pdo->query("
    SELECT t.*, u.nom AS utilisateur
    FROM taux_change_officiel t
    LEFT JOIN users u ON t.user_direction_id = u.id
    ORDER BY t.created_at DESC
    LIMIT 50
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($page_title) ?> | cOllect_Pay</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../../assets/css/admin.css">

    <style>
        .grid-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
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

        .warning {
            background: #fff7ed;
            color: #9a3412;
            padding: 12px;
            border-radius: 14px;
            font-weight: 800;
            margin-bottom: 15px;
        }

        .badge-active {
            background: #dcfce7;
            color: #166534;
            padding: 6px 10px;
            border-radius: 999px;
            font-weight: 900;
        }

        .badge-inactive {
            background: #fee2e2;
            color: #991b1b;
            padding: 6px 10px;
            border-radius: 999px;
            font-weight: 900;
        }
    </style>
<link rel="stylesheet" href="../../assets/css/parametrage.css">
</head>

<body class="cp-parametrage-page">

<div class="admin-layout">

    <?php require_once "../../includes/sidebar.php"; ?>

    <main class="main-content">

        <?php require_once "../../includes/topbar.php"; ?>

        <div class="panel cp-parametrage-panel">
            <h3>Définir le taux de change officiel</h3>

            <?php if ($message): ?>
                <div class="success"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <div class="warning">
                Le taux saisi ici sera utilisé automatiquement pour convertir les taxes en USD vers la devise officielle CDF.
                Les agents de taxation ne pourront pas modifier ce taux.
            </div>

            <form method="POST">
                <div class="grid-3">
                    <select name="devise" required>
                        <option value="USD">USD vers CDF</option>
                    </select>

                    <input type="number"
                           step="0.0001"
                           name="taux"
                           placeholder="Ex: 2800"
                           required>

                    <input type="date"
                           name="date_application"
                           value="<?= date('Y-m-d') ?>"
                           required>
                </div>

                <button type="submit">Enregistrer le taux officiel</button>
            </form>
        </div>

        <div class="panel cp-parametrage-panel">
            <h3>Taux actif actuel</h3>

            <table class="table-premium cp-parametrage-table">
                <tr>
                    <th>Devise</th>
                    <th>Taux</th>
                    <th>Date application</th>
                    <th>Défini par</th>
                    <th>Statut</th>
                </tr>

                <?php foreach($tauxActifs as $t): ?>
                    <tr>
                        <td><?= htmlspecialchars($t['devise']) ?></td>
                        <td><strong><?= number_format($t['taux'], 4, ',', ' ') ?> CDF</strong></td>
                        <td><?= htmlspecialchars($t['date_application']) ?></td>
                        <td><?= htmlspecialchars($t['utilisateur'] ?? '-') ?></td>
                        <td><span class="badge-active">Actif</span></td>
                    </tr>
                <?php endforeach; ?>

                <?php if(empty($tauxActifs)): ?>
                    <tr>
                        <td colspan="5">Aucun taux actif configuré.</td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>

        <div class="panel cp-parametrage-panel">
            <h3>Historique des taux</h3>

            <table class="table-premium cp-parametrage-table">
                <tr>
                    <th>Devise</th>
                    <th>Taux</th>
                    <th>Date application</th>
                    <th>Défini par</th>
                    <th>Statut</th>
                    <th>Date création</th>
                </tr>

                <?php foreach($historique as $h): ?>
                    <tr>
                        <td><?= htmlspecialchars($h['devise']) ?></td>
                        <td><?= number_format($h['taux'], 4, ',', ' ') ?> CDF</td>
                        <td><?= htmlspecialchars($h['date_application']) ?></td>
                        <td><?= htmlspecialchars($h['utilisateur'] ?? '-') ?></td>
                        <td>
                            <?php if($h['actif']): ?>
                                <span class="badge-active">Actif</span>
                            <?php else: ?>
                                <span class="badge-inactive">Inactif</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($h['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php if(empty($historique)): ?>
                    <tr>
                        <td colspan="6">Aucun historique trouvé.</td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>

    </main>
</div>

</body>
</html>