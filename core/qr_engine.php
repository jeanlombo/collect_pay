<?php
require_once(__DIR__ . "/../config/constants.php");

function genererQRData($type, $numero, $montant)
{
    $hash = hash('sha256', $numero . $montant . CLE_QR);

    return json_encode([
        "doc" => $type,
        "numero" => $numero,
        "montant" => $montant,
        "hash" => $hash
    ]);
}

function verifierQRData($numero, $montant, $hash)
{
    $hash_calcule = hash('sha256', $numero . $montant . CLE_QR);

    return $hash_calcule === $hash;
}
?>