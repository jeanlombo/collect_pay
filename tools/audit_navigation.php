<?php
/*
|--------------------------------------------------------------------------
| cOllect_Pay - Audit des chemins de navigation
|--------------------------------------------------------------------------
| À placer dans : collect_pay/tools/audit_navigation.php
| Puis ouvrir : http://localhost/collect_pay/tools/audit_navigation.php
|--------------------------------------------------------------------------
*/

$baseDir = realpath(__DIR__ . "/..");
$baseUrl = "/collect_pay";

$modules = [
    "Dashboard" => [
        "dashboard/index.php",
        "index.php"
    ],

    "Contribuables" => [
        "contribuables/index.php",
        "assujettis/index.php",
        "taxpayers/index.php"
    ],

    "Notes de taxation / Constatation" => [
        "notes_taxation/index.php",
        "constatation/index.php",
        "taxation/index.php",
        "nt/index.php",
        "nt_list.php"
    ],

    "Liquidation / Notes de débit" => [
        "liquidation/index.php",
        "notes_debit/index.php",
        "nd/index.php",
        "nd_list.php"
    ],

    "Ordonnancement / Notes de perception" => [
        "ordonnancement/index.php",
        "notes_perception/index.php",
        "np/index.php",
        "np_list.php"
    ],

    "Recouvrement / Paiements" => [
        "recouvrement/index.php",
        "paiements/index.php",
        "caisse/index.php"
    ],

    "Quittances" => [
        "quittances/index.php",
        "quittance/index.php"
    ],

    "Rapports" => [
        "rapports/index.php",
        "reports/index.php",
        "rapport/index.php"
    ],

    "Paramétrage" => [
        "parametres/index.php",
        "parametrage/index.php",
        "settings/index.php",
        "configurations/index.php"
    ],

    "Utilisateurs" => [
        "users/index.php"
    ],

    "Rôles & Permissions" => [
        "roles/index.php"
    ],

    "Déconnexion" => [
        "logout.php",
        "auth/logout.php"
    ]
];

function scanPhpFiles($dir)
{
    $files = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === "php") {
            $files[] = str_replace("\\", "/", $file->getPathname());
        }
    }

    sort($files);

    return $files;
}

$allPhpFiles = scanPhpFiles($baseDir);

?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Audit navigation cOllect_Pay</title>
<style>
body{
    margin:0;
    background:#f8fafc;
    font-family:Arial,sans-serif;
    color:#0f172a;
}
.header{
    background:linear-gradient(135deg,#06152b,#0f3460);
    color:white;
    padding:28px;
}
.header h1{
    margin:0;
}
.container{
    padding:25px;
}
.card{
    background:white;
    border-radius:18px;
    padding:20px;
    box-shadow:0 8px 25px rgba(0,0,0,.08);
    margin-bottom:20px;
}
table{
    width:100%;
    border-collapse:collapse;
}
th,td{
    padding:12px;
    border-bottom:1px solid #e5e7eb;
    text-align:left;
    vertical-align:top;
}
th{
    background:#f8fafc;
    color:#0f3460;
}
.ok{
    color:#166534;
    font-weight:900;
}
.bad{
    color:#991b1b;
    font-weight:900;
}
.code{
    background:#f1f5f9;
    padding:8px 10px;
    border-radius:10px;
    font-family:Consolas,monospace;
    display:inline-block;
}
.small{
    color:#64748b;
    font-size:13px;
}
a{
    color:#0f3460;
    font-weight:800;
}
</style>
</head>
<body>

<div class="header">
    <h1>Audit navigation cOllect_Pay</h1>
    <p>Détection automatique des chemins existants pour la sidebar.</p>
</div>

<div class="container">

    <div class="card">
        <h2>Résultat par module</h2>

        <table>
            <thead>
                <tr>
                    <th>Module</th>
                    <th>Statut</th>
                    <th>Chemin trouvé</th>
                    <th>URL</th>
                    <th>Chemins testés</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($modules as $module => $candidates): ?>
                <?php
                $found = null;

                foreach($candidates as $candidate){
                    $path = $baseDir . "/" . $candidate;

                    if(file_exists($path)){
                        $found = $candidate;
                        break;
                    }
                }
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($module) ?></strong></td>

                    <td>
                        <?php if($found): ?>
                            <span class="ok">Relié</span>
                        <?php else: ?>
                            <span class="bad">Non relié</span>
                        <?php endif; ?>
                    </td>

                    <td>
                        <?php if($found): ?>
                            <span class="code"><?= htmlspecialchars($found) ?></span>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>

                    <td>
                        <?php if($found): ?>
                            <a href="<?= htmlspecialchars($baseUrl . "/" . $found) ?>" target="_blank">
                                <?= htmlspecialchars($baseUrl . "/" . $found) ?>
                            </a>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>

                    <td>
                        <?php foreach($candidates as $candidate): ?>
                            <div class="small"><?= htmlspecialchars($candidate) ?></div>
                        <?php endforeach; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2>Tous les fichiers PHP détectés</h2>

        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Chemin relatif</th>
                    <th>URL possible</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($allPhpFiles as $i => $file): ?>
                <?php
                $relative = str_replace("\\", "/", str_replace($baseDir . DIRECTORY_SEPARATOR, "", $file));
                $relative = str_replace($baseDir . "/", "", $relative);
                ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><span class="code"><?= htmlspecialchars($relative) ?></span></td>
                    <td>
                        <a href="<?= htmlspecialchars($baseUrl . "/" . $relative) ?>" target="_blank">
                            <?= htmlspecialchars($baseUrl . "/" . $relative) ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>
