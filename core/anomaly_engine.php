<?php
function detecterBaisseRecettes($centre_id, $pdo)
{
    $stmt = $pdo->prepare("
        SELECT SUM(montant_paye) total
        FROM paiements p
        JOIN notes_perception np ON p.note_perception_id = np.id
        JOIN notes_debit nd ON np.note_debit_id = nd.id
        JOIN notes_taxation nt ON nd.note_taxation_id = nt.id
        WHERE nt.centre_id = ?
        AND MONTH(p.date_paiement) = MONTH(CURRENT_DATE - INTERVAL 1 MONTH)
    ");

    $stmt->execute([$centre_id]);
    $total_mois_precedent = $stmt->fetch()['total'] ?? 0;

    $stmt = $pdo->prepare("
        SELECT SUM(montant_paye) total
        FROM paiements p
        JOIN notes_perception np ON p.note_perception_id = np.id
        JOIN notes_debit nd ON np.note_debit_id = nd.id
        JOIN notes_taxation nt ON nd.note_taxation_id = nt.id
        WHERE nt.centre_id = ?
        AND MONTH(p.date_paiement) = MONTH(CURRENT_DATE)
    ");

    $stmt->execute([$centre_id]);
    $total_mois_actuel = $stmt->fetch()['total'] ?? 0;

    if ($total_mois_precedent > 0) {
        $variation = (($total_mois_actuel - $total_mois_precedent) / $total_mois_precedent) * 100;

        if ($variation < -50) {
            enregistrerAlerte("baisse_recettes", $centre_id, "Baisse supérieure à 50%", "critique", $pdo);
        }
    }
}

function enregistrerAlerte($type, $reference_id, $description, $niveau, $pdo)
{
    $stmt = $pdo->prepare("
        INSERT INTO alertes_systeme
        (type_alerte, reference_id, description, niveau)
        VALUES (?, ?, ?, ?)
    ");

    $stmt->execute([
        $type,
        $reference_id,
        $description,
        $niveau
    ]);
}
?>