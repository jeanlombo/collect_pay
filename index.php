<?php
/*
|--------------------------------------------------------------------------
| cOllect_Pay - Vitrine publique / Tableau public des recettes
|--------------------------------------------------------------------------
| Version serveur AwardSpace corrigée :
| - charge proprement config/database.php
| - évite l'erreur Undefined variable $pdo
| - accepte PDO, et reste silencieuse si la base est temporairement indisponible
|--------------------------------------------------------------------------
*/

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

$databaseFile = __DIR__ . "/config/database.php";

if (file_exists($databaseFile)) {
    require_once $databaseFile;
}

/*
|--------------------------------------------------------------------------
| Connexion publique normalisée
|--------------------------------------------------------------------------
*/
function publicDb()
{
    global $pdo;

    if (isset($pdo) && $pdo instanceof PDO) {
        return $pdo;
    }

    return null;
}

$db = publicDb();

/*
|--------------------------------------------------------------------------
| Helpers sécurisés
|--------------------------------------------------------------------------
*/
function safePublic($v) {
    return htmlspecialchars((string)($v ?? '-'), ENT_QUOTES, 'UTF-8');
}

function moneyPublic($n) {
    return number_format((float)$n, 0, ',', ' ') . ' CDF';
}

function fetchOnePublic($db, $sql, $default = 0) {
    if (!$db instanceof PDO) {
        return $default;
    }

    try {
        $stmt = $db->query($sql);
        $row = $stmt ? $stmt->fetch(PDO::FETCH_NUM) : false;
        return $row ? ($row[0] ?? $default) : $default;
    } catch (Throwable $e) {
        return $default;
    }
}

function fetchAllPublic($db, $sql) {
    if (!$db instanceof PDO) {
        return [];
    }

    try {
        $stmt = $db->query($sql);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (Throwable $e) {
        return [];
    }
}

/*
|--------------------------------------------------------------------------
| Statistiques publiques
|--------------------------------------------------------------------------
*/
$totalSemaine = fetchOnePublic($db, "
    SELECT IFNULL(SUM(montant_converti_cdf),0)
    FROM paiements
    WHERE YEARWEEK(COALESCE(date_paiement, created_at),1)=YEARWEEK(CURDATE(),1)
");

$totalMois = fetchOnePublic($db, "
    SELECT IFNULL(SUM(montant_converti_cdf),0)
    FROM paiements
    WHERE MONTH(COALESCE(date_paiement, created_at))=MONTH(CURDATE())
    AND YEAR(COALESCE(date_paiement, created_at))=YEAR(CURDATE())
");

$totalAnnee = fetchOnePublic($db, "
    SELECT IFNULL(SUM(montant_converti_cdf),0)
    FROM paiements
    WHERE YEAR(COALESCE(date_paiement, created_at))=YEAR(CURDATE())
");

$totalNT = fetchOnePublic($db, "SELECT COUNT(*) FROM notes_taxation");
$totalNP = fetchOnePublic($db, "SELECT COUNT(*) FROM notes_perception");
$totalPaiements = fetchOnePublic($db, "SELECT COUNT(*) FROM paiements");
$totalQuittances = fetchOnePublic($db, "SELECT COUNT(*) FROM quittances");

$totalConstatation = fetchOnePublic($db, "
    SELECT IFNULL(SUM(total_estime),0)
    FROM notes_taxation
");

$totalOrdonnance = fetchOnePublic($db, "
    SELECT IFNULL(SUM(montant_initial),0)
    FROM notes_perception
");

$totalRecouvre = fetchOnePublic($db, "
    SELECT IFNULL(SUM(montant_converti_cdf),0)
    FROM paiements
");

$totalSolde = fetchOnePublic($db, "
    SELECT IFNULL(SUM(solde_restant),0)
    FROM notes_perception
    WHERE statut <> 'payee'
");

$tauxRecouvrement = 0;
if ((float)$totalOrdonnance > 0) {
    $tauxRecouvrement = ((float)$totalRecouvre / (float)$totalOrdonnance) * 100;
}

/*
|--------------------------------------------------------------------------
| Listes publiques limitées
|--------------------------------------------------------------------------
*/
$npDefaillantes = fetchAllPublic($db, "
    SELECT 
        numero_np,
        type_np,
        montant_initial,
        solde_restant,
        date_echeance
    FROM notes_perception
    WHERE statut <> 'payee'
    AND date_echeance IS NOT NULL
    AND date_echeance < CURDATE()
    ORDER BY date_echeance ASC
    LIMIT 5
");

$notesPayees = fetchAllPublic($db, "
    SELECT 
        np.numero_np,
        q.numero_quittance,
        q.montant_acquitte,
        q.date_emission
    FROM quittances q
    JOIN apurements a ON q.apurement_id = a.id
    JOIN notes_perception np ON a.reference_id = np.id
    ORDER BY q.date_emission DESC
    LIMIT 5
");

$notesNonPayees = fetchAllPublic($db, "
    SELECT 
        numero_np,
        type_np,
        montant_initial,
        solde_restant,
        date_echeance
    FROM notes_perception
    WHERE statut <> 'payee'
    ORDER BY created_at DESC
    LIMIT 5
");

$derniersPaiements = fetchAllPublic($db, "
    SELECT 
        p.reference_transaction,
        p.montant_converti_cdf,
        p.devise,
        COALESCE(p.date_paiement, p.created_at) AS date_paiement,
        np.numero_np
    FROM paiements p
    LEFT JOIN notes_perception np ON p.note_perception_id = np.id
    ORDER BY p.created_at DESC
    LIMIT 5
");
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>cOllect_Pay | Canalisation des Recettes Publiques</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/public.css" rel="stylesheet">

    <style>
        :root{
            --primary:#06152b;
            --secondary:#0f3460;
            --gold:#fbbf24;
            --green:#16a34a;
            --red:#dc2626;
            --muted:#64748b;
            --soft:#f8fafc;
        }

        body{
            background:#f4f7fb;
            font-family:Segoe UI,Arial,sans-serif;
            color:#0f172a;
        }

        .premium-nav{
            background:rgba(6,21,43,.95);
            backdrop-filter:blur(14px);
            box-shadow:0 10px 35px rgba(2,6,23,.25);
        }

        .navbar-brand span{
            color:var(--gold);
            font-size:12px;
            display:block;
            line-height:1;
        }

        .hero{
            min-height:92vh;
            background:
                radial-gradient(circle at top right, rgba(251,191,36,.25), transparent 30%),
                linear-gradient(135deg,#06152b,#0f3460,#1e3a8a);
            color:white;
            display:flex;
            align-items:center;
            padding:110px 0 70px;
            overflow:hidden;
            position:relative;
        }

        .hero:before{
            content:"";
            position:absolute;
            width:420px;
            height:420px;
            border-radius:50%;
            background:rgba(255,255,255,.06);
            bottom:-160px;
            right:-120px;
        }

        .hero h1{
            font-size:48px;
            line-height:1.08;
            font-weight:950;
            margin-bottom:22px;
        }

        .hero p{
            font-size:19px;
            color:#dbeafe;
            max-width:760px;
        }

        .hero-badge{
            display:inline-flex;
            align-items:center;
            gap:8px;
            background:rgba(255,255,255,.12);
            border:1px solid rgba(255,255,255,.2);
            padding:10px 14px;
            border-radius:999px;
            margin-bottom:18px;
            font-weight:800;
            color:#fff7ed;
        }

        .hero-card{
            background:rgba(255,255,255,.95);
            color:#0f172a;
            border-radius:28px;
            padding:26px;
            box-shadow:0 25px 70px rgba(2,6,23,.35);
            border:1px solid rgba(255,255,255,.3);
        }

        .hero-card h4{
            font-weight:950;
            color:var(--primary);
            margin-bottom:18px;
        }

        .stat-line{
            display:flex;
            justify-content:space-between;
            gap:15px;
            border-bottom:1px solid #e5e7eb;
            padding:13px 0;
        }

        .stat-line span{
            color:var(--muted);
            font-weight:800;
        }

        .stat-line strong{
            color:var(--secondary);
            text-align:right;
        }

        .kpi-public{
            margin-top:-45px;
            position:relative;
            z-index:2;
        }

        .kpi-box{
            background:#fff;
            border-radius:22px;
            padding:22px;
            box-shadow:0 15px 40px rgba(15,23,42,.12);
            border:1px solid #e5e7eb;
            height:100%;
        }

        .kpi-box span{
            display:block;
            color:var(--muted);
            font-weight:900;
            margin-bottom:8px;
            font-size:13px;
        }

        .kpi-box h3{
            color:var(--primary);
            font-weight:950;
            margin:0;
        }

        .section-title{
            font-weight:950;
            color:var(--primary);
            margin-bottom:12px;
        }

        .section-subtitle{
            color:var(--muted);
            max-width:800px;
            margin:0 auto 35px;
        }

        .premium-card{
            background:white;
            border-radius:24px;
            padding:24px;
            height:100%;
            box-shadow:0 12px 32px rgba(15,23,42,.08);
            border:1px solid #e5e7eb;
        }

        .premium-card h5{
            font-weight:950;
            color:var(--primary);
            margin-bottom:16px;
        }

        .mini-item{
            padding:12px 0;
            border-bottom:1px dashed #e5e7eb;
        }

        .mini-item:last-child{
            border-bottom:none;
        }

        .mini-item strong{
            color:var(--secondary);
        }

        .workflow{
            background:#fff;
            padding:70px 0;
        }

        .step-box{
            background:#f8fafc;
            border:1px solid #e5e7eb;
            padding:22px;
            border-radius:22px;
            height:100%;
            position:relative;
        }

        .step-number{
            width:38px;
            height:38px;
            border-radius:50%;
            background:var(--secondary);
            color:white;
            display:flex;
            align-items:center;
            justify-content:center;
            font-weight:950;
            margin-bottom:12px;
        }

        .features{
            background:linear-gradient(135deg,#06152b,#0f3460);
            padding:70px 0;
            color:white;
        }

        .feature-box{
            background:rgba(255,255,255,.1);
            border:1px solid rgba(255,255,255,.18);
            border-radius:22px;
            padding:24px;
            height:100%;
            font-weight:900;
            text-align:center;
            color:white;
        }

        .feature-box small{
            display:block;
            color:#dbeafe;
            margin-top:8px;
            font-weight:600;
        }

        .public-table{
            width:100%;
            border-collapse:collapse;
            font-size:14px;
        }

        .public-table th{
            background:#f1f5f9;
            color:#0f172a;
            padding:11px;
        }

        .public-table td{
            padding:11px;
            border-bottom:1px solid #e5e7eb;
        }

        .badge-soft{
            display:inline-block;
            padding:6px 10px;
            border-radius:999px;
            background:#dbeafe;
            color:#1e40af;
            font-weight:900;
            font-size:12px;
        }

        .badge-red{
            background:#fee2e2;
            color:#991b1b;
        }

        .badge-green{
            background:#dcfce7;
            color:#166534;
        }

        .cta-section{
            background:#fff;
            padding:70px 0;
        }

        .cta-box{
            background:linear-gradient(135deg,#fbbf24,#f59e0b);
            border-radius:30px;
            padding:36px;
            color:#111827;
            box-shadow:0 20px 50px rgba(245,158,11,.25);
        }

        footer{
            background:#020617;
            color:#cbd5e1;
            text-align:center;
            padding:24px 10px;
        }

        @media(max-width:992px){
            .hero h1{font-size:36px}
            .hero{padding-top:120px}
            .kpi-public{margin-top:25px}
            .navbar .ms-auto{
                display:flex;
                gap:6px;
                flex-wrap:wrap;
                justify-content:flex-end;
            }
        }
    </style>
</head>
<body>

<?php if (!$db instanceof PDO): ?>
<div style="position:fixed;left:15px;right:15px;bottom:15px;z-index:9999;background:#fff7ed;color:#9a3412;border:1px solid #fdba74;border-radius:14px;padding:12px 16px;font-weight:800;box-shadow:0 10px 30px rgba(0,0,0,.12)">
    Connexion base de données indisponible : les statistiques publiques sont affichées à zéro. Vérifiez <code>config/database.php</code> sur le serveur.
</div>
<?php endif; ?>


<nav class="navbar navbar-expand-lg navbar-dark fixed-top premium-nav">
    <div class="container">
        <a class="navbar-brand fw-bold" href="#">
            cOllect_Pay
            <span>LUXORIA PUBLIC REVENUE SUITE</span>
        </a>

        <div class="ms-auto">
            <a href="modules/inspection/scan_qr.php" class="btn btn-outline-light btn-sm">Vérifier QR</a>
            <a href="modules/ordonnancement/np_list.php" class="btn btn-warning btn-sm">Consulter NP</a>
            <a href="login.php" class="btn btn-light btn-sm">Connexion</a>
        </div>
    </div>
</nav>

<section class="hero">
    <div class="container position-relative">
        <div class="row align-items-center g-5">
            <div class="col-lg-7">
                <div class="hero-badge">🔐 Système fiscal sécurisé avec QR Code vérifiable</div>

                <h1>Plateforme digitale de canalisation et maximisation des recettes publiques</h1>

                <p>
                    cOllect_Pay sécurise toute la chaîne de mobilisation des recettes :
                    constatation, liquidation, ordonnancement, paiement, apurement,
                    quittance et contrôle QR anti-fraude.
                </p>

                <div class="d-flex flex-wrap gap-2 mt-4">
                    <a href="modules/ordonnancement/np_list.php" class="btn btn-warning btn-lg">
                        Effectuer / Suivre un paiement
                    </a>
                    <a href="modules/inspection/scan_qr.php" class="btn btn-outline-light btn-lg">
                        Vérifier un document
                    </a>
                    <a href="login.php" class="btn btn-light btn-lg">
                        Accéder au Guichet Unique
                    </a>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="hero-card">
                    <h4>Recettes réalisées</h4>

                    <div class="stat-line">
                        <span>Semaine</span>
                        <strong><?= moneyPublic($totalSemaine) ?></strong>
                    </div>

                    <div class="stat-line">
                        <span>Mois</span>
                        <strong><?= moneyPublic($totalMois) ?></strong>
                    </div>

                    <div class="stat-line">
                        <span>Année</span>
                        <strong><?= moneyPublic($totalAnnee) ?></strong>
                    </div>

                    <div class="stat-line">
                        <span>Taux de recouvrement</span>
                        <strong><?= number_format($tauxRecouvrement, 2, ',', ' ') ?> %</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="container kpi-public">
    <div class="row g-4">
        <div class="col-md-3">
            <div class="kpi-box">
                <span>Notes de taxation</span>
                <h3><?= (int)$totalNT ?></h3>
            </div>
        </div>

        <div class="col-md-3">
            <div class="kpi-box">
                <span>Notes de perception</span>
                <h3><?= (int)$totalNP ?></h3>
            </div>
        </div>

        <div class="col-md-3">
            <div class="kpi-box">
                <span>Paiements enregistrés</span>
                <h3><?= (int)$totalPaiements ?></h3>
            </div>
        </div>

        <div class="col-md-3">
            <div class="kpi-box">
                <span>Quittances émises</span>
                <h3><?= (int)$totalQuittances ?></h3>
            </div>
        </div>
    </div>
</section>

<section class="container py-5">
    <div class="text-center">
        <h2 class="section-title">Situation publique des recettes</h2>
        <p class="section-subtitle">
            Aperçu synthétique des recettes constatées, ordonnancées, recouvrées et restant à recouvrer.
        </p>
    </div>

    <div class="row g-4">
        <div class="col-md-3">
            <div class="premium-card">
                <h5>Constaté</h5>
                <h3><?= moneyPublic($totalConstatation) ?></h3>
                <p class="text-muted mb-0">Montants issus des NT.</p>
            </div>
        </div>

        <div class="col-md-3">
            <div class="premium-card">
                <h5>Ordonnancé</h5>
                <h3><?= moneyPublic($totalOrdonnance) ?></h3>
                <p class="text-muted mb-0">Montants des NP/NPF.</p>
            </div>
        </div>

        <div class="col-md-3">
            <div class="premium-card">
                <h5>Recouvré</h5>
                <h3><?= moneyPublic($totalRecouvre) ?></h3>
                <p class="text-muted mb-0">Paiements convertis CDF.</p>
            </div>
        </div>

        <div class="col-md-3">
            <div class="premium-card">
                <h5>Solde</h5>
                <h3><?= moneyPublic($totalSolde) ?></h3>
                <p class="text-muted mb-0">Reste à recouvrer.</p>
            </div>
        </div>
    </div>
</section>

<section class="workflow">
    <div class="container">
        <div class="text-center">
            <h2 class="section-title">Chaîne officielle de traitement</h2>
            <p class="section-subtitle">
                Chaque document est sécurisé, traçable et vérifiable par QR Code.
            </p>
        </div>

        <div class="row g-4">
            <div class="col-md-2 col-6">
                <div class="step-box">
                    <div class="step-number">1</div>
                    <h6>NT</h6>
                    <small>Constatation de l’assiette.</small>
                </div>
            </div>

            <div class="col-md-2 col-6">
                <div class="step-box">
                    <div class="step-number">2</div>
                    <h6>ND</h6>
                    <small>Liquidation officielle.</small>
                </div>
            </div>

            <div class="col-md-2 col-6">
                <div class="step-box">
                    <div class="step-number">3</div>
                    <h6>NP / NPF</h6>
                    <small>Ordonnancement.</small>
                </div>
            </div>

            <div class="col-md-2 col-6">
                <div class="step-box">
                    <div class="step-number">4</div>
                    <h6>Paiement</h6>
                    <small>Banque, mobile money, virement.</small>
                </div>
            </div>

            <div class="col-md-2 col-6">
                <div class="step-box">
                    <div class="step-number">5</div>
                    <h6>Apurement</h6>
                    <small>Solde et validation.</small>
                </div>
            </div>

            <div class="col-md-2 col-6">
                <div class="step-box">
                    <div class="step-number">6</div>
                    <h6>Quittance</h6>
                    <small>Acquit libératoire.</small>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="container py-5">
    <div class="row g-4">

        <div class="col-lg-4">
            <div class="premium-card border-danger">
                <h5>NP / NPF échues</h5>

                <?php foreach($npDefaillantes as $np): ?>
                    <div class="mini-item">
                        <span class="badge-soft badge-red"><?= strtoupper(safePublic($np['type_np'])) ?></span><br>
                        <strong><?= safePublic($np['numero_np']) ?></strong><br>
                        Solde : <?= moneyPublic($np['solde_restant']) ?><br>
                        Échéance : <?= safePublic($np['date_echeance']) ?>
                    </div>
                <?php endforeach; ?>

                <?php if(empty($npDefaillantes)): ?>
                    <p class="text-muted mb-0">Aucune NP échue pour le moment.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="premium-card border-success">
                <h5>Dernières quittances</h5>

                <?php foreach($notesPayees as $n): ?>
                    <div class="mini-item">
                        <span class="badge-soft badge-green">PAYÉE</span><br>
                        NP : <strong><?= safePublic($n['numero_np']) ?></strong><br>
                        QT : <?= safePublic($n['numero_quittance']) ?><br>
                        <?= moneyPublic($n['montant_acquitte']) ?>
                    </div>
                <?php endforeach; ?>

                <?php if(empty($notesPayees)): ?>
                    <p class="text-muted mb-0">Aucune quittance disponible.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="premium-card border-warning">
                <h5>Notes non soldées</h5>

                <?php foreach($notesNonPayees as $n): ?>
                    <div class="mini-item">
                        <span class="badge-soft"><?= strtoupper(safePublic($n['type_np'])) ?></span><br>
                        <strong><?= safePublic($n['numero_np']) ?></strong><br>
                        Solde : <?= moneyPublic($n['solde_restant']) ?><br>
                        Échéance : <?= safePublic($n['date_echeance']) ?>
                    </div>
                <?php endforeach; ?>

                <?php if(empty($notesNonPayees)): ?>
                    <p class="text-muted mb-0">Toutes les notes sont soldées.</p>
                <?php endif; ?>
            </div>
        </div>

    </div>
</section>

<section class="container pb-5">
    <div class="premium-card">
        <h5>Derniers paiements enregistrés</h5>

        <div class="table-responsive">
            <table class="public-table">
                <tr>
                    <th>Date</th>
                    <th>NP / NPF</th>
                    <th>Référence</th>
                    <th>Devise</th>
                    <th>Montant CDF</th>
                </tr>

                <?php foreach($derniersPaiements as $p): ?>
                    <tr>
                        <td><?= safePublic(date('d/m/Y', strtotime($p['date_paiement']))) ?></td>
                        <td><strong><?= safePublic($p['numero_np']) ?></strong></td>
                        <td><?= safePublic($p['reference_transaction']) ?></td>
                        <td><?= safePublic($p['devise']) ?></td>
                        <td><strong><?= moneyPublic($p['montant_converti_cdf']) ?></strong></td>
                    </tr>
                <?php endforeach; ?>

                <?php if(empty($derniersPaiements)): ?>
                    <tr>
                        <td colspan="5">Aucun paiement enregistré.</td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>
    </div>
</section>

<section class="features">
    <div class="container">
        <h2 class="text-center mb-5 fw-bold">Fonctionnalités clés</h2>

        <div class="row g-4">
            <div class="col-md-3">
                <div class="feature-box">
                    QR Code sécurisé
                    <small>Chaque document peut être vérifié.</small>
                </div>
            </div>

            <div class="col-md-3">
                <div class="feature-box">
                    Paiement multi-devise
                    <small>CDF / USD avec taux du jour.</small>
                </div>
            </div>

            <div class="col-md-3">
                <div class="feature-box">
                    Apurement automatique
                    <small>Solde, statut et quittance.</small>
                </div>
            </div>

            <div class="col-md-3">
                <div class="feature-box">
                    Audit anti-fraude
                    <small>Traçabilité et inspection QR.</small>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="cta-section">
    <div class="container">
        <div class="cta-box">
            <div class="row align-items-center g-4">
                <div class="col-lg-8">
                    <h2 class="fw-bold">Guichet Unique Digital des Recettes Publiques</h2>
                    <p class="mb-0">
                        Accédez au système pour créer les documents, suivre les paiements,
                        apurer les notes et produire les quittances sécurisées.
                    </p>
                </div>

                <div class="col-lg-4 text-lg-end">
                    <a href="login.php" class="btn btn-dark btn-lg">Connexion au système</a>
                </div>
            </div>
        </div>
    </div>
</section>

<footer>
    <p class="mb-0">© <?= date('Y') ?> cOllect_Pay — Système digital de gestion et maximisation des recettes publiques</p>
</footer>

<?php require_once __DIR__ . "/verification_widget.php"; ?>
</body>
</html>
