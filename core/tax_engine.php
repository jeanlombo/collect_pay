<?php
/*
|--------------------------------------------------------------------------
| cOllect_Pay - Tax Engine Refonte
|--------------------------------------------------------------------------
| Séparation claire :
| - IRL/RL = impôts locatifs
| - Taxes = quantité × taux, base × %, intervalle, mixte
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/period_engine.php';
require_once __DIR__ . '/currency_converter.php';

if (!function_exists('cpFiscalPersonType')) {
    function cpFiscalPersonType(array $data = []): string
    {
        $raw = cpNormFiscal((string)(
            $data['type_personne'] ??
            $data['type_contribuable'] ??
            $data['nature_contribuable'] ??
            ''
        ));

        $isCommercant = (int)($data['est_commercant'] ?? $data['commercant'] ?? 0);

        if (
            str_contains($raw, 'morale') ||
            str_contains($raw, 'societe') ||
            str_contains($raw, 'entreprise')
        ) {
            return 'personne_morale';
        }

        if (
            $isCommercant === 1 ||
            str_contains($raw, 'commercant')
        ) {
            return 'personne_physique_commercante';
        }

        return 'personne_physique_non_commercante';
    }
}

if (!function_exists('cpIrlRate')) {
    function cpIrlRate(array $data = []): float
    {
        $type = cpFiscalPersonType($data);

        if ($type === 'personne_morale' || $type === 'personne_physique_commercante') {
            return 15.0;
        }

        return 10.0;
    }
}

if (!function_exists('cpPercentLabel')) {
    function cpPercentLabel(float $taux): string
    {
        if ($taux > 0 && $taux < 1) {
            $taux *= 100;
        }

        $txt = number_format($taux, 2, ',', ' ');
        $txt = preg_replace('/,00$/', '', $txt);

        return $txt . '%';
    }
}

if (!function_exists('cpMoneyLabel')) {
    function cpMoneyLabel(float $amount, string $devise = 'CDF'): string
    {
        return number_format($amount, 2, ',', ' ') . ' ' . strtoupper($devise);
    }
}

if (!function_exists('cpDetectFiscalFamily')) {
    function cpDetectFiscalFamily(array $article, array $data = []): string
    {
        $txt = cpNormFiscal(
            (string)($article['mode_calcul'] ?? '') . ' ' .
            (string)($article['type_taux'] ?? '') . ' ' .
            (string)($article['nature_acte'] ?? '') . ' ' .
            (string)($article['libelle_taux'] ?? '') . ' ' .
            (string)($article['code_article'] ?? '')
        );

        if (str_contains($txt, 'irl') || str_contains($txt, 'impots sur les revenus locatifs')) {
            return 'irl';
        }

        if (
            str_contains($txt, 'retenu locative') ||
            str_contains($txt, 'retenue locative') ||
            str_contains($txt, 'revenu locatif') ||
            preg_match('/\brl\b/i', $txt)
        ) {
            return 'rl';
        }

        return 'taxe';
    }
}

if (!function_exists('cpNormalizeModeCalcul')) {
    function cpNormalizeModeCalcul(array $article): string
    {
        $mode = strtolower(trim((string)($article['mode_calcul'] ?? '')));
        $type = strtolower(trim((string)($article['type_taux'] ?? '')));

        if ($mode === '' || $mode === 'fixe') {
            if ($type === 'pourcentage') {
                return 'pourcentage';
            }

            return 'par_unite';
        }

        if ($mode === 'fixe') {
            return 'par_unite';
        }

        return $mode;
    }
}

if (!function_exists('calculerTaxeActe')) {
    function calculerTaxeActe(PDO $pdo, array $article, array $data): array
    {
        $family = cpDetectFiscalFamily($article, $data);
        $devise = strtoupper(trim((string)($article['devise_base'] ?? 'CDF')));

        if ($devise === '%' || $devise === '') {
            $devise = 'CDF';
        }

        $fraisAdminSource = (float)($article['frais_administratif'] ?? 0);
        $fraisTechSource  = (float)($article['frais_technique'] ?? 0);

        $details = [];

        /*
        |--------------------------------------------------------------------------
        | IRL
        |--------------------------------------------------------------------------
        */
        if ($family === 'irl') {
            $periode = cpResolvePeriod($data, null, 'impot');

            $loyerMensuel = (float)(
                $data['loyer_mensuel'] ??
                $data['montant_loyer'] ??
                $data['base_imposable'] ??
                0
            );

            $mois = (int)$periode['nombre_mois'];
            $base = $loyerMensuel * $mois;
            $taux = cpIrlRate($data);
            $principalSource = ($base * $taux) / 100;

            $details[] = [
                'libelle' => $article['nature_acte'] ?? 'IRL',
                'formule' => cpMoneyLabel($base, $devise) . ' × ' . cpPercentLabel($taux),
                'montant_source' => $principalSource,
                'montant_cdf' => cpConvertToCDF($pdo, $principalSource, $devise)['cdf'],
                'devise' => $devise,
                'mois' => $periode['mois_liste'],
            ];

            return cpFiscalResult($pdo, $article, $data, 'irl', $periode, $base, $loyerMensuel, 1, $taux, $principalSource, $fraisAdminSource, $fraisTechSource, $devise, $details);
        }

        /*
        |--------------------------------------------------------------------------
        | RL
        |--------------------------------------------------------------------------
        */
        if ($family === 'rl') {
            $periode = cpResolvePeriod($data, null, 'impot');

            $loyerMensuel = (float)(
                $data['loyer_mensuel'] ??
                $data['montant_loyer'] ??
                $data['base_imposable'] ??
                0
            );

            $mois = (int)$periode['nombre_mois'];
            $base = $loyerMensuel * $mois;
            $taux = 2.0;
            $principalSource = ($base * $taux) / 100;

            $details[] = [
                'libelle' => $article['nature_acte'] ?? 'RL',
                'formule' => cpMoneyLabel($base, $devise) . ' × ' . cpPercentLabel($taux),
                'montant_source' => $principalSource,
                'montant_cdf' => cpConvertToCDF($pdo, $principalSource, $devise)['cdf'],
                'devise' => $devise,
                'mois' => $periode['mois_liste'],
            ];

            return cpFiscalResult($pdo, $article, $data, 'rl', $periode, $base, $loyerMensuel, 1, $taux, $principalSource, $fraisAdminSource, $fraisTechSource, $devise, $details);
        }

        /*
        |--------------------------------------------------------------------------
        | TAXES
        |--------------------------------------------------------------------------
        */
        $periode = cpResolvePeriod($data, null, 'taxe');
        $mode = cpNormalizeModeCalcul($article);

        $quantite = (float)($data['quantite'] ?? 1);
        $base = (float)($data['base_imposable'] ?? $data['base_montant'] ?? 0);
        $taux = (float)($article['taux_acte'] ?? $article['taux'] ?? 0);

        if ($quantite <= 0) {
            $quantite = 1;
        }

        $principalSource = 0;

        if (in_array($mode, ['par_unite', 'quantite', 'unite', 'fixe'], true)) {
            $principalSource = $quantite * $taux;

            $details[] = [
                'libelle' => $article['nature_acte'] ?? 'Taxe',
                'formule' => number_format($quantite, 2, ',', ' ') . ' × ' . cpMoneyLabel($taux, $devise),
                'montant_source' => $principalSource,
                'montant_cdf' => cpConvertToCDF($pdo, $principalSource, $devise)['cdf'],
                'devise' => $devise,
            ];

            return cpFiscalResult($pdo, $article, $data, 'par_unite', $periode, $quantite, 0, $quantite, $taux, $principalSource, $fraisAdminSource, $fraisTechSource, $devise, $details);
        }

        if ($mode === 'pourcentage') {
            $principalSource = ($base * $taux) / 100;

            $details[] = [
                'libelle' => $article['nature_acte'] ?? 'Taxe',
                'formule' => cpMoneyLabel($base, $devise) . ' × ' . cpPercentLabel($taux),
                'montant_source' => $principalSource,
                'montant_cdf' => cpConvertToCDF($pdo, $principalSource, $devise)['cdf'],
                'devise' => $devise,
            ];

            return cpFiscalResult($pdo, $article, $data, 'pourcentage', $periode, $base, 0, $quantite, $taux, $principalSource, $fraisAdminSource, $fraisTechSource, $devise, $details);
        }

        if (in_array($mode, ['intervalle', 'par_intervalle', 'bareme', 'barème'], true)) {
            $montantIntervalle = (float)(
                $article['montant_intervalle'] ??
                $article['montant_bareme'] ??
                $article['montant_palier'] ??
                $article['taux_acte'] ??
                0
            );

            $principalSource = $montantIntervalle;

            $details[] = [
                'libelle' => $article['nature_acte'] ?? 'Taxe par intervalle',
                'formule' => 'Barème / intervalle : ' . cpMoneyLabel($montantIntervalle, $devise),
                'montant_source' => $principalSource,
                'montant_cdf' => cpConvertToCDF($pdo, $principalSource, $devise)['cdf'],
                'devise' => $devise,
            ];

            return cpFiscalResult($pdo, $article, $data, 'intervalle', $periode, $base, 0, $quantite, $taux, $principalSource, $fraisAdminSource, $fraisTechSource, $devise, $details);
        }

        if ($mode === 'mixte') {
            $principalSource = ($quantite * $taux) + (($base * $taux) / 100);

            $details[] = [
                'libelle' => $article['nature_acte'] ?? 'Taxe mixte',
                'formule' => '(' . number_format($quantite, 2, ',', ' ') . ' × ' . cpMoneyLabel($taux, $devise) . ') + (' . cpMoneyLabel($base, $devise) . ' × ' . cpPercentLabel($taux) . ')',
                'montant_source' => $principalSource,
                'montant_cdf' => cpConvertToCDF($pdo, $principalSource, $devise)['cdf'],
                'devise' => $devise,
            ];

            return cpFiscalResult($pdo, $article, $data, 'mixte', $periode, $base, 0, $quantite, $taux, $principalSource, $fraisAdminSource, $fraisTechSource, $devise, $details);
        }

        $principalSource = $taux;

        $details[] = [
            'libelle' => $article['nature_acte'] ?? 'Taxe',
            'formule' => 'Montant fixe : ' . cpMoneyLabel($taux, $devise),
            'montant_source' => $principalSource,
            'montant_cdf' => cpConvertToCDF($pdo, $principalSource, $devise)['cdf'],
            'devise' => $devise,
        ];

        return cpFiscalResult($pdo, $article, $data, 'fixe', $periode, $base, 0, $quantite, $taux, $principalSource, $fraisAdminSource, $fraisTechSource, $devise, $details);
    }
}

