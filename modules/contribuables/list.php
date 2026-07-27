<?php
require_once "../../config/database.php";
require_once "../../config/security.php";
require_once "../../core/functions.php";

checkAuth();

$page_title = "Liste des Contribuables";

$search = $_GET['search'] ?? '';
$statut = $_GET['statut'] ?? '';

$sql = "
    SELECT *
    FROM contribuables
    WHERE 1=1
";

$params = [];

if (!empty($search)) {
    $sql .= "
        AND (
            code_contribuable LIKE ?
            OR raison_sociale LIKE ?
            OR nom LIKE ?
            OR telephone LIKE ?
            OR nif LIKE ?
            OR rccm LIKE ?
        )
    ";

    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($statut)) {
    $sql .= " AND statut = ?";
    $params[] = $statut;
}

$sql .= " ORDER BY id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$contribuables = $stmt->fetchAll();

function nomContribuable($c) {
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
    <link rel="stylesheet" href="/collect_pay/assets/css/admin.css">
</head>
<body>

<div class="admin-layout">
    <?php require_once "../../includes/sidebar.php"; ?>

    <main class="main-content">
        <?php require_once "../../includes/topbar.php"; ?>

        <div class="panel">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <h3>Registre des contribuables</h3>

                <a href="create.php" style="
                    background:#0f3460;
                    color:white;
                    padding:11px 18px;
                    border-radius:12px;
                    text-decoration:none;
                    font-weight:800;
                ">
                    + Nouveau contribuable
                </a>
            </div>

            <form method="GET" style="margin-top:20px;display:grid;grid-template-columns:2fr 1fr auto;gap:12px;">
                <input type="text" name="search" placeholder="Recherche : code, nom, NIF, RCCM, téléphone"
                       value="<?= htmlspecialchars($search) ?>">

                <select name="statut">
                    <option value="">Tous statuts</option>
                    <option value="actif" <?= $statut=='actif'?'selected':'' ?>>Actif</option>
                    <option value="suspendu" <?= $statut=='suspendu'?'selected':'' ?>>Suspendu</option>
                    <option value="radie" <?= $statut=='radie'?'selected':'' ?>>Radié</option>
                    <option value="decede" <?= $statut=='decede'?'selected':'' ?>>Décédé</option>
                    <option value="contentieux" <?= $statut=='contentieux'?'selected':'' ?>>Contentieux</option>
                </select>

                <button type="submit">Filtrer</button>
            </form>
        </div>

        <div class="panel">
            <table class="table-premium">
                <tr>
                    <th>Code</th>
                    <th>Contribuable</th>
                    <th>Type</th>
                    <th>NIF</th>
                    <th>RCCM</th>
                    <th>Téléphone</th>
                    <th>Ville</th>
                    <th>Statut</th>
                    <th>Action</th>
                </tr>

                <?php foreach($contribuables as $c): ?>
                    <tr>
                        <td><?= htmlspecialchars($c['code_contribuable'] ?? '-') ?></td>
                        <td><strong><?= htmlspecialchars(nomContribuable($c)) ?></strong></td>
                        <td><?= htmlspecialchars($c['type_personne']) ?></td>
                        <td><?= htmlspecialchars($c['nif'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($c['rccm'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($c['telephone'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($c['ville'] ?? '-') ?></td>
                        <td><?= strtoupper(htmlspecialchars($c['statut'])) ?></td>
                        <td>
                            <a href="view.php?id=<?= $c['id'] ?>">Voir</a> |
                            <a href="edit.php?id=<?= $c['id'] ?>">Modifier</a>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if(empty($contribuables)): ?>
                    <tr>
                        <td colspan="9">Aucun contribuable trouvé.</td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>
    </main>
</div>

</body>
</html>