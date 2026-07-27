<?php
require_once("../../config/database.php");
require_once("../../config/security.php");

checkAuth();
requireRole([
    'SUPER_ADMIN',
    'ORDONNATEUR'
]);

$numero_np = $_POST['numero_np'];
$nombre = $_POST['nombre'];

$stmt = $pdo->prepare("SELECT * FROM notes_perception WHERE numero_np=?");
$stmt->execute([$numero_np]);
$np = $stmt->fetch();

$montant = $np['montant_total'];
$montant_fraction = round($montant / $nombre, 2);

for ($i = 1; $i <= $nombre; $i++) {

    $echeance = ($i == 1)
        ? date('Y-m-d')
        : date('Y-m-d', strtotime("+$i month"));

    $numero_fraction = $numero_np . "-" . str_pad($i,2,"0",STR_PAD_LEFT);

    $pdo->prepare("
        INSERT INTO notes_perception_fractions
        (numero_fraction, note_mere_id, montant_fraction, date_echeance)
        VALUES (?, ?, ?, ?)
    ")->execute([
        $numero_fraction,
        $np['id'],
        $montant_fraction,
        $echeance
    ]);
}

$pdo->prepare("
    UPDATE notes_perception
    SET est_fractionnee=1, statut_fractionnement='fractionnee'
    WHERE id=?
")->execute([$np['id']]);

echo "Fractionnement terminé";
?>