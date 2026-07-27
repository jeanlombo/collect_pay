<?php
require_once "../../auth/check_auth.php";

checkAuth();
requirePermission('apurement', 'create');

header("Location: /cOllect_pay/modules/ordonnancement/np_list.php?statut=payee&mode=apurement");
exit;