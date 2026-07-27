<?php
/*
|--------------------------------------------------------------------------
| cOllect_Pay - Period Engine
|--------------------------------------------------------------------------
| Gestion centralisée des périodes fiscales.
|--------------------------------------------------------------------------
*/

if (!function_exists('cpFiscalMonths')) {
    function cpFiscalMonths(): array
    {
        return [
            1  => 'Janvier',
            2  => 'Février',
            3  => 'Mars',
            4  => 'Avril',
            5  => 'Mai',
            6  => 'Juin',
            7  => 'Juillet',
            8  => 'Août',
            9  => 'Septembre',
            10 => 'Octobre',
            11 => 'Novembre',
            12 => 'Décembre',
        ];
    }
}

if (!function_exists('cpNormFiscal')) {
    function cpNormFiscal(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');

        return str_replace(
            ['é','è','ê','ë','à','â','î','ï','ô','ù','û','ç'],
            ['e','e','e','e','a','a','i','i','o','u','u','c'],
            $text
        );
    }
}

if (!function_exists('cpMakeFiscalPeriod')) {
    function cpMakeFiscalPeriod(string $code, string $libelle, array $nums): array
    {
        $months = cpFiscalMonths();
        $mois = [];

        foreach ($nums as $n) {
            if (isset($months[$n])) {
                $mois[] = $months[$n];
            }
        }

        return [
            'code' => $code,
            'libelle' => $libelle,
            'nombre_mois' => count($nums),
            'mois_nums' => $nums,
            'mois_liste' => implode(', ', $mois),
            'mois_texte' => implode(', ', $mois),
        ];
    }
}

if (!function_exists('cpResolvePeriod')) {
    function cpResolvePeriod(array $data = [], ?array $periodeDb = null, string $family = 'taxe'): array
    {
        $code = (string)($data['periode_code'] ?? $data['periode'] ?? $data['periodicite'] ?? '');
        $libelle = (string)($data['periode_libelle'] ?? $data['libelle_periode'] ?? '');
        $moisChoisi = (int)($data['mois'] ?? $data['mois_concerne'] ?? $data['mois_selectionne'] ?? 0);

        if ($periodeDb) {
            $code = (string)($periodeDb['code'] ?? $periodeDb['slug'] ?? $code);
            $libelle = (string)($periodeDb['libelle'] ?? $periodeDb['nom'] ?? $periodeDb['designation'] ?? $libelle);
        }

        $txt = cpNormFiscal($code . ' ' . $libelle);

        /*
        |--------------------------------------------------------------------------
        | Taxes simples : par défaut Ponctuelle
        |--------------------------------------------------------------------------
        */
        if ($family === 'taxe') {
            if (
                $txt === '' ||
                str_contains($txt, 'ponctuelle') ||
                str_contains($txt, 'ponctuel')
            ) {
                return [
                    'code' => 'ponctuelle',
                    'libelle' => 'Ponctuelle',
                    'nombre_mois' => 0,
                    'mois_nums' => [],
                    'mois_liste' => '',
                    'mois_texte' => '',
                ];
            }

            if (
                str_contains($txt, 'non renouvelable') ||
                str_contains($txt, 'non_renouvelable') ||
                str_contains($txt, 'non-renouvelable')
            ) {
                return [
                    'code' => 'non_renouvelable',
                    'libelle' => 'Non renouvelable',
                    'nombre_mois' => 0,
                    'mois_nums' => [],
                    'mois_liste' => '',
                    'mois_texte' => '',
                ];
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Impôts / revenus locatifs
        |--------------------------------------------------------------------------
        */
        if (
            str_contains($txt, '1er semestre') ||
            str_contains($txt, 'premier semestre') ||
            str_contains($txt, 'semestre_1') ||
            str_contains($txt, 's1')
        ) {
            return cpMakeFiscalPeriod('semestre_1', 'Premier semestre', range(1, 6));
        }

        if (
            str_contains($txt, '2e semestre') ||
            str_contains($txt, '2eme semestre') ||
            str_contains($txt, 'deuxieme semestre') ||
            str_contains($txt, 'deuxième semestre') ||
            str_contains($txt, 'semestre_2') ||
            str_contains($txt, 's2')
        ) {
            return cpMakeFiscalPeriod('semestre_2', 'Deuxième semestre', range(7, 12));
        }

        if (
            str_contains($txt, 'premier trimestre') ||
            str_contains($txt, '1er trimestre') ||
            str_contains($txt, 't1') ||
            str_contains($txt, 'q1')
        ) {
            return cpMakeFiscalPeriod('t1', 'Premier trimestre', [1, 2, 3]);
        }

        if (
            str_contains($txt, 'deuxieme trimestre') ||
            str_contains($txt, 'deuxième trimestre') ||
            str_contains($txt, '2e trimestre') ||
            str_contains($txt, '2eme trimestre') ||
            str_contains($txt, 't2') ||
            str_contains($txt, 'q2')
        ) {
            return cpMakeFiscalPeriod('t2', 'Deuxième trimestre', [4, 5, 6]);
        }

        if (
            str_contains($txt, 'troisieme trimestre') ||
            str_contains($txt, 'troisième trimestre') ||
            str_contains($txt, '3e trimestre') ||
            str_contains($txt, '3eme trimestre') ||
            str_contains($txt, 't3') ||
            str_contains($txt, 'q3')
        ) {
            return cpMakeFiscalPeriod('t3', 'Troisième trimestre', [7, 8, 9]);
        }

        if (
            str_contains($txt, 'quatrieme trimestre') ||
            str_contains($txt, 'quatrième trimestre') ||
            str_contains($txt, '4e trimestre') ||
            str_contains($txt, '4eme trimestre') ||
            str_contains($txt, 't4') ||
            str_contains($txt, 'q4')
        ) {
            return cpMakeFiscalPeriod('t4', 'Quatrième trimestre', [10, 11, 12]);
        }

        if (str_contains($txt, 'trimestriel') || str_contains($txt, 'trimestre')) {
            return cpMakeFiscalPeriod('t1', 'Premier trimestre', [1, 2, 3]);
        }

        if (str_contains($txt, 'semestriel') || str_contains($txt, 'semestre')) {
            return cpMakeFiscalPeriod('semestre_1', 'Premier semestre', range(1, 6));
        }

        $months = cpFiscalMonths();

        if ($moisChoisi >= 1 && $moisChoisi <= 12) {
            return cpMakeFiscalPeriod('m' . $moisChoisi, $months[$moisChoisi], [$moisChoisi]);
        }

        foreach ($months as $n => $m) {
            if (str_contains($txt, cpNormFiscal($m))) {
                return cpMakeFiscalPeriod('m' . $n, $m, [$n]);
            }
        }

        if (str_contains($txt, 'mensuel') || str_contains($txt, 'mois')) {
            return cpMakeFiscalPeriod('m1', 'Janvier', [1]);
        }

        if (str_contains($txt, 'annuel') || str_contains($txt, 'annuelle') || str_contains($txt, 'annee')) {
            return cpMakeFiscalPeriod('annuel', 'Annuel', range(1, 12));
        }

        if ($family === 'impot') {
            return cpMakeFiscalPeriod('annuel', 'Annuel', range(1, 12));
        }

        return [
            'code' => 'ponctuelle',
            'libelle' => 'Ponctuelle',
            'nombre_mois' => 0,
            'mois_nums' => [],
            'mois_liste' => '',
            'mois_texte' => '',
        ];
    }
}
