<?php
/*
|--------------------------------------------------------------------------
| cOllect_Pay - Fiscal Engine
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/tax_engine.php';

if (!class_exists('FiscalEngine')) {
    class FiscalEngine
    {
        private PDO $pdo;

        public function __construct(PDO $pdo)
        {
            $this->pdo = $pdo;
        }

        public function calculate(array $article, array $data): array
        {
            return calculerTaxeActe($this->pdo, $article, $data);
        }

        public function calculateAmount(array $article, array $data): float
        {
            $result = $this->calculate($article, $data);
            return (float)($result['total_ligne_cdf'] ?? 0);
        }
    }
}
