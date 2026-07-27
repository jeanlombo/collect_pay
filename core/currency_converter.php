<?php
/*
|--------------------------------------------------------------------------
| cOllect_Pay - Currency Converter
|--------------------------------------------------------------------------
*/

if (!function_exists('cpGetTauxChange')) {
    function cpGetTauxChange(PDO $pdo, string $from = 'USD', string $to = 'CDF'): float
    {
        $from = strtoupper(trim($from));
        $to = strtoupper(trim($to));

        if ($from === $to) {
            return 1.0;
        }

        try {
            $tables = array_map('current', $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_NUM));

            if (!in_array('taux_change', $tables, true)) {
                return 2800.0;
            }

            $cols = array_column($pdo->query("SHOW COLUMNS FROM taux_change")->fetchAll(PDO::FETCH_ASSOC), 'Field');

            $rateCol = 'taux';

            if (!in_array('taux', $cols, true)) {
                if (in_array('taux_change', $cols, true)) {
                    $rateCol = 'taux_change';
                } elseif (in_array('valeur', $cols, true)) {
                    $rateCol = 'valeur';
                } else {
                    return 2800.0;
                }
            }

            $where = [];
            $params = [];

            if (in_array('devise_source', $cols, true)) {
                $where[] = "devise_source = ?";
                $params[] = $from;
            }

            if (in_array('devise_cible', $cols, true)) {
                $where[] = "devise_cible = ?";
                $params[] = $to;
            }

            if (in_array('actif', $cols, true)) {
                $where[] = "actif = 1";
            }

            $sql = "SELECT {$rateCol} AS taux FROM taux_change";

            if ($where) {
                $sql .= " WHERE " . implode(" AND ", $where);
            }

            if (in_array('date_taux', $cols, true)) {
                $sql .= " ORDER BY date_taux DESC, id DESC LIMIT 1";
            } else {
                $sql .= " ORDER BY id DESC LIMIT 1";
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row && (float)$row['taux'] > 0 ? (float)$row['taux'] : 2800.0;

        } catch (Throwable $e) {
            return 2800.0;
        }
    }
}

if (!function_exists('cpConvertToCDF')) {
    function cpConvertToCDF(PDO $pdo, float $amount, string $deviseSource = 'CDF'): array
    {
        $deviseSource = strtoupper(trim($deviseSource));

        if ($deviseSource === '%' || $deviseSource === '') {
            $deviseSource = 'CDF';
        }

        if ($deviseSource === 'CDF') {
            return [
                'source' => round($amount, 2),
                'cdf' => round($amount, 2),
                'devise_source' => 'CDF',
                'taux_change' => 1.0,
            ];
        }

        if ($deviseSource === 'USD') {
            $taux = cpGetTauxChange($pdo, 'USD', 'CDF');

            return [
                'source' => round($amount, 2),
                'cdf' => round($amount * $taux, 2),
                'devise_source' => 'USD',
                'taux_change' => $taux,
            ];
        }

        return [
            'source' => round($amount, 2),
            'cdf' => round($amount, 2),
            'devise_source' => 'CDF',
            'taux_change' => 1.0,
        ];
    }
}
