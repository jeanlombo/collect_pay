<?php
require_once "../../config/database.php";
require_once "../../config/security.php";
require_once "../../core/functions.php";

checkAuth();

$page_title = "Modes de paiement";
$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $code = strtoupper(trim($_POST['code']));
    $libelle = trim($_POST['libelle']);

    if ($code && $libelle) {
        $stmt = $pdo->prepare("
            INSERT INTO modes_paiement (code, libelle, actif)
            VALUES (?, ?, 1)
            ON DUPLICATE KEY UPDATE
                libelle = VALUES(libelle),
                actif = 1
        ");
        $stmt->execute([$code, $libelle]);

        $message = "Mode de paiement enregistré avec succès.";
    }
}

$modes = $pdo->query("
    SELECT *
    FROM modes_paiement
    ORDER BY id ASC
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($page_title) ?> | cOllect_Pay</title>
    <link rel="stylesheet" href="/collect_pay/assets/css/admin.css">

    <style>
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
</head>
<body>

<div class="admin-layout">

    <?php require_once "../../includes/sidebar.php"; ?>

    <main class="main-content">

        <?php require_once "../../includes/topbar.php"; ?>

        <div class="panel">
            <h3>Configurer les modes de paiement</h3>

            <?php if ($message): ?>
                <div class="success"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="grid-2">
                    <input type="text" name="code" placeholder="Code ex: MOMO" required>
                    <input type="text" name="libelle" placeholder="Libellé ex: Mobile Money" required>
                </div>

                <button type="submit">Enregistrer</button>
            </form>
        </div>

        <div class="panel">
            <h3>Liste des modes de paiement</h3>

            <table class="table-premium">
                <tr>
                    <th>ID</th>
                    <th>Code</th>
                    <th>Libellé</th>
                    <th>Statut</th>
                </tr>

                <?php foreach($modes as $m): ?>
                    <tr>
                        <td><?= $m['id'] ?></td>
                        <td><strong><?= htmlspecialchars($m['code']) ?></strong></td>
                        <td><?= htmlspecialchars($m['libelle']) ?></td>
                        <td>
                            <?php if($m['actif']): ?>
                                <span class="badge-active">Actif</span>
                            <?php else: ?>
                                <span class="badge-inactive">Inactif</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if(empty($modes)): ?>
                    <tr>
                        <td colspan="4">Aucun mode de paiement configuré.</td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>

    </main>
</div>

</body>
</html>