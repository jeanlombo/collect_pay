<?php
require_once "_rapport_bootstrap.php";

$f = cpRapportFilters();
$c = cpRapportCatalogues($pdo);

[$where, $params] = cpRapportScopeWhere($f, "COALESCE(np.date_emission,np.created_at)");
$whereSql = $where ? "WHERE ".implode(" AND ", $where) : "";

$paySub = "
    SELECT note_perception_id,
           SUM(CASE WHEN statut <> 'annule' THEN montant_converti_cdf ELSE 0 END) total_paye
    FROM paiements
    WHERE note_perception_id IS NOT NULL
    GROUP BY note_perception_id
";

$sql = "
SELECT
    COUNT(DISTINCT np.id) AS nb_notes,
    SUM(COALESCE(NULLIF(np.montant_initial,0),np.montant_total,0)) AS total_du,
    SUM(COALESCE(pp.total_paye,0)) AS total_paye,
    SUM(GREATEST(COALESCE(NULLIF(np.montant_initial,0),np.montant_total,0)-COALESCE(pp.total_paye,0),0)) AS solde,
    SUM(np.statut='payee') AS nb_payees,
    SUM(np.statut='partiellement_payee') AS nb_partielles,
    SUM(np.statut IN ('non_payee','en_attente','defaillante')) AS nb_non_payees
FROM notes_perception np
JOIN notes_debit nd ON np.note_debit_id=nd.id
JOIN notes_taxation nt ON nd.note_taxation_id=nt.id
JOIN centres ce ON nt.centre_id=ce.id
JOIN provinces pr ON ce.province_id=pr.id
LEFT JOIN services_assiette s ON nt.service_id=s.id
LEFT JOIN directions d ON s.direction_id=d.id
LEFT JOIN ({$paySub}) pp ON pp.note_perception_id=np.id
{$whereSql}
";
$stmt=$pdo->prepare($sql); $stmt->execute($params);
$k=$stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$taux=((float)($k['total_du']??0)>0) ? ((float)($k['total_paye']??0)/(float)$k['total_du']*100) : 0;

cpRapportPageStart("Tableau de bord des rapports","Vue consolidée des émissions, paiements et soldes CollectPay.");
?>
<section class="rp-panel"><?php cpRapportFilterHtml($f,$c); ?></section>

<section class="rp-kpis">
    <article><small>Notes émises</small><strong><?= number_format((int)($k['nb_notes']??0),0,',',' ') ?></strong></article>
    <article><small>Total dû</small><strong><?= cpRapportMoney($k['total_du']??0) ?></strong></article>
    <article><small>Total payé</small><strong><?= cpRapportMoney($k['total_paye']??0) ?></strong></article>
    <article><small>Solde</small><strong><?= cpRapportMoney($k['solde']??0) ?></strong></article>
    <article><small>Taux recouvrement</small><strong><?= number_format($taux,1,',',' ') ?> %</strong></article>
    <article><small>Payées / Partielles</small><strong><?= (int)($k['nb_payees']??0) ?> / <?= (int)($k['nb_partielles']??0) ?></strong></article>
</section>

<section class="rp-panel">
    <div class="rp-panel-head"><h2>Rapports disponibles</h2><span>11 analyses métier</span></div>
    <div class="rp-report-grid">
        <a href="rapport_mensuel.php"><b>Rapport mensuel</b><span>Émissions, paiements et recouvrement du mois.</span></a>
        <a href="rapport_assujetti.php"><b>Par assujetti</b><span>Historique fiscal complet d’un contribuable.</span></a>
        <a href="rapport_analytique.php"><b>Rapport analytique</b><span>Province, centre, direction, service et articles.</span></a>
        <a href="rapport_recouvrement.php"><b>Recouvrement</b><span>Créances, soldes, retards et défaillances.</span></a>
        <a href="rapport_paiements.php"><b>Paiements</b><span>Encaissements, modes, devises et références.</span></a>
        <a href="rapport_penalites.php"><b>Pénalités</b><span>Assiette et recouvrement, statut et validation.</span></a>
        <a href="rapport_performance.php"><b>Performance</b><span>Comparaison Province / Centre / Service.</span></a>
        <a href="rapport_fractionnements.php"><b>Fractionnements</b><span>Avis, tranches et échéances.</span></a>
        <a href="rapport_quittances.php"><b>Quittances</b><span>Apurements et quittances émises.</span></a>
        <a href="rapport_amr.php"><b>AMR</b><span>Avis de mise en recouvrement et retards.</span></a>
        <a href="rapport_cycle_fiscal.php"><b>Cycle fiscal</b><span>Traçabilité NT → ND → NP → paiement.</span></a>
    </div>
</section>
<?php cpRapportPageEnd(); ?>