if (!function_exists('cpFiscalResult')) {
    function cpFiscalResult(
        PDO $pdo,
        array $article,
        array $data,
        string $typeCalcul,
        array $periode,
        float $base,
        float $loyerMensuel,
        float $quantite,
        float $taux,
        float $principalSource,
        float $fraisAdminSource,
        float $fraisTechSource,
        string $devise,
        array $details
    ): array {
        $principal = cpConvertToCDF($pdo, $principalSource, $devise);
        $fa = cpConvertToCDF($pdo, $fraisAdminSource, $devise);
        $ft = cpConvertToCDF($pdo, $fraisTechSource, $devise);

        $totalCdf = $principal['cdf'] + $fa['cdf'] + $ft['cdf'];

        if ($fraisAdminSource > 0) {
            $details[] = [
                'libelle' => 'Frais administratif',
                'formule' => cpMoneyLabel($fraisAdminSource, $devise),
                'montant_source' => $fraisAdminSource,
                'montant_cdf' => $fa['cdf'],
                'devise' => $devise,
            ];
        }

        if ($fraisTechSource > 0) {
            $details[] = [
                'libelle' => 'Frais technique',
                'formule' => cpMoneyLabel($fraisTechSource, $devise),
                'montant_source' => $fraisTechSource,
                'montant_cdf' => $ft['cdf'],
                'devise' => $devise,
            ];
        }

        return [
            'type_calcul' => $typeCalcul,
            'type_personne' => cpFiscalPersonType($data),

            'periode_code' => $periode['code'],
            'periode_libelle' => $periode['libelle'],
            'mois_concernes' => $periode['nombre_mois'],
            'mois_liste' => $periode['mois_liste'] ?? '',
            'mois_texte' => $periode['mois_texte'] ?? '',

            'loyer_mensuel' => round($loyerMensuel, 2),
            'base' => round($base, 2),
            'base_imposable' => round($base, 2),
            'quantite' => round($quantite, 6),

            'taux' => $taux,
            'taux_pourcentage' => in_array($typeCalcul, ['irl', 'rl', 'pourcentage'], true) ? $taux : 0,

            'principal_source' => round($principalSource, 2),
            'frais_admin_source' => round($fraisAdminSource, 2),
            'frais_tech_source' => round($fraisTechSource, 2),
            'total_source' => round($principalSource + $fraisAdminSource + $fraisTechSource, 2),

            'montant_acte_cdf' => round($principal['cdf'], 2),
            'montant_frais_admin_cdf' => round($fa['cdf'], 2),
            'montant_frais_tech_cdf' => round($ft['cdf'], 2),
            'total_ligne_cdf' => round($totalCdf, 2),

            'devise_source' => $devise,
            'taux_change' => $principal['taux_change'],

            'details_calcul' => json_encode([
                'details' => $details,
                'periode' => $periode,
                'devise_source' => $devise,
                'taux_change' => $principal['taux_change'],
                'total_cdf' => round($totalCdf, 2),
            ], JSON_UNESCAPED_UNICODE),

            'details' => $details,
        ];
    }
}

if (!function_exists('calculerMontantTaxe')) {
    function calculerMontantTaxe(array $article, array $data = []): float
    {
        $pdo = $GLOBALS['pdo'] ?? null;

        if ($pdo instanceof PDO) {
            $result = calculerTaxeActe($pdo, $article, $data);
            return (float)($result['total_ligne_cdf'] ?? 0);
        }

        return 0.0;
    }
}
