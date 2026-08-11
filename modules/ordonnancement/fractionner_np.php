<?php
require_once("../../config/database.php");
require_once("../../config/security.php");

checkAuth();
requireRole([
    'SUPER_ADMIN',
    'ORDONNATEUR'
]);

/*
|--------------------------------------------------------------------------
| Paramètres
|--------------------------------------------------------------------------
*/
$numero_np = trim((string)($_POST['numero_np'] ?? ''));
$nombre    = (int)($_POST['nombre'] ?? 0);

if ($numero_np === '') {
    die("Numéro NP manquant.");
}

if ($nombre <= 0) {
    die("Nombre de fractions invalide.");
}

/*
|--------------------------------------------------------------------------
| Note de Perception
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT *
    FROM notes_perception
    WHERE numero_np = ?
    LIMIT 1
");
$stmt->execute([$numero_np]);
$np = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$np) {
    die("Note de Perception introuvable.");
}

$montant = (float)($np['montant_total'] ?? 0);

if ($montant <= 0) {
    die("Le montant de la Note de Perception est invalide.");
}

$montant_fraction = round($montant / $nombre, 2);

/*
|--------------------------------------------------------------------------
| Fractionnement
|--------------------------------------------------------------------------
| La logique métier existante est conservée :
| - même calcul du montant par fraction
| - même numérotation
| - même règle d'échéance
| - même mise à jour de la NP mère
*/
try {

    $pdo->beginTransaction();

    for ($i = 1; $i <= $nombre; $i++) {

        $echeance = ($i == 1)
            ? date('Y-m-d')
            : date('Y-m-d', strtotime("+$i month"));

        $numero_fraction =
            $numero_np . "-" . str_pad(
                (string)$i,
                2,
                "0",
                STR_PAD_LEFT
            );

        $stmtFraction = $pdo->prepare("
            INSERT INTO notes_perception_fractions
            (
                numero_fraction,
                note_mere_id,
                montant_fraction,
                date_echeance
            )
            VALUES (?, ?, ?, ?)
        ");

        $stmtFraction->execute([
            $numero_fraction,
            (int)$np['id'],
            $montant_fraction,
            $echeance
        ]);
    }

    $stmtUpdate = $pdo->prepare("
        UPDATE notes_perception
        SET
            est_fractionnee = 1,
            statut_fractionnement = 'fractionnee'
        WHERE id = ?
    ");

    $stmtUpdate->execute([
        (int)$np['id']
    ]);

    $pdo->commit();

    /*
    |--------------------------------------------------------------------------
    | Redirection propre
    |--------------------------------------------------------------------------
    | Au lieu d'afficher uniquement "Fractionnement terminé",
    | on revient vers la liste des fractions.
    */
    header(
        "Location: fractions_list.php?numero_np=" .
        urlencode($numero_np) .
        "&fractionnement=ok"
    );
    exit;

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    die(
        "Erreur lors du fractionnement : " .
        $e->getMessage()
    );
}
