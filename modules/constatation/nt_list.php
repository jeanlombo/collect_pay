<?php
require_once "../../config/database.php";
require_once "../../config/security.php";
require_once "../../core/functions.php";



$page_title = "Liste des Notes de Taxation";

$search = $_GET['search'] ?? '';
$statut = $_GET['statut'] ?? '';
$centre_id = $_SESSION['centre_id'];

$sql = "
    SELECT 
        nt.*,
        c.raison_sociale,
        c.nom,
        c.postnom,
        c.prenom,
        c.telephone
    FROM notes_taxation nt
    JOIN contribuables c ON nt.contribuable_id = c.id
    WHERE nt.centre_id = ?
";

$params = [$centre_id];

if (!empty($search)) {
    $sql .= "
        AND (
            nt.numero_nt LIKE ?
            OR c.raison_sociale LIKE ?
            OR c.nom LIKE ?
            OR c.telephone LIKE ?
        )
    ";

    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($statut)) {
    $sql .= " AND nt.statut = ?";
    $params[] = $statut;
}

$sql .= " ORDER BY nt.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$notes = $stmt->fetchAll();

function nomContribuableList($c) {
    if (!empty($c['raison_sociale'])) {
        return $c['raison_sociale'];
    }

    return trim(($c['nom'] ?? '') . ' ' . ($c['postnom'] ?? '') . ' ' . ($c['prenom'] ?? ''));
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= $page_title ?> | cOllect_Pay</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/constatation.css">
</head>
<body class="cp-constatation-page cp-nt-list">

<div class="admin-layout">
    <?php require_once "../../includes/sidebar.php"; ?>

    <main class="main-content">
        <?php require_once "../../includes/topbar.php"; ?>

        <div class="panel cp-nt-shell">
            <div class="cp-list-heading">
                <h3>Notes de Taxation</h3>
                <a href="../contribuables/list.php" class="cp-primary-link">
                    + Nouvelle NT
                </a>
            </div>

            <form method="GET" class="cp-filter-bar">
                <input type="text" name="search" placeholder="Rechercher numéro, contribuable, téléphone"
                       value="<?= htmlspecialchars($search) ?>">

                <select name="statut">
                    <option value="">Tous statuts</option>
                    <option value="brouillon" <?= $statut=='brouillon'?'selected':'' ?>>Brouillon</option>
                    <option value="liquidee" <?= $statut=='liquidee'?'selected':'' ?>>Liquidée</option>
                    <option value="rejete" <?= $statut=='rejete'?'selected':'' ?>>Rejetée</option>
                </select>

                <button type="submit">Filtrer</button>
            </form>
        </div>

        <div class="panel cp-table-panel">
            <div class="cp-table-wrap">
            <table class="table-premium cp-nt-table">
                <tr>
                    <th>Numéro NT</th>
                    <th>Contribuable</th>
                    <th>Contact</th>
                    <th>Exercice</th>
                    <th>Total</th>
                    <th>Statut</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>

                <?php foreach($notes as $n): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($n['numero_nt']) ?></strong></td>
                        <td><?= htmlspecialchars(nomContribuableList($n)) ?></td>
                        <td><?= htmlspecialchars($n['telephone'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($n['exercice']) ?></td>
                        <td><?= number_format($n['total_estime'], 2, ',', ' ') ?> CDF</td>
                        <td><?= strtoupper(htmlspecialchars($n['statut'])) ?></td>
                        <td><?= htmlspecialchars($n['created_at']) ?></td>
                        <td>
                            <a class="cp-view-link" href="nt_view.php?numero=<?= urlencode($n['numero_nt']) ?>">Voir</a>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if(empty($notes)): ?>
                    <tr>
                        <td colspan="8">Aucune Note de Taxation trouvée.</td>
                    </tr>
                <?php endif; ?>
            </table>
            </div>
        </div>

    </main>
</div>

</body>
</html>