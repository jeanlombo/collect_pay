<?php
require_once "../../config/database.php";
require_once "../../config/security.php";
require_once "../../core/functions.php";

checkAuth();

$id = $_GET['id'] ?? null;

if (!$id) {
    die("Contribuable introuvable.");
}

$stmt = $pdo->prepare("SELECT * FROM contribuables WHERE id = ?");
$stmt->execute([$id]);
$contribuable = $stmt->fetch();

if (!$contribuable) {
    die("Contribuable introuvable.");
}

$page_title = "Modifier Contribuable";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $type_personne = $_POST['type_personne'];
    $raison_sociale = trim($_POST['raison_sociale']);
    $nom = trim($_POST['nom']);
    $postnom = trim($_POST['postnom']);
    $prenom = trim($_POST['prenom']);
    $nif = trim($_POST['nif']);
    $rccm = trim($_POST['rccm']);
    $id_national = trim($_POST['id_national']);
    $telephone = trim($_POST['telephone']);
    $telephone_secondaire = trim($_POST['telephone_secondaire']);
    $email = trim($_POST['email']);
    $adresse = trim($_POST['adresse']);
    $ville = trim($_POST['ville']);
    $commune = trim($_POST['commune']);
    $quartier = trim($_POST['quartier']);
    $avenue = trim($_POST['avenue']);
    $numero_parcelle = trim($_POST['numero_parcelle']);
    $latitude = $_POST['latitude'] ?: null;
    $longitude = $_POST['longitude'] ?: null;
    $statut = $_POST['statut'];

    $photoName = $contribuable['photo'];

    if (!empty($_FILES['photo']['name'])) {
        $uploadDir = "../../assets/uploads/contribuables/";

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $photoName = "CTR_" . time() . "." . $ext;

        move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $photoName);
    }

    $stmt = $pdo->prepare("
        UPDATE contribuables
        SET
            type_personne = ?,
            raison_sociale = ?,
            nom = ?,
            postnom = ?,
            prenom = ?,
            nif = ?,
            rccm = ?,
            id_national = ?,
            telephone = ?,
            telephone_secondaire = ?,
            email = ?,
            adresse = ?,
            ville = ?,
            commune = ?,
            quartier = ?,
            avenue = ?,
            numero_parcelle = ?,
            latitude = ?,
            longitude = ?,
            photo = ?,
            statut = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $type_personne,
        $raison_sociale,
        $nom,
        $postnom,
        $prenom,
        $nif,
        $rccm,
        $id_national,
        $telephone,
        $telephone_secondaire,
        $email,
        $adresse,
        $ville,
        $commune,
        $quartier,
        $avenue,
        $numero_parcelle,
        $latitude,
        $longitude,
        $photoName,
        $statut,
        $id
    ]);

    header("Location: view.php?id=" . $id);
    exit;
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
            <h3>Modifier le contribuable</h3>

            <form method="POST" enctype="multipart/form-data">

                <select name="type_personne" required>
                    <option value="physique" <?= $contribuable['type_personne']=='physique'?'selected':'' ?>>Personne Physique</option>
                    <option value="morale" <?= $contribuable['type_personne']=='morale'?'selected':'' ?>>Personne Morale</option>
                    <option value="etablissement" <?= $contribuable['type_personne']=='etablissement'?'selected':'' ?>>Établissement</option>
                    <option value="ong" <?= $contribuable['type_personne']=='ong'?'selected':'' ?>>ONG</option>
                    <option value="autres" <?= $contribuable['type_personne']=='autres'?'selected':'' ?>>Autres</option>
                </select>

                <input type="text" name="raison_sociale" placeholder="Raison sociale"
                       value="<?= htmlspecialchars($contribuable['raison_sociale'] ?? '') ?>">

                <input type="text" name="nom" placeholder="Nom"
                       value="<?= htmlspecialchars($contribuable['nom'] ?? '') ?>">

                <input type="text" name="postnom" placeholder="Postnom"
                       value="<?= htmlspecialchars($contribuable['postnom'] ?? '') ?>">

                <input type="text" name="prenom" placeholder="Prénom"
                       value="<?= htmlspecialchars($contribuable['prenom'] ?? '') ?>">

                <input type="text" name="nif" placeholder="NIF"
                       value="<?= htmlspecialchars($contribuable['nif'] ?? '') ?>">

                <input type="text" name="rccm" placeholder="RCCM / Patente"
                       value="<?= htmlspecialchars($contribuable['rccm'] ?? '') ?>">

                <input type="text" name="id_national" placeholder="ID National"
                       value="<?= htmlspecialchars($contribuable['id_national'] ?? '') ?>">

                <input type="text" name="telephone" placeholder="Téléphone principal"
                       value="<?= htmlspecialchars($contribuable['telephone'] ?? '') ?>" required>

                <input type="text" name="telephone_secondaire" placeholder="Téléphone secondaire"
                       value="<?= htmlspecialchars($contribuable['telephone_secondaire'] ?? '') ?>">

                <input type="email" name="email" placeholder="Email"
                       value="<?= htmlspecialchars($contribuable['email'] ?? '') ?>">

                <input type="text" name="adresse" placeholder="Adresse"
                       value="<?= htmlspecialchars($contribuable['adresse'] ?? '') ?>">

                <input type="text" name="ville" placeholder="Ville"
                       value="<?= htmlspecialchars($contribuable['ville'] ?? '') ?>">

                <input type="text" name="commune" placeholder="Commune"
                       value="<?= htmlspecialchars($contribuable['commune'] ?? '') ?>">

                <input type="text" name="quartier" placeholder="Quartier"
                       value="<?= htmlspecialchars($contribuable['quartier'] ?? '') ?>">

                <input type="text" name="avenue" placeholder="Avenue"
                       value="<?= htmlspecialchars($contribuable['avenue'] ?? '') ?>">

                <input type="text" name="numero_parcelle" placeholder="N° Parcelle"
                       value="<?= htmlspecialchars($contribuable['numero_parcelle'] ?? '') ?>">

                <input type="text" name="latitude" placeholder="Latitude"
                       value="<?= htmlspecialchars($contribuable['latitude'] ?? '') ?>">

                <input type="text" name="longitude" placeholder="Longitude"
                       value="<?= htmlspecialchars($contribuable['longitude'] ?? '') ?>">

                <select name="statut" required>
                    <option value="actif" <?= $contribuable['statut']=='actif'?'selected':'' ?>>Actif</option>
                    <option value="suspendu" <?= $contribuable['statut']=='suspendu'?'selected':'' ?>>Suspendu</option>
                    <option value="radie" <?= $contribuable['statut']=='radie'?'selected':'' ?>>Radié</option>
                    <option value="decede" <?= $contribuable['statut']=='decede'?'selected':'' ?>>Décédé</option>
                    <option value="contentieux" <?= $contribuable['statut']=='contentieux'?'selected':'' ?>>Contentieux</option>
                </select>

                <label>Changer photo</label>
                <input type="file" name="photo" accept="image/*">

                <button type="submit">Mettre à jour</button>
            </form>
        </div>

    </main>
</div>

</body>
</html>