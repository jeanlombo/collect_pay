<?php
/*
|--------------------------------------------------------------------------
| cOllect_Pay - Helper affichage taux fiscal
|--------------------------------------------------------------------------
*/

if (!function_exists('cpFormatTauxFiscalNT')) {
    function cpFormatTauxFiscalNT($taux, bool $isPercent = false): string
    {
        $taux = (float)($taux ?? 0);

        if ($isPercent) {
            $txt = number_format($taux, 2, ',', ' ');
            $txt = preg_replace('/,00$/', '', $txt);
            return $txt . '%';
        }

        return number_format($taux, 2, ',', ' ') . ' CDF';
    }
}

if (!function_exists('cpIsTauxPercentFiscalNT')) {
    function cpIsTauxPercentFiscalNT(array $detail): bool
    {
        $type = strtolower((string)($detail['type_calcul'] ?? ''));
        $libelle = strtolower((string)($detail['libelle_acte'] ?? $detail['nature_acte'] ?? ''));

        return in_array($type, ['irl', 'rl', 'pourcentage'], true)
            || str_contains($libelle, 'irl')
            || str_contains($libelle, 'revenu locatif')
            || str_contains($libelle, 'retenu locative')
            || str_contains($libelle, 'retenue locative')
            || str_contains($libelle, 'rl');
    }
}
