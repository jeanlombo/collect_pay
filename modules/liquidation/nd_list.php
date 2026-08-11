<?php
/*
|--------------------------------------------------------------------------
| cOllect_Pay - Liste Notes de Débit
|--------------------------------------------------------------------------
| Accès corrigé :
| - LIQUIDATEUR : liquidation/view
| - CONTROLEUR : controle/view
| - ORDONNATEUR : ordonnancement/view
|--------------------------------------------------------------------------
*/

require_once "../../auth/check_auth.php";
require_once "../../core/functions.php";

checkAuth();

$isControleur   = function_exists('canDo') && canDo('controle', 'view');
$isLiquidateur  = function_exists('canDo') && canDo('liquidation', 'view');
$isOrdonnateur  = function_exists('canDo') && canDo('ordonnancement', 'view');
$isSuperAdmin   = strtoupper($_SESSION['role'] ?? '') === 'SUPER_ADMIN';

if (!$isSuperAdmin && !$isControleur && !$isLiquidateur && !$isOrdonnateur) {
    requirePermission('liquidation', 'view');
}

$page_title = "Liste des Notes de Débit";

$search = trim($_GET['search'] ?? '');
$statut = trim($_GET['statut'] ?? '');
$mode   = trim($_GET['mode'] ?? '');

$centre_id = $_SESSION['centre_id'] ?? null;

$sql = "
    SELECT
        nd.*,
        nt.numero_nt,
        nt.centre_id AS nt_centre_id,
        c.raison_sociale,
        c.nom,
        c.postnom,
        c.prenom,
        c.telephone
    FROM notes_debit nd
    JOIN notes_taxation nt ON nd.note_taxation_id = nt.id
    JOIN contribuables c ON nt.contribuable_id = c.id
    WHERE 1=1
";

$params = [];

/*
|--------------------------------------------------------------------------
| Filtre centre
|--------------------------------------------------------------------------
| Le contrôleur et l’ordonnateur peuvent voir les ND de leur flux.
|--------------------------------------------------------------------------
*/
if (!$isControleur && !$isOrdonnateur && !$isSuperAdmin && !empty($centre_id)) {
    $sql .= " AND nt.centre_id = ?";
    $params[] = $centre_id;
}

/*
|--------------------------------------------------------------------------
| Filtre statut
|--------------------------------------------------------------------------
*/
if (!empty($statut)) {
    $sql .= " AND nd.statut = ?";
    $params[] = $statut;
} else {
    if ($isControleur && !$isLiquidateur && !$isOrdonnateur && !$isSuperAdmin) {
        $sql .= " AND nd.statut IN ('brouillon','en_controle','validee','rejete')";
    }

    if ($isOrdonnateur && !$isLiquidateur && !$isControleur && !$isSuperAdmin) {
        $sql .= " AND nd.statut = 'validee'";
    }
}

