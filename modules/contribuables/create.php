<?php
require_once "../../config/database.php";
require_once "../../config/security.php";
require_once "../../core/functions.php";

checkAuth();

$page_title = "Nouveau contribuable";
$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $type_personne = $_POST['type_personne'] ?? 'PHYSIQUE';

    $raison_sociale = trim($_POST['raison_sociale'] ?? '');
    $nom = trim($_POST['nom'] ?? '');
    $postnom = trim($_POST['postnom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');

    $nif = trim($_POST['nif'] ?? '');
    $rccm = trim($_POST['rccm'] ?? '');
    $id_national = trim($_POST['id_national'] ?? '');

    $telephone = trim($_POST['telephone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $ville = trim($_POST['ville'] ?? '');
    $adresse = trim($_POST['adresse'] ?? '');

    if ($type_personne === 'PHYSIQUE') {
        $raison_sociale = null;
        $rccm = null;
    } else {
        $nom = null;
        $postnom = null;
        $prenom = null;
        $id_national = null;
    }

    $code_contribuable = 'CTR-' . date('ymdHis');

    $stmt = $pdo->prepare("
        INSERT INTO contribuables
        (
            code_contribuable,
            type_personne,
            raison_sociale,
            nom,
            postnom,
            prenom,
            nif,
            rccm,
            id_national,
            telephone,
            email,
            ville,
            adresse,
            created_at
        )
        VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $stmt->execute([
        $code_contribuable,
        $type_personne,
        $raison_sociale,
        $nom,
        $postnom,
        $prenom,
        $nif,
        $rccm,
        $id_national,
        $telephone,
        $email,
        $ville,
        $adresse
    ]);

    header("Location: list.php");
    exit;
}
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

        .field-group {
            margin-bottom: 14px;
        }

        label {
            display: block;
            font-weight: 900;
            color: #0f3460;
            margin-bottom: 6px;
        }

        .hidden {
            display: none;
        }

        .info-box {
            background: #eff6ff;
            color: #1e3a8a;
            border: 1px solid #bfdbfe;
            padding: 14px;
            border-radius: 14px;
            font-weight: 800;
            margin-bottom: 18px;
        }
    </style>
</head>

<body>

<div class="admin-layout">

    <?php require_once "../../includes/sidebar.php"; ?>

    <main class="main-content">

        <?php require_once "../../includes/topbar.php"; ?>

        <div class="panel">
            <h3>Créer un contribuable</h3>

            <div class="info-box">
                Les champs changent automatiquement selon le type de personne choisi.
            </div>

            <form method="POST">

                <div class="field-group">
                    <label>Type de personne</label>
                    <select name="type_personne" id="type_personne" required>
                        <option value="PHYSIQUE">Personne physique</option>
                        <option value="MORALE">Personne morale</option>
                        <option value="ETABLISSEMENT">Établissement</option>
                        <option value="ONG">ONG</option>
                        <option value="ONGD">ONGD</option>
                    </select>
                </div>

                <div id="bloc_physique">
                    <div class="grid-2">
                        <div class="field-group">
                            <label>Nom</label>
                            <input type="text" name="nom" placeholder="Nom">
                        </div>

                        <div class="field-group">
                            <label>Post-nom</label>
                            <input type="text" name="postnom" placeholder="Post-nom">
                        </div>
                    </div>

                    <div class="grid-2">
                        <div class="field-group">
                            <label>Prénom</label>
                            <input type="text" name="prenom" placeholder="Prénom">
                        </div>

                        <div class="field-group">
                            <label>ID national</label>
                            <input type="text" name="id_national" placeholder="ID national">
                        </div>
                    </div>
                </div>

                <div id="bloc_morale" class="hidden">
                    <div class="grid-2">
                        <div class="field-group">
                            <label>Raison sociale</label>
                            <input type="text" name="raison_sociale" placeholder="Raison sociale">
                        </div>

                        <div class="field-group">
                            <label>RCCM ou Patente</label>
                            <input type="text" name="rccm" placeholder="RCCM ou Patente">
                        </div>
                    </div>
                </div>

                <div class="grid-2">
                    <div class="field-group">
                        <label>NIF</label>
                        <input type="text" name="nif" placeholder="Numéro d’identification fiscale">
                    </div>

                    <div class="field-group">
                        <label>Téléphone</label>
                        <input type="text" name="telephone" placeholder="Téléphone">
                    </div>
                </div>

                <div class="grid-2">
                    <div class="field-group">
                        <label>Email</label>
                        <input type="email" name="email" placeholder="Email">
                    </div>

                    <div class="field-group">
                        <label>Ville</label>
                        <input type="text" name="ville" placeholder="Ville">
                    </div>
                </div>

                <div class="field-group">
                    <label>Adresse complète</label>
                    <textarea name="adresse" placeholder="Province, ville, commune, quartier, avenue, numéro"></textarea>
                </div>

                <button type="submit">Enregistrer le contribuable</button>

            </form>
        </div>

    </main>
</div>

<script>
function toggleTypePersonne() {
    const type = document.getElementById('type_personne').value;
    const blocPhysique = document.getElementById('bloc_physique');
    const blocMorale = document.getElementById('bloc_morale');

    if (type === 'PHYSIQUE') {
        blocPhysique.classList.remove('hidden');
        blocMorale.classList.add('hidden');
    } else {
        blocPhysique.classList.add('hidden');
        blocMorale.classList.remove('hidden');
    }
}

document.getElementById('type_personne').addEventListener('change', toggleTypePersonne);
toggleTypePersonne();
</script>

</body>
</html>