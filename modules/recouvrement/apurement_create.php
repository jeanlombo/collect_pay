<?php
require_once "../../auth/check_auth.php";

checkAuth();
requirePermission('apurement', 'create');

/*
|--------------------------------------------------------------------------
| Point d'entrée Apurement
|--------------------------------------------------------------------------
| On renvoie vers la liste des NP/NPF payées pour sélectionner la note
| à apurer. Chemin relatif compatible local + Railway.
*/
header("Location: ../ordonnancement/np_list.php?statut=payee&mode=apurement");
exit;
