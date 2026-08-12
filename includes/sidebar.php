<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../auth/permissions.php";

if (!function_exists('cpBaseUrl')) {
    function cpBaseUrl(): string
    {
        $host = strtolower($_SERVER['HTTP_HOST'] ?? '');
        $script = $_SERVER['SCRIPT_NAME'] ?? '';

        if (strpos($host, 'collectpay.flyflash-systems.com') !== false || strpos($host, 'flyflash-systems.com') !== false) return '';
        if (stripos($script, '/collect_pay/') === 0) return '/collect_pay';
        if (stripos($script, '/cOllect_pay/') === 0) return '/cOllect_pay';
        return '';
    }
}

if (!function_exists('cpUrl')) {
    function cpUrl(string $path): string
    {
        return rtrim(cpBaseUrl(), '/') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('cpActiveHorizontal')) {
    function cpActiveHorizontal(string $needle): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        return stripos($uri, $needle) !== false ? 'active' : '';
    }
}

if (!function_exists('cpCan')) {
    function cpCan(string $module, string $action = 'view'): bool
    {
        if (function_exists('canDo')) {
            return canDo($module, $action);
        }

        if (function_exists('hasPermission')) {
            return hasPermission($module, $action);
        }

        return true;
    }
}

if (!function_exists('cpAnyCan')) {
    function cpAnyCan(array $permissions): bool
    {
        foreach ($permissions as $perm) {
            $module = $perm[0] ?? '';
            $action = $perm[1] ?? 'view';

            if (cpCan($module, $action)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('cpNavItemSecure')) {
    function cpNavItemSecure(string $module, string $action, string $icon, string $label, string $path): void
    {
        if (!cpCan($module, $action)) return;

        echo '<a href="' . htmlspecialchars(cpUrl($path), ENT_QUOTES, 'UTF-8') . '">
                <span class="mega-icon">' . $icon . '</span>
                <strong>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</strong>
              </a>';
    }
}

$nom  = $_SESSION['nom'] ?? 'Utilisateur';
$role = $_SESSION['role'] ?? ($_SESSION['nom_role'] ?? '');

$showDashboard = cpCan('dashboard','view');

$showContribuables = cpAnyCan([
    ['contribuables','view'], ['contribuables','add'], ['contribuables','import']
]);

$showConstatation = cpAnyCan([
    ['constatation','view'], ['constatation','add'], ['constatation','submit'], ['constatation','print']
]);

$showLiquidation = cpAnyCan([
    ['liquidation','view'], ['liquidation','create_nd'], ['liquidation','print_nd'], ['liquidation','validate_nd'], ['liquidation','reject_nd']
]);

$showControle = cpAnyCan([
    ['controle','view'], ['controle','validate'], ['controle','reject'], ['controle','observe']
]);

$showOrdonnancement = cpAnyCan([
    ['ordonnancement','view'], ['ordonnancement','create_np'], ['ordonnancement','fractionner_np'], ['ordonnancement','avis_fractionnement'], ['ordonnancement','print_np']
]);

$showRecouvrement = cpAnyCan([
    ['paiements','view'], ['paiements','add_np'], ['paiements','add_npf'],
    ['recouvrement','view'], ['recouvrement','amr'], ['recouvrement','apurement'], ['recouvrement','quittance'],
    ['amr','view'], ['amr','create'], ['amr','print'], ['amr','pay'],
    ['apurement','view'], ['apurement','create'],
    ['quittances','view'], ['quittances','create'], ['quittances','print']
]);

$showPenalites = cpAnyCan([
    ['penalites','view'], ['penalites','manage'], ['penalites','history']
]);

$showInspection = cpAnyCan([
    ['inspection','view'], ['inspection','scan'], ['inspection','verify'], ['inspection','revoke'], ['inspection','fraud'], ['inspection','alerts']
]);

$showCorrections = cpAnyCan([
    ['corrections','view'], ['corrections','create'], ['corrections','validate'], ['corrections','history']
]);

$showRapports = cpAnyCan([
    ['rapports','view'],
    ['rapports','nt'],
    ['rapports','nd'],
    ['rapports','np'],
    ['rapports','amr'],
    ['rapports','attestation'],
    ['rapports','paiements'],
    ['rapports','apurements'],
    ['rapports','quittances'],
    ['rapports','analytique'],
    ['rapports','export_pdf'],
    ['rapports','export_excel']
]);

$showParametrage = cpAnyCan([
    ['parametrage','view'], ['parametrage','manage'], ['parametrage','nomenclature'],
    ['parametrage','directions'], ['parametrage','services'], ['parametrage','periodes'], ['parametrage','taux_change']
]);

$showAdmin = cpAnyCan([
    ['users','view'], ['roles','view'], ['roles','permissions'], ['administration','view'], ['administration','logs'], ['administration','backup'], ['administration','settings']
]);

$showPwa = cpAnyCan([
    ['pwa','view'], ['pwa','sync'], ['pwa','backup'], ['pwa','agents'], ['pwa','reports']
]);
?>

<style>
@media(max-width:1120px){

    .cp-mobile-menu-btn{
        display:block!important;
    }

    .cp-horizontal-nav{
        display:none!important;
        height:auto!important;
        padding:10px!important;
    }

    body.cp-mobile-nav-open .cp-horizontal-nav{
        display:block!important;
    }

    .cp-nav-list{
        display:block!important;
    }

    .cp-dropdown,
    .cp-mega-menu{
        position:static!important;
        display:block!important;
        width:100%!important;
        transform:none!important;
        min-width:100%!important;
        box-shadow:none!important;
        border-radius:14px!important;
        margin:8px 0 12px!important;
    }

    .cp-mega-grid{
        grid-template-columns:1fr!important;
    }

    /* ---------------------------------------------------------
       UTILISATEUR CONNECTÉ
       On conserve le nom + rôle visibles même en mode responsive
       --------------------------------------------------------- */

    .cp-user-top{
        display:flex!important;
        align-items:center!important;
        gap:8px!important;
        min-width:0!important;
    }

    .cp-user-top > div:not(.cp-user-avatar){
        display:block!important;
        min-width:0!important;
    }

    .cp-user-top strong{
        display:block!important;
        max-width:160px!important;
        overflow:hidden!important;
        text-overflow:ellipsis!important;
        white-space:nowrap!important;
        font-size:11px!important;
        color:white!important;
        line-height:1.15!important;
    }

    .cp-user-top small{
        display:block!important;
        max-width:160px!important;
        overflow:hidden!important;
        text-overflow:ellipsis!important;
        white-space:nowrap!important;
        font-size:9px!important;
        color:var(--cp-gold)!important;
        font-weight:1000!important;
        margin-top:3px!important;
    }

    /* Le bouton de déconnexion reste masqué sur écran réduit */
    .cp-logout-horizontal{
        display:none!important;
    }

    .main-content,
    .cp-main,
    main{
        padding:0 12px 20px!important;
    }
}
</style>

<header class="cp-horizontal-shell">
    <div class="cp-horizontal-top">
        <div style="display:flex;align-items:center;gap:10px;">
            <button type="button" class="cp-mobile-menu-btn" id="cpMobileMenuBtn">☰</button>
            <a href="<?= cpUrl('dashboard/index.php') ?>" class="cp-brand-horizontal">
                <div class="cp-brand-mark">cP</div>
                <div><h2>cOllect_Pay</h2><span>Recettes publiques RDC</span></div>
            </a>
        </div>

        <div class="cp-top-right">
            <div class="cp-user-top">
                <div class="cp-user-avatar"><?= strtoupper(substr($nom,0,1)) ?></div>
                <div><strong><?= htmlspecialchars($nom) ?></strong><small><?= htmlspecialchars($role) ?></small></div>
            </div>
            <a href="<?= cpUrl('logout.php') ?>" class="cp-logout-horizontal">⏻ Déconnexion</a>
        </div>
    </div>

    <nav class="cp-horizontal-nav" id="cpHorizontalNav">
        <ul class="cp-nav-list">

            <?php if ($showDashboard): ?>
                <li class="cp-nav-item">
                    <a class="cp-nav-link <?= cpActiveHorizontal('dashboard') ?>" href="<?= cpUrl('dashboard/index.php') ?>">🏠 Accueil</a>
                </li>
            <?php endif; ?>

            <?php if ($showContribuables): ?>
                <li class="cp-nav-item">
                    <a class="cp-nav-link">👤 Contribuables <span class="cp-nav-arrow">▼</span></a>
                    <div class="cp-dropdown">
                        <div class="cp-dropdown-title">Contribuables</div>
                        <?php cpNavItemSecure('contribuables','add','➕','Nouveau contribuable','modules/contribuables/create.php'); ?>
                        <?php cpNavItemSecure('contribuables','view','📋','Liste contribuables','modules/contribuables/list.php'); ?>
                        <?php cpNavItemSecure('contribuables','import','📥','Importation','modules/contribuables/import.php'); ?>
                    </div>
                </li>
            <?php endif; ?>

            <?php if ($showConstatation): ?>
                <li class="cp-nav-item">
                    <a class="cp-nav-link">📝 Constatation <span class="cp-nav-arrow">▼</span></a>
                    <div class="cp-dropdown">
                        <div class="cp-dropdown-title">Constatation</div>
                        <?php cpNavItemSecure('constatation','add','📝','Créer une NT','modules/constatation/nt_create.php'); ?>
                        <?php cpNavItemSecure('constatation','view','📄','Liste des NT','modules/constatation/nt_list.php'); ?>
                        <?php cpNavItemSecure('constatation','view','📁','NT brouillons','modules/constatation/nt_list.php?statut=brouillon'); ?>
                        <?php cpNavItemSecure('constatation','view','⏳','NT soumises','modules/constatation/nt_list.php?statut=en_attente_liquidation'); ?>
                    </div>
                </li>
            <?php endif; ?>

            <?php if ($showLiquidation): ?>
                <li class="cp-nav-item">
                    <a class="cp-nav-link">⚖️ Liquidation <span class="cp-nav-arrow">▼</span></a>
                    <div class="cp-dropdown">
                        <div class="cp-dropdown-title">Liquidation</div>
                        <?php cpNavItemSecure('liquidation','view','⚖️','NT à liquider','modules/liquidation/nt_a_liquider.php'); ?>
                        <?php cpNavItemSecure('liquidation','view','📑','Liste des ND','modules/liquidation/nd_list.php'); ?>
                        <?php cpNavItemSecure('liquidation','validate_nd','✅','ND validées','modules/liquidation/nd_list.php?statut=validee'); ?>
                        <?php cpNavItemSecure('liquidation','reject_nd','❌','ND rejetées','modules/liquidation/nd_list.php?statut=rejete'); ?>
                    </div>
                </li>
            <?php endif; ?>

            <?php if ($showControle): ?>
                <li class="cp-nav-item">
                    <a class="cp-nav-link">🔎 Contrôle <span class="cp-nav-arrow">▼</span></a>
                    <div class="cp-dropdown">
                        <div class="cp-dropdown-title">Contrôle / Vérification</div>
                        <?php cpNavItemSecure('controle','view','🔎','ND à contrôler','modules/liquidation/nd_list.php?statut=en_controle&mode=controle'); ?>
                        <?php cpNavItemSecure('controle','validate','✅','Valider contrôle','modules/liquidation/nd_list.php?statut=en_controle&mode=validation'); ?>
                        <?php cpNavItemSecure('controle','reject','❌','Rejeter contrôle','modules/liquidation/nd_list.php?statut=en_controle&mode=rejet'); ?>
                        <?php cpNavItemSecure('controle','observe','🧾','Observations contrôle','modules/controle/observations.php'); ?>
                    </div>
                </li>
            <?php endif; ?>

            <?php if ($showOrdonnancement): ?>
                <li class="cp-nav-item">
                    <a class="cp-nav-link">📌 Ordonnancement <span class="cp-nav-arrow">▼</span></a>
                    <div class="cp-dropdown wide">
                        <div class="cp-dropdown-title">Ordonnancement</div>
                        <div class="cp-dropdown-grid">
                            <?php cpNavItemSecure('ordonnancement','view','📌','ND conformes','modules/liquidation/nd_list.php?statut=validee'); ?>
                            <?php cpNavItemSecure('ordonnancement','view','💳','Liste NP','modules/ordonnancement/np_list.php'); ?>
                            <?php cpNavItemSecure('ordonnancement','fractionner_np','🧾','NPF','modules/ordonnancement/fractions_list.php'); ?>
                            <?php cpNavItemSecure('ordonnancement','avis_fractionnement','📜','Avis fractionnement','modules/ordonnancement/avis_fractionnement_list.php'); ?>
                        </div>
                    </div>
                </li>
            <?php endif; ?>

            <?php if ($showRecouvrement): ?>
                <li class="cp-nav-item">
                    <a class="cp-nav-link">💰 Recouvrement <span class="cp-nav-arrow">▼</span></a>
                    <div class="cp-dropdown wide">
                        <div class="cp-dropdown-title">Paiement & Recouvrement</div>
                        <div class="cp-dropdown-grid">
                            <?php cpNavItemSecure('recouvrement','view','👁️','Vue recouvrement','modules/recouvrement/index.php'); ?>
                            <?php cpNavItemSecure('paiements','add_np','💰','Paiement NP','modules/ordonnancement/np_list.php?statut=en_attente'); ?>
                            <?php cpNavItemSecure('paiements','add_npf','💵','Paiement NPF','modules/ordonnancement/np_list.php?type=fractionnee'); ?>
                            <?php cpNavItemSecure('paiements','view','📋','Liste paiements','modules/recouvrement/paiement_list.php'); ?>
                            <?php cpNavItemSecure('amr','view','🚨','Liste AMR','modules/recouvrement/amr_list.php'); ?>
                            <?php cpNavItemSecure('amr','create','➕','Générer AMR','modules/recouvrement/amr_generate.php'); ?>
                            <?php cpNavItemSecure('apurement','view','📥','Liste des apurements','modules/recouvrement/apurement_list.php'); ?>
                            <?php cpNavItemSecure('apurement','create','📥','Apurer NP / NPF','modules/ordonnancement/np_list.php?statut=payee&mode=apurement'); ?>
                            <?php cpNavItemSecure('quittances','view','📚','Liste quittances','modules/recouvrement/quittance_list.php'); ?>
                        </div>
                    </div>
                </li>
            <?php endif; ?>

            <?php if ($showPenalites): ?>
                <li class="cp-nav-item">
                    <a class="cp-nav-link">⚠️ Pénalités <span class="cp-nav-arrow">▼</span></a>
                    <div class="cp-dropdown">
                        <div class="cp-dropdown-title">Pénalités</div>
                        <?php cpNavItemSecure('penalites','view','👁️','Voir pénalités','modules/penalites/index.php'); ?>
                        <?php cpNavItemSecure('penalites','manage','⚠️','Barème pénalités','modules/penalites/parametres.php'); ?>
                        <?php cpNavItemSecure('penalites','history','📖','Historique pénalités','modules/penalites/historique.php'); ?>
                    </div>
                </li>
            <?php endif; ?>

            <?php if ($showInspection): ?>
                <li class="cp-nav-item">
                    <a class="cp-nav-link">🛡️ Inspection <span class="cp-nav-arrow">▼</span></a>
                    <div class="cp-dropdown wide">
                        <div class="cp-dropdown-title">Inspection / QR</div>
                        <div class="cp-dropdown-grid">
                            <?php cpNavItemSecure('inspection','view','🛡️','Dashboard','modules/inspection/dashboard.php'); ?>
                            <?php cpNavItemSecure('inspection','scan','🔍','Scanner QR','modules/inspection/scan_qr.php'); ?>
                            <?php cpNavItemSecure('inspection','verify','📋','Vérifications','modules/inspection/verifications.php'); ?>
                            <?php cpNavItemSecure('inspection','revoke','🚫','Révoqués','modules/inspection/documents_revoques.php'); ?>
                            <?php cpNavItemSecure('inspection','fraud','🚨','Fraudes suspectes','modules/inspection/fraude_suspecte.php'); ?>
                            <?php cpNavItemSecure('inspection','alerts','⚠️','Alertes inspection','modules/inspection/alertes.php'); ?>
                        </div>
                    </div>
                </li>
            <?php endif; ?>

            <?php if ($showCorrections): ?>
                <li class="cp-nav-item">
                    <a class="cp-nav-link">🛠️ Corrections <span class="cp-nav-arrow">▼</span></a>
                    <div class="cp-dropdown">
                        <div class="cp-dropdown-title">Corrections</div>
                        <?php cpNavItemSecure('corrections','view','📋','Liste corrections','modules/corrections/corrections_list.php'); ?>
                        <?php cpNavItemSecure('corrections','create','➕','Nouvelle correction','modules/corrections/correction_create.php'); ?>
                        <?php cpNavItemSecure('corrections','validate','✅','Documents corrigés','modules/corrections/documents_corriges.php'); ?>
                        <?php cpNavItemSecure('corrections','history','🕘','Historique','modules/corrections/historique.php'); ?>
                    </div>
                </li>
            <?php endif; ?>

            <?php if ($showRapports): ?>
                <li class="cp-nav-item">
                    <a class="cp-nav-link">📊 Rapports <span class="cp-nav-arrow">▼</span></a>
                    <div class="cp-mega-menu">
                        <div class="cp-mega-grid">

                            <div class="cp-mega-col">
                                <h4>Documents fiscaux</h4>
                                <?php cpNavItemSecure('rapports','view','📊','Tableau rapports','modules/rapports/index.php'); ?>
                                <?php cpNavItemSecure('rapports','nt','📝','Notes de Taxation','modules/rapports/rapport_nt.php'); ?>
                                <?php cpNavItemSecure('rapports','nd','⚖️','Notes de Débit','modules/rapports/rapport_nd.php'); ?>
                                <?php cpNavItemSecure('rapports','np','📌','Notes de Perception / NPF','modules/rapports/rapport_np.php'); ?>
                            </div>

                            <div class="cp-mega-col">
                                <h4>Recouvrement</h4>
                                <?php cpNavItemSecure('rapports','amr','🚨','AMR','modules/rapports/rapport_amr.php'); ?>
                                <?php cpNavItemSecure('rapports','attestation','📄','Attestations paiement','modules/rapports/rapport_attestations.php'); ?>
                                <?php cpNavItemSecure('rapports','paiements','💰','Paiements par devise','modules/rapports/rapport_paiements.php'); ?>
                                <?php cpNavItemSecure('rapports','apurements','📥','Apurements par devise','modules/rapports/rapport_apurements.php'); ?>
                                <?php cpNavItemSecure('rapports','quittances','🧾','Quittances','modules/rapports/rapport_quittances.php'); ?>
                            </div>

                            <div class="cp-mega-col">
                                <h4>Analyses métier</h4>
                                <?php cpNavItemSecure('rapports','analytique','🏢','Par service d’assiette','modules/rapports/rapport_analytique.php?axe=service'); ?>
                                <?php cpNavItemSecure('rapports','analytique','🏛️','Par direction / ressort','modules/rapports/rapport_analytique.php?axe=direction'); ?>
                                <?php cpNavItemSecure('rapports','analytique','📚','Par article budgétaire','modules/rapports/rapport_analytique.php?axe=article'); ?>
                                <?php cpNavItemSecure('rapports','analytique','🧩','Par acte taxable','modules/rapports/rapport_analytique.php?axe=acte_taxable'); ?>
                            </div>

                            <div class="cp-mega-col">
                                <h4>Axes avancés</h4>
                                <?php cpNavItemSecure('rapports','analytique','📋','Par nature d’acte','modules/rapports/rapport_analytique.php?axe=nature_acte'); ?>
                                <?php cpNavItemSecure('rapports','analytique','⚙️','Par fait générateur','modules/rapports/rapport_analytique.php?axe=fait_generateur'); ?>
                                <?php cpNavItemSecure('rapports','analytique','🏷️','Par catégorie','modules/rapports/rapport_analytique.php?axe=categorie'); ?>
                               <?php cpNavItemSecure('rapports','assujetti','👥','Par assujetti','modules/rapports/rapport_assujetti.php'); ?>
                            </div>

                            <div class="cp-mega-col">
                                <h4>Exports</h4>
                                <?php cpNavItemSecure('rapports','export_pdf','📄','Exporter PDF','modules/rapports/export_pdf.php'); ?>
                                <?php cpNavItemSecure('rapports','export_excel','📊','Exporter Excel','modules/rapports/export_excel.php'); ?>
                            </div>

                        </div>
                    </div>
                </li>
            <?php endif; ?>

            <?php if ($showParametrage): ?>
                <li class="cp-nav-item">
                    <a class="cp-nav-link">⚙️ Paramétrage <span class="cp-nav-arrow">▼</span></a>
                    <div class="cp-mega-menu">
                        <div class="cp-mega-grid">
                            <div class="cp-mega-col">
                                <h4>Général</h4>
                                <?php cpNavItemSecure('parametrage','view','⚙️','Paramétrage général','modules/parametrage/index.php'); ?>
                                <?php cpNavItemSecure('parametrage','taux_change','📈','Taux de change','modules/parametrage/taux_change.php'); ?>
                                <?php cpNavItemSecure('parametrage','periodes','📆','Périodes','modules/parametrage/periodes.php'); ?>
                            </div>
                            <div class="cp-mega-col">
                                <h4>Structure</h4>
                                <?php cpNavItemSecure('parametrage','manage','🌍','Provinces','modules/parametrage/provinces.php'); ?>
                                <?php cpNavItemSecure('parametrage','manage','🏢','Centres','modules/parametrage/centres.php'); ?>
                                <?php cpNavItemSecure('parametrage','directions','🏛️','Directions','modules/parametrage/directions.php'); ?>
                                <?php cpNavItemSecure('parametrage','services','🧩','Services','modules/parametrage/services.php'); ?>
                            </div>
                            <div class="cp-mega-col">
                                <h4>Référentiels</h4>
                                <?php cpNavItemSecure('parametrage','nomenclature','📚','Nomenclature','modules/parametrage/nomenclature.php'); ?>
                            </div>
                            <div class="cp-mega-col">
                                <h4>Finances</h4>
                                <?php cpNavItemSecure('parametrage','manage','🏦','Comptes bancaires','modules/parametrage/comptes_bancaires.php'); ?>
                                <?php cpNavItemSecure('parametrage','manage','💳','Modes paiement','modules/parametrage/modes_paiement.php'); ?>
                            </div>
                            <div class="cp-mega-col">
                                <h4>Système</h4>
                                <?php cpNavItemSecure('administration','settings','🛠️','Paramètres système','modules/administration/index.php'); ?>
                            </div>
                        </div>
                    </div>
                </li>
            <?php endif; ?>

            <?php if ($showAdmin): ?>
                <li class="cp-nav-item">
                    <a class="cp-nav-link">🔐 Admin <span class="cp-nav-arrow">▼</span></a>
                    <div class="cp-dropdown">
                        <div class="cp-dropdown-title">Administration</div>
                        <?php cpNavItemSecure('users','view','👤','Utilisateurs','users/index.php'); ?>
                        <?php cpNavItemSecure('roles','view','🛡️','Rôles','roles/index.php'); ?>
                        <?php cpNavItemSecure('roles','permissions','🔑','Permissions','roles/index.php'); ?>
                        <?php cpNavItemSecure('administration','logs','📜','Journaux','modules/administration/audit.php'); ?>
                        <?php cpNavItemSecure('administration','backup','💾','Sauvegardes','modules/administration/backup.php'); ?>
                    </div>
                </li>
            <?php endif; ?>

            <?php if ($showPwa): ?>
                <li class="cp-nav-item">
                    <a class="cp-nav-link">📱 PWA <span class="cp-nav-arrow">▼</span></a>
                    <div class="cp-dropdown">
                        <div class="cp-dropdown-title">PWA Mobile</div>
                        <?php cpNavItemSecure('pwa','view','📱','Connexion PWA','pwa/login.html'); ?>
                        <?php cpNavItemSecure('pwa','view','📊','Dashboard PWA','pwa/dashboard.html'); ?>
                        <?php cpNavItemSecure('pwa','view','🧾','Taxation PWA','pwa/tickets.html'); ?>
                        <?php cpNavItemSecure('pwa','sync','🔄','Synchronisation','pwa/sync.html'); ?>
                        <?php cpNavItemSecure('pwa','backup','💾','Backup PWA','pwa/backup.html'); ?>
                        <?php cpNavItemSecure('pwa','agents','👥','Agents terrain','pwa/agents.html'); ?>
                        <?php cpNavItemSecure('pwa','reports','📈','Rapport PWA','pwa/rapport.html'); ?>
                    </div>
                </li>
            <?php endif; ?>

        </ul>
    </nav>
</header>

<div class="cp-page-spacing"></div>

<script>
document.addEventListener('DOMContentLoaded',function(){
    const b=document.getElementById('cpMobileMenuBtn');
    if(b)b.addEventListener('click',()=>document.body.classList.toggle('cp-mobile-nav-open'));
});
</script>
