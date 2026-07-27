<?php
function calculerPenaliteProgressive($montant_base, $jours_retard, $type, $pdo)
{
    if ($jours_retard <= 0) {
        return 0;
    }

    $stmt = $pdo->prepare("
        SELECT taux_pourcentage
        FROM parametres_penalites_progressives
        WHERE type = ?
        AND actif = 1
        AND ? BETWEEN tranche_debut AND tranche_fin
        LIMIT 1
    ");

    $stmt->execute([$type, $jours_retard]);
    $param = $stmt->fetch();

    if (!$param) {
        return 0;
    }

    return $montant_base * ($param['taux_pourcentage'] / 100);
}

function proposerPenalite($type, $reference_type, $reference_id,
                           $montant_base, $taux,
                           $montant_penalite, $jours, $pdo)
{
    $stmt = $pdo->prepare("
        INSERT INTO penalites_historique
        (type, reference_type, reference_id,
         montant_base, taux_applique,
         montant_penalite, jours_retard,
         date_application, statut)
        VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), 'proposee')
    ");

    $stmt->execute([
        $type,
        $reference_type,
        $reference_id,
        $montant_base,
        $taux,
        $montant_penalite,
        $jours
    ]);
}
?>