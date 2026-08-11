<?php
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../config/security.php";

checkAuth();

/*
|--------------------------------------------------------------------------
| Helpers communs des rapports
|--------------------------------------------------------------------------
| Les pages de rapports utilisent uniquement la structure réelle de la
| base CollectPay. Aucun ALTER TABLE ni modification de données.
*/

if (!function_exists('cpRapportH')) {
    function cpRapportH($value): string {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('cpRapportDate')) {
    function cpRapportDate($value): string {
        if (!$value) return '-';
        $ts = strtotime((string)$value);
        return $ts ? date('d/m/Y', $ts) : (string)$value;
    }
}

if (!function_exists('cpRapportMoney')) {
    function cpRapportMoney($value, string $devise = 'CDF'): string {
        return number_format((float)$value, 2, ',', ' ') . ' ' . $devise;
    }
}

if (!function_exists('cpRapportStatusBadge')) {
    function cpRapportStatusBadge($statut): string {
        $s = strtolower(trim((string)$statut));
        $map = [
            'payee' => ['PAYÉE', 'success'],
            'apure_total' => ['APURÉ TOTAL', 'success'],
            'total' => ['TOTAL', 'success'],
            'validee' => ['VALIDÉE', 'success'],
            'valide' => ['VALIDE', 'success'],
            'partiellement_payee' => ['PARTIELLE', 'warning'],
            'apure_partiel' => ['APURÉ PARTIEL', 'warning'],
            'partiel' => ['PARTIEL', 'warning'],
            'en_attente' => ['EN ATTENTE', 'info'],
            'non_payee' => ['NON PAYÉE', 'muted'],
            'defaillante' => ['DÉFAILLANTE', 'danger'],
            'en_retard' => ['EN RETARD', 'danger'],
            'annulee' => ['ANNULÉE', 'muted'],
            'annule' => ['ANNULÉ', 'muted'],
            'rejete' => ['REJETÉ', 'danger'],
            'emis' => ['ÉMIS', 'info'],
            'accorde' => ['ACCORDÉ', 'success'],
            'proposee' => ['PROPOSÉE', 'info'],
            'suspendue' => ['SUSPENDUE', 'warning'],
        ];
        [$label, $class] = $map[$s] ?? [strtoupper($s ?: '-'), 'muted'];
        return '<span class="rp-badge rp-'.$class.'">'.cpRapportH($label).'</span>';
    }
}

if (!function_exists('cpRapportNomContribuableSql')) {
    function cpRapportNomContribuableSql(string $alias = 'c'): string {
        return "COALESCE(NULLIF({$alias}.raison_sociale,''), NULLIF(TRIM(CONCAT_WS(' ',{$alias}.nom,{$alias}.postnom,{$alias}.prenom)),''), 'Sans nom')";
    }
}

if (!function_exists('cpRapportFilters')) {
    function cpRapportFilters(): array {
        $today = date('Y-m-d');
        $first = date('Y-m-01');
        return [
            'date_debut' => trim((string)($_GET['date_debut'] ?? $first)),
            'date_fin' => trim((string)($_GET['date_fin'] ?? $today)),
            'province_id' => (int)($_GET['province_id'] ?? 0),
            'centre_id' => (int)($_GET['centre_id'] ?? 0),
            'direction_id' => (int)($_GET['direction_id'] ?? 0),
            'service_id' => (int)($_GET['service_id'] ?? 0),
        ];
    }
}

if (!function_exists('cpRapportScopeWhere')) {
    function cpRapportScopeWhere(array $f, string $dateExpr): array {
        $where = [];
        $params = [];

        if (!empty($f['date_debut'])) {
            $where[] = "DATE({$dateExpr}) >= ?";
            $params[] = $f['date_debut'];
        }
        if (!empty($f['date_fin'])) {
            $where[] = "DATE({$dateExpr}) <= ?";
            $params[] = $f['date_fin'];
        }
        if (!empty($f['province_id'])) {
            $where[] = "pr.id = ?";
            $params[] = $f['province_id'];
        }
        if (!empty($f['centre_id'])) {
            $where[] = "ce.id = ?";
            $params[] = $f['centre_id'];
        }
        if (!empty($f['direction_id'])) {
            $where[] = "d.id = ?";
            $params[] = $f['direction_id'];
        }
        if (!empty($f['service_id'])) {
            $where[] = "s.id = ?";
            $params[] = $f['service_id'];
        }

        return [$where, $params];
    }
}

if (!function_exists('cpRapportCatalogues')) {
    function cpRapportCatalogues(PDO $pdo): array {
        $provinces = $pdo->query("
            SELECT id, nom FROM provinces WHERE actif=1 ORDER BY nom
        ")->fetchAll(PDO::FETCH_ASSOC);

        $centres = $pdo->query("
            SELECT ce.id, ce.nom, ce.province_id, pr.nom AS province
            FROM centres ce
            JOIN provinces pr ON ce.province_id=pr.id
            WHERE ce.actif=1
            ORDER BY pr.nom, ce.nom
        ")->fetchAll(PDO::FETCH_ASSOC);

        $directions = $pdo->query("
            SELECT id, nom_direction FROM directions WHERE actif=1 ORDER BY nom_direction
        ")->fetchAll(PDO::FETCH_ASSOC);

        $services = $pdo->query("
            SELECT s.id, s.nom_service, s.centre_id, s.direction_id,
                   ce.nom AS centre, pr.nom AS province
            FROM services_assiette s
            JOIN centres ce ON s.centre_id=ce.id
            JOIN provinces pr ON ce.province_id=pr.id
            WHERE s.actif=1
            ORDER BY pr.nom, ce.nom, s.nom_service
        ")->fetchAll(PDO::FETCH_ASSOC);

        return compact('provinces','centres','directions','services');
    }
}

if (!function_exists('cpRapportFilterHtml')) {
    function cpRapportFilterHtml(array $f, array $catalogues, bool $dates = true): void {
        ?>
        <form method="GET" class="rp-filter-grid">
            <?php if ($dates): ?>
                <label>Du
                    <input type="date" name="date_debut" value="<?= cpRapportH($f['date_debut']) ?>">
                </label>
                <label>Au
                    <input type="date" name="date_fin" value="<?= cpRapportH($f['date_fin']) ?>">
                </label>
            <?php endif; ?>

            <label>Province
                <select name="province_id">
                    <option value="0">Toutes</option>
                    <?php foreach ($catalogues['provinces'] as $r): ?>
                        <option value="<?= (int)$r['id'] ?>" <?= $f['province_id']==(int)$r['id']?'selected':'' ?>>
                            <?= cpRapportH($r['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>Centre
                <select name="centre_id">
                    <option value="0">Tous</option>
                    <?php foreach ($catalogues['centres'] as $r): ?>
                        <option value="<?= (int)$r['id'] ?>" <?= $f['centre_id']==(int)$r['id']?'selected':'' ?>>
                            <?= cpRapportH($r['province'].' / '.$r['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>Direction
                <select name="direction_id">
                    <option value="0">Toutes</option>
                    <?php foreach ($catalogues['directions'] as $r): ?>
                        <option value="<?= (int)$r['id'] ?>" <?= $f['direction_id']==(int)$r['id']?'selected':'' ?>>
                            <?= cpRapportH($r['nom_direction']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>Service
                <select name="service_id">
                    <option value="0">Tous</option>
                    <?php foreach ($catalogues['services'] as $r): ?>
                        <option value="<?= (int)$r['id'] ?>" <?= $f['service_id']==(int)$r['id']?'selected':'' ?>>
                            <?= cpRapportH($r['province'].' / '.$r['centre'].' / '.$r['nom_service']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <div class="rp-filter-action">
                <button type="submit">Appliquer</button>
            </div>
        </form>
        <?php
    }
}

if (!function_exists('cpRapportPageStart')) {
    function cpRapportPageStart(string $title, string $subtitle = ''): void {
        $page_title = $title;
        ?>
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?= cpRapportH($title) ?> | cOllect_Pay</title>
            <link rel="stylesheet" href="../../assets/css/admin.css">
            <link rel="stylesheet" href="../../assets/css/rapports.css">
        </head>
        <body class="cp-rapports-page">
        <div class="admin-layout">
            <?php require __DIR__ . "/../../includes/sidebar.php"; ?>
            <main class="main-content">
                <?php require __DIR__ . "/../../includes/topbar.php"; ?>
                <section class="rp-hero">
                    <span class="rp-kicker">Rapports & statistiques</span>
                    <h1><?= cpRapportH($title) ?></h1>
                    <?php if ($subtitle): ?><p><?= cpRapportH($subtitle) ?></p><?php endif; ?>
                </section>
        <?php
    }
}

if (!function_exists('cpRapportPageEnd')) {
    function cpRapportPageEnd(): void {
        ?>
            </main>
        </div>
        </body>
        </html>
        <?php
    }
}
