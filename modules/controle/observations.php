<?php
/*
|--------------------------------------------------------------------------
| cOllect_Pay - Observations Contrôle
|--------------------------------------------------------------------------
| Affiche les observations liées aux Notes de Débit :
| - ND en contrôle
| - ND rejetées
| - ND validées avec observation
|--------------------------------------------------------------------------
*/

require_once "../../auth/check_auth.php";
require_once "../../core/functions.php";

checkAuth();
requirePermission('controle', 'observe');

$page_title = "Observations du contrôle";

$search = trim($_GET['search'] ?? '');
$statut = trim($_GET['statut'] ?? '');

$sql = "
    SELECT
        nd.id,
        nd.numero_nd,
        nd.statut,
        nd.decision,
        nd.observation,
        nd.montant_total,
        nd.total_exigible,
        nd.date_validation,
        nd.created_at,
        nt.numero_nt,
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

if ($statut !== '') {
    $sql .= " AND nd.statut = ?";
    $params[] = $statut;
} else {
    $sql .= " AND nd.statut IN ('brouillon','en_controle','validee','rejete')";
}

if ($search !== '') {
    $sql .= "
        AND (
            nd.numero_nd LIKE ?
            OR nt.numero_nt LIKE ?
            OR c.raison_sociale LIKE ?
            OR c.nom LIKE ?
            OR c.postpostnom LIKE ?
            OR c.prenom LIKE ?
            OR c.telephone LIKE ?
            OR nd.observation LIKE ?
        )
    ";
}

if ($search !== '') {
    $like = "%$search%";
    $params = array_merge($params, [$like,$like,$like,$like,$like,$like,$like,$like]);
}

/*
|--------------------------------------------------------------------------
| Correction si la colonne postpostnom n'existe pas
|--------------------------------------------------------------------------
*/
$sql = str_replace("c.postpostnom", "c.postnom", $sql);

$sql .= "
    ORDER BY 
        CASE 
            WHEN nd.statut = 'en_controle' THEN 1
            WHEN nd.statut = 'brouillon' THEN 2
            WHEN nd.statut = 'rejete' THEN 3
            WHEN nd.statut = 'validee' THEN 4
            ELSE 5
        END,
        COALESCE(nd.date_validation, nd.created_at) DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

function nomContribuableControleObs(array $c): string
{
    if (!empty($c['raison_sociale'])) {
        return $c['raison_sociale'];
    }

    return trim(($c['nom'] ?? '') . ' ' . ($c['postnom'] ?? '') . ' ' . ($c['prenom'] ?? ''));
}

function badgeStatutControleObs(string $statut): string
{
    $statut = strtolower($statut);

    $map = [
        'brouillon' => ['Brouillon', '#f3f4f6', '#374151'],
        'en_controle' => ['En contrôle', '#dbeafe', '#1e40af'],
        'validee' => ['Validée', '#dcfce7', '#166534'],
        'rejete' => ['Rejetée', '#fee2e2', '#991b1b'],
    ];

    $b = $map[$statut] ?? [strtoupper($statut), '#f3f4f6', '#374151'];

    return '<span class="badge-status" style="background:' . $b[1] . ';color:' . $b[2] . '">' . htmlspecialchars($b[0]) . '</span>';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($page_title) ?> | cOllect_Pay</title>
    <link rel="stylesheet" href="/collect_pay/assets/css/admin.css">

    <style>
        .obs-header{
            background:linear-gradient(135deg,#06152b,#0f3460);
            color:#fff;
            border-radius:22px;
            padding:22px;
            margin-bottom:18px;
        }
        .obs-header h2{
            margin:0;
            font-weight:1000;
        }
        .obs-header p{
            margin:7px 0 0;
            color:#dbeafe;
            font-weight:700;
        }
        .filter-form{
            display:grid;
            grid-template-columns:2fr 1fr auto;
            gap:12px;
            margin-top:18px;
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
        .badge-status{
            display:inline-block;
            padding:6px 10px;
            border-radius:999px;
            font-weight:900;
            font-size:12px;
        }
        .obs-text{
            max-width:420px;
            white-space:normal;
            line-height:1.45;
            color:#334155;
            font-weight:700;
        }
        .obs-empty{
            color:#94a3b8;
            font-style:italic;
            font-weight:700;
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
        .btn-view{background:#e5e7eb;color:#111827;}
        .btn-control{background:#0f3460;color:#fff;}
        @media(max-width:900px){
            .filter-form{grid-template-columns:1fr;}
        }
    </style>
</head>

<body>
<div class="admin-layout">
    <?php require_once "../../includes/sidebar.php"; ?>

    <main class="main-content">
        <?php require_once "../../includes/topbar.php"; ?>

        <div class="obs-header">
            <h2>Observations du contrôle</h2>
            <p>Suivi des décisions, rejets, corrections et observations sur les Notes de Débit.</p>
        </div>

        <div class="panel">
            <form method="GET" class="filter-form">
                <input type="text" name="search"
                       placeholder="Rechercher ND, NT, contribuable, téléphone, observation..."
                       value="<?= htmlspecialchars($search) ?>">

                <select name="statut">
                    <option value="">Tous statuts</option>
                    <option value="brouillon" <?= $statut === 'brouillon' ? 'selected' : '' ?>>Brouillon</option>
                    <option value="en_controle" <?= $statut === 'en_controle' ? 'selected' : '' ?>>En contrôle</option>
                    <option value="validee" <?= $statut === 'validee' ? 'selected' : '' ?>>Validée</option>
                    <option value="rejete" <?= $statut === 'rejete' ? 'selected' : '' ?>>Rejetée</option>
                </select>

                <button type="submit">Filtrer</button>
            </form>
        </div>

        <div class="panel">
            <table class="table-premium">
                <tr>
                    <th>ND</th>
                    <th>NT</th>
                    <th>Contribuable</th>
                    <th>Montant</th>
                    <th>Statut</th>
                    <th>Décision</th>
                    <th>Observation</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>

                <?php foreach ($notes as $n): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($n['numero_nd']) ?></strong></td>
                        <td><?= htmlspecialchars($n['numero_nt']) ?></td>
                        <td><?= htmlspecialchars(nomContribuableControleObs($n)) ?></td>
                        <td><?= number_format((float)($n['montant_total'] ?: ($n['total_exigible'] ?? 0)), 2, ',', ' ') ?> CDF</td>
                        <td><?= badgeStatutControleObs($n['statut']) ?></td>
                        <td><?= htmlspecialchars($n['decision'] ?? '-') ?></td>
                        <td>
                            <?php if (!empty($n['observation'])): ?>
                                <div class="obs-text"><?= nl2br(htmlspecialchars($n['observation'])) ?></div>
                            <?php else: ?>
                                <span class="obs-empty">Aucune observation</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($n['date_validation'] ?: $n['created_at']) ?></td>
                        <td>
                            <a class="btn-action btn-view" href="../liquidation/nd_view.php?numero=<?= urlencode($n['numero_nd']) ?>">Voir</a>

                            <?php if (in_array($n['statut'], ['brouillon','en_controle'], true)): ?>
                                <a class="btn-action btn-control" href="../liquidation/nd_controle.php?numero=<?= urlencode($n['numero_nd']) ?>">Contrôler</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($notes)): ?>
                    <tr>
                        <td colspan="9">Aucune observation trouvée.</td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>

    </main>
</div>
</body>
</html>