/*
|--------------------------------------------------------------------------
| Recherche
|--------------------------------------------------------------------------
*/
if (!empty($search)) {
    $sql .= "
        AND (
            nd.numero_nd LIKE ?
            OR nt.numero_nt LIKE ?
            OR c.raison_sociale LIKE ?
            OR c.nom LIKE ?
            OR c.postnom LIKE ?
            OR c.prenom LIKE ?
            OR c.telephone LIKE ?
        )
    ";

    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql .= "
    ORDER BY 
        CASE 
            WHEN nd.statut = 'validee' THEN 1
            WHEN nd.statut = 'en_controle' THEN 2
            WHEN nd.statut = 'brouillon' THEN 3
            WHEN nd.statut = 'rejete' THEN 4
            ELSE 5
        END,
        nd.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

function nomContribuableNDList($c) {
    if (!empty($c['raison_sociale'])) {
        return $c['raison_sociale'];
    }

    return trim(($c['nom'] ?? '') . ' ' . ($c['postnom'] ?? '') . ' ' . ($c['prenom'] ?? ''));
}

function badgeStatutND($statut) {
    $statut = strtolower((string)$statut);

    $map = [
        'brouillon' => ['Brouillon', '#f3f4f6', '#374151'],
        'en_controle' => ['En contrôle', '#dbeafe', '#1e40af'],
        'validee' => ['Validée', '#dcfce7', '#166534'],
        'rejete' => ['Rejetée', '#fee2e2', '#991b1b'],
    ];

    $item = $map[$statut] ?? [strtoupper($statut), '#f3f4f6', '#374151'];

    return '<span style="display:inline-block;padding:6px 10px;border-radius:999px;background:' . $item[1] . ';color:' . $item[2] . ';font-weight:900;font-size:12px;">' . htmlspecialchars($item[0]) . '</span>';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($page_title) ?> | cOllect_Pay</title>
    <link rel="stylesheet" href="../../assets/css/admin.css">

    <style>
        .filter-form{
            margin-top:20px;
            display:grid;
            grid-template-columns:2fr 1fr auto;
            gap:12px;
        }
        .filter-form input,
        .filter-form select{
            width:100%;
            padding:12px 14px;
            border:1px solid #d1d5db;
            border-radius:12px;
            font-weight:800;
        }
        .filter-form button{
            background:#0f3460;
            color:white;
            border:none;
            border-radius:12px;
            padding:12px 18px;
            font-weight:900;
            cursor:pointer;
        }
        .btn-action{
            display:inline-block;
            padding:7px 10px;
            border-radius:10px;
            text-decoration:none;
            font-weight:900;
            font-size:12px;
            margin:2px;
        }
        .btn-view{
            background:#e5e7eb;
            color:#111827;
        }
        .btn-control{
            background:#0f3460;
            color:white;
        }
        .btn-ordo{
            background:#166534;
            color:white;
        }
        .btn-print{
            background:#f6b21a;
            color:#06152b;
        }
        .info-mini{
            background:#eff6ff;
            color:#1e3a8a;
            border:1px solid #bfdbfe;
            padding:12px 14px;
            border-radius:14px;
            font-weight:800;
            margin-top:12px;
        }
        @media(max-width:900px){
            .filter-form{
                grid-template-columns:1fr;
            }
        }
    </style>
<link rel="stylesheet" href="../../assets/css/liquidation.css">
</head>
<body class="cp-liquidation-page cp-nd-list">

<div class="admin-layout">
    <?php require_once "../../includes/sidebar.php"; ?>

    <main class="main-content">
        <?php require_once "../../includes/topbar.php"; ?>

        <div class="panel cp-liquidation-panel">
            <div class="cp-section-head"><div><span class="cp-eyebrow">Liquidation</span><h3>Notes de Débit</h3><p>Suivi, contrôle, impression et transmission des Notes de Débit.</p></div></div>

            <?php if ($isControleur): ?>
                <div class="info-mini">
                    Mode contrôleur : affichage des ND à contrôler, validées et rejetées.
                </div>
            <?php endif; ?>

            <?php if ($isOrdonnateur): ?>
                <div class="info-mini">
                    Mode ordonnancement : affichage des ND validées à ordonnancer.
                </div>
            <?php endif; ?>

            <form method="GET" class="filter-form">
                <input type="text" name="search" placeholder="Rechercher ND, NT, contribuable, téléphone"
                       value="<?= htmlspecialchars($search) ?>">

                <select name="statut">
                    <option value="">Tous statuts</option>
                    <option value="brouillon" <?= $statut=='brouillon'?'selected':'' ?>>Brouillon</option>
                    <option value="en_controle" <?= $statut=='en_controle'?'selected':'' ?>>En contrôle</option>
                    <option value="validee" <?= $statut=='validee'?'selected':'' ?>>Validée</option>
                    <option value="rejete" <?= $statut=='rejete'?'selected':'' ?>>Rejetée</option>
                </select>

                <button type="submit">Filtrer</button>
            </form>
        </div>

        <div class="panel cp-liquidation-panel cp-table-panel">
            <div class="cp-table-wrap">
            <table class="table-premium">
                <tr>
                    <th>Numéro ND</th>
                    <th>Réf NT</th>
                    <th>Contribuable</th>
                    <th>Total</th>
                    <th>Statut</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>

                <?php foreach($notes as $n): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($n['numero_nd']) ?></strong></td>
                        <td><?= htmlspecialchars($n['numero_nt']) ?></td>
                        <td><?= htmlspecialchars(nomContribuableNDList($n)) ?></td>
                        <td>
                            <?= number_format((float)($n['montant_total'] ?: ($n['total_exigible'] ?? 0)), 2, ',', ' ') ?> CDF
                        </td>
                        <td><?= badgeStatutND($n['statut']) ?></td>
                        <td><?= htmlspecialchars($n['created_at']) ?></td>
                        <td>
                            <a class="btn-action btn-view" href="nd_view.php?numero=<?= urlencode($n['numero_nd']) ?>">Voir</a>

                            <?php if ($isControleur && in_array($n['statut'], ['brouillon','en_controle'], true)): ?>
                                <a class="btn-action btn-control" href="nd_controle.php?numero=<?= urlencode($n['numero_nd']) ?>">Contrôler</a>
                            <?php endif; ?>

                            <?php if ($isOrdonnateur && $n['statut'] === 'validee'): ?>
                                <a class="btn-action btn-ordo" href="../ordonnancement/np_create.php?numero_nd=<?= urlencode($n['numero_nd']) ?>">Ordonnancer</a>
                            <?php endif; ?>

                            <?php if (function_exists('canDo') && canDo('liquidation','print_nd')): ?>
                                <a class="btn-action btn-print" href="../rapports/nd_pdf.php?numero=<?= urlencode($n['numero_nd']) ?>" target="_blank">PDF</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if(empty($notes)): ?>
                    <tr>
                        <td colspan="7">Aucune Note de Débit trouvée.</td>
                    </tr>
                <?php endif; ?>
            </table>
            </div>
        </div>

    </main>
</div>

</body>
</html>
