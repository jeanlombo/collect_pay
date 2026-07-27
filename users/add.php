<?php
require_once "../auth/check_auth.php";
require_once "../auth/permissions.php";

requirePermission('users', 'add');

$db = cpDb();
$error = null;

function cpTableExists(PDO $db, string $table): bool
{
    try {
        $stmt = $db->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        return (bool)$stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
}

function cpColumns(PDO $db, string $table): array
{
    try {
        $stmt = $db->query("SHOW COLUMNS FROM `$table`");
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
    } catch (Exception $e) {
        return [];
    }
}

function cpFetchOptions(PDO $db, string $table, array $labelCandidates): array
{
    if (!cpTableExists($db, $table)) {
        return [];
    }

    $cols = cpColumns($db, $table);

    $labelCol = null;
    foreach ($labelCandidates as $c) {
        if (in_array($c, $cols, true)) {
            $labelCol = $c;
            break;
        }
    }

    if (!$labelCol) {
        $labelCol = 'id';
    }

    $where = "";
    if (in_array('actif', $cols, true)) {
        $where = " WHERE actif = 1 ";
    } elseif (in_array('statut', $cols, true)) {
        $where = " WHERE COALESCE(statut,'actif')='actif' ";
    }

    $sql = "SELECT * FROM `$table` $where ORDER BY `$labelCol` ASC";
    return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

function cpOptionLabel(array $row, array $candidates, string $fallbackPrefix): string
{
    foreach ($candidates as $c) {
        if (isset($row[$c]) && trim((string)$row[$c]) !== '') {
            return (string)$row[$c];
        }
    }

    return $fallbackPrefix . ' #' . ($row['id'] ?? '');
}

$roles = $db->query("
    SELECT id, nom_role
    FROM roles
    WHERE COALESCE(statut,'actif')='actif'
    ORDER BY nom_role ASC
")->fetchAll(PDO::FETCH_ASSOC);

$provinces = cpFetchOptions($db, 'provinces', ['nom_province', 'nom', 'libelle', 'province']);
$centres   = cpFetchOptions($db, 'centres', ['nom_centre', 'nom', 'libelle', 'centre']);
$services  = cpFetchOptions($db, 'services_assiette', ['nom_service', 'nom', 'libelle', 'service']);
if (!$services) {
    $services = cpFetchOptions($db, 'services', ['nom_service', 'nom', 'libelle', 'service']);
}

$directions = cpFetchOptions($db, 'directions', ['nom_direction', 'nom', 'libelle', 'direction']);

$old = [
    'nom' => '',
    'email' => '',
    'telephone' => '',
    'role_id' => '',
    'province_id' => '',
    'centre_id' => '',
    'direction_id' => '',
    'service_id' => '',
    'statut' => 'actif',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $role_id = (int)($_POST['role_id'] ?? 0);
    $province_id = !empty($_POST['province_id']) ? (int)$_POST['province_id'] : null;
    $centre_id = !empty($_POST['centre_id']) ? (int)$_POST['centre_id'] : null;
    $direction_id = !empty($_POST['direction_id']) ? (int)$_POST['direction_id'] : null;
    $service_id = !empty($_POST['service_id']) ? (int)$_POST['service_id'] : null;
    $statut = $_POST['statut'] ?? 'actif';
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    $old = [
        'nom' => $nom,
        'email' => $email,
        'telephone' => $telephone,
        'role_id' => $role_id,
        'province_id' => $province_id,
        'centre_id' => $centre_id,
        'direction_id' => $direction_id,
        'service_id' => $service_id,
        'statut' => $statut,
    ];

    if ($nom === '' || $email === '' || $role_id <= 0 || $password === '') {
        $error = "Veuillez remplir les champs obligatoires.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Adresse email invalide.";
    } elseif ($password !== $password2) {
        $error = "Les mots de passe ne correspondent pas.";
    } elseif (strlen($password) < 6) {
        $error = "Le mot de passe doit contenir au moins 6 caractères.";
    } else {
        try {
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);

            if ($stmt->fetch()) {
                throw new Exception("Cette adresse email existe déjà.");
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $photoName = null;

            if (!empty($_FILES['photo']['name'])) {
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));

                if (!in_array($ext, $allowed, true)) {
                    throw new Exception("Photo invalide. Formats acceptés : JPG, PNG, WEBP.");
                }

                $dir = __DIR__ . "/../uploads/users/";
                if (!is_dir($dir)) {
                    mkdir($dir, 0777, true);
                }

                $photoName = "user_" . time() . "_" . rand(1000,9999) . "." . $ext;
                move_uploaded_file($_FILES['photo']['tmp_name'], $dir . $photoName);
            }

            $userCols = cpColumns($db, 'users');

            $data = [
                'nom' => $nom,
                'email' => $email,
                'telephone' => $telephone,
                'password' => $hash,
                'role_id' => $role_id,
                'province_id' => $province_id,
                'centre_id' => $centre_id,
                'service_id' => $service_id,
                'photo' => $photoName,
                'statut' => $statut,
            ];

            if (in_array('direction_id', $userCols, true)) {
                $data['direction_id'] = $direction_id;
            }

            if (in_array('actif', $userCols, true)) {
                $data['actif'] = ($statut === 'actif') ? 1 : 0;
            }

            $cols = [];
            $vals = [];

            foreach ($data as $col => $val) {
                if (in_array($col, $userCols, true)) {
                    $cols[] = $col;
                    $vals[] = $val;
                }
            }

            $sql = "INSERT INTO users (`" . implode("`,`", $cols) . "`) VALUES (" . implode(",", array_fill(0, count($cols), "?")) . ")";
            $stmt = $db->prepare($sql);

            if ($stmt->execute($vals)) {
                logAction('users', 'add', "Création utilisateur : " . $nom);
                header("Location: index.php?created=1");
                exit;
            }

            $error = "Erreur lors de l'enregistrement.";

        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

function selected($a, $b): string
{
    return (string)$a === (string)$b ? 'selected' : '';
}

$page_title = "Nouvel utilisateur";
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Nouvel utilisateur | cOllect_Pay</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="../assets/css/admin.css">

<style>
.users-page{
    max-width:1180px;
    margin:0 auto;
}
.user-header{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:16px;
    margin-bottom:18px;
}
.user-header h2{
    margin:0;
    color:#06152b;
    font-size:26px;
    font-weight:1000;
}
.user-header p{
    margin:5px 0 0;
    color:#64748b;
    font-weight:750;
}
.premium-card{
    background:white;
    border-radius:26px;
    padding:24px;
    box-shadow:0 18px 45px rgba(15,23,42,.09);
    border:1px solid #e5e7eb;
}
.section-title{
    margin:24px 0 14px;
    color:#0f3460;
    font-size:15px;
    font-weight:1000;
    text-transform:uppercase;
    letter-spacing:.8px;
    display:flex;
    align-items:center;
    gap:8px;
}
.section-title:first-child{
    margin-top:0;
}
.form-grid{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:16px;
}
.form-grid-3{
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:16px;
}
.form-group label{
    font-weight:950;
    color:#111827;
    margin-bottom:7px;
    display:block;
}
.form-group input,
.form-group select{
    width:100%;
    padding:13px 14px;
    border:1px solid #d1d5db;
    border-radius:15px;
    font-weight:750;
    background:#fff;
    outline:none;
}
.form-group input:focus,
.form-group select:focus{
    border-color:#0f3460;
    box-shadow:0 0 0 4px rgba(15,52,96,.10);
}
.hint{
    display:block;
    margin-top:5px;
    color:#64748b;
    font-size:12px;
    font-weight:700;
}
.actions{
    display:flex;
    gap:12px;
    justify-content:flex-end;
    flex-wrap:wrap;
    margin-top:24px;
    padding-top:18px;
    border-top:1px solid #e5e7eb;
}
.btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    padding:12px 18px;
    border-radius:15px;
    text-decoration:none;
    font-weight:1000;
    border:none;
    cursor:pointer;
}
.btn-primary{
    background:linear-gradient(135deg,#0f3460,#06152b);
    color:white;
}
.btn-gray{
    background:#e5e7eb;
    color:#111827;
}
.error{
    background:#fee2e2;
    color:#991b1b;
    padding:13px 15px;
    border-radius:16px;
    font-weight:900;
    margin-bottom:16px;
    border:1px solid #fecaca;
}
.quick-box{
    background:#eff6ff;
    color:#1e3a8a;
    padding:14px;
    border-radius:18px;
    font-weight:800;
    margin-bottom:18px;
}
.photo-box{
    border:2px dashed #cbd5e1;
    border-radius:18px;
    padding:16px;
    background:#f8fafc;
}
@media(max-width:900px){
    .form-grid,
    .form-grid-3{
        grid-template-columns:1fr;
    }
    .user-header{
        flex-direction:column;
        align-items:flex-start;
    }
}
</style>
</head>

<body>
<?php require_once "../includes/sidebar.php"; ?>

<main class="main-content">
    <?php
    $topbar = __DIR__ . "/../includes/topbar.php";
    if (file_exists($topbar)) {
        require_once $topbar;
    }
    ?>

    <div class="users-page">

        <div class="user-header">
            <div>
                <h2>Nouvel utilisateur</h2>
                <p>Création d’un compte agent avec rôle, affectation et sécurité.</p>
            </div>

            <a class="btn btn-gray" href="index.php">← Retour utilisateurs</a>
        </div>

        <div class="premium-card">

            <?php if($error): ?>
                <div class="error">❌ <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="quick-box">
                Les champs marqués par * sont obligatoires. Les affectations sont sélectionnées par nom, plus besoin de saisir les IDs.
            </div>

            <form method="post" enctype="multipart/form-data">

                <div class="section-title">👤 Informations personnelles</div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Nom complet *</label>
                        <input name="nom" value="<?= htmlspecialchars($old['nom']) ?>" placeholder="Ex : Jean LOMBO LOFUMA" required>
                    </div>

                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($old['email']) ?>" placeholder="Ex : agent@collectpay.cd" required>
                    </div>

                    <div class="form-group">
                        <label>Téléphone</label>
                        <input name="telephone" value="<?= htmlspecialchars($old['telephone']) ?>" placeholder="Ex : +243 820 646 942">
                    </div>

                    <div class="form-group">
                        <label>Photo</label>
                        <div class="photo-box">
                            <input type="file" name="photo" accept="image/*">
                            <span class="hint">Formats acceptés : JPG, PNG, WEBP.</span>
                        </div>
                    </div>
                </div>

                <div class="section-title">🏛️ Rôle et affectation</div>

                <div class="form-grid-3">
                    <div class="form-group">
                        <label>Rôle *</label>
                        <select name="role_id" required>
                            <option value="">-- Choisir un rôle --</option>
                            <?php foreach($roles as $r): ?>
                                <option value="<?= (int)$r['id'] ?>" <?= selected($old['role_id'], $r['id']) ?>>
                                    <?= htmlspecialchars($r['nom_role']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Province</label>
                        <select name="province_id" id="province_id">
                            <option value="">-- Toutes / Non précisée --</option>
                            <?php foreach($provinces as $p): ?>
                                <option value="<?= (int)$p['id'] ?>" <?= selected($old['province_id'], $p['id']) ?>>
                                    <?= htmlspecialchars(cpOptionLabel($p, ['nom_province','nom','libelle','province'], 'Province')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Centre</label>
                        <select name="centre_id" id="centre_id">
                            <option value="">-- Tous / Non précisé --</option>
                            <?php foreach($centres as $c): ?>
                                <option value="<?= (int)$c['id'] ?>"
                                        data-province="<?= htmlspecialchars($c['province_id'] ?? '') ?>"
                                        <?= selected($old['centre_id'], $c['id']) ?>>
                                    <?= htmlspecialchars(cpOptionLabel($c, ['nom_centre','nom','libelle','centre'], 'Centre')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Direction</label>
                        <select name="direction_id" id="direction_id">
                            <option value="">-- Non précisée --</option>
                            <?php foreach($directions as $d): ?>
                                <option value="<?= (int)$d['id'] ?>" <?= selected($old['direction_id'], $d['id']) ?>>
                                    <?= htmlspecialchars(cpOptionLabel($d, ['nom_direction','nom','libelle','direction'], 'Direction')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="hint">Ce champ sera utilisé si ta table users possède direction_id.</span>
                    </div>

                    <div class="form-group">
                        <label>Service</label>
                        <select name="service_id" id="service_id">
                            <option value="">-- Non précisé --</option>
                            <?php foreach($services as $s): ?>
                                <option value="<?= (int)$s['id'] ?>"
                                        data-direction="<?= htmlspecialchars($s['direction_id'] ?? '') ?>"
                                        <?= selected($old['service_id'], $s['id']) ?>>
                                    <?= htmlspecialchars(cpOptionLabel($s, ['nom_service','nom','libelle','service'], 'Service')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Statut</label>
                        <select name="statut">
                            <option value="actif" <?= selected($old['statut'], 'actif') ?>>Actif</option>
                            <option value="inactif" <?= selected($old['statut'], 'inactif') ?>>Inactif</option>
                        </select>
                    </div>
                </div>

                <div class="section-title">🔐 Sécurité</div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Mot de passe *</label>
                        <input type="password" name="password" placeholder="Minimum 6 caractères" required>
                    </div>

                    <div class="form-group">
                        <label>Confirmer mot de passe *</label>
                        <input type="password" name="password2" placeholder="Confirmer le mot de passe" required>
                    </div>
                </div>

                <div class="actions">
                    <a class="btn btn-gray" href="index.php">Annuler</a>
                    <button class="btn btn-primary" type="submit">💾 Enregistrer l’utilisateur</button>
                </div>

            </form>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const province = document.getElementById('province_id');
    const centre = document.getElementById('centre_id');
    const direction = document.getElementById('direction_id');
    const service = document.getElementById('service_id');

    function filterCentres() {
        if (!province || !centre) return;

        const pid = province.value;

        Array.from(centre.options).forEach(opt => {
            if (!opt.value) {
                opt.hidden = false;
                return;
            }

            const opid = opt.dataset.province || '';
            opt.hidden = pid && opid && opid !== pid;
        });

        if (centre.selectedOptions[0] && centre.selectedOptions[0].hidden) {
            centre.value = '';
        }
    }

    function filterServices() {
        if (!direction || !service) return;

        const did = direction.value;

        Array.from(service.options).forEach(opt => {
            if (!opt.value) {
                opt.hidden = false;
                return;
            }

            const odid = opt.dataset.direction || '';
            opt.hidden = did && odid && odid !== did;
        });

        if (service.selectedOptions[0] && service.selectedOptions[0].hidden) {
            service.value = '';
        }
    }

    if (province) {
        province.addEventListener('change', filterCentres);
        filterCentres();
    }

    if (direction) {
        direction.addEventListener('change', filterServices);
        filterServices();
    }
});
</script>

</body>
</html>
