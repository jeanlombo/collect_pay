<?php
ob_start();
ini_set("display_errors", "0");
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);

/*
|--------------------------------------------------------------------------
| Compatibilité PHP 8.2 / 8.3 pour FPDF
|--------------------------------------------------------------------------
*/
if (!function_exists('pdfTxt')) {
    function pdfTxt($text)
    {
        $text = (string)($text ?? '');

        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $text);
            if ($converted !== false) {
                return $converted;
            }
        }

        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');
        }

        return $text;
    }
}

if (!function_exists('cpCleanOutputBeforePdf')) {
    function cpCleanOutputBeforePdf()
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }
}

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../config/security.php";
require_once __DIR__ . "/../../lib/fpdf/fpdf.php";
require_once __DIR__ . "/../../lib/phpqrcode/qrlib.php";
require_once __DIR__ . "/../../core/secure_qr_engine.php";

checkAuth();

$numero = $_GET['numero'] ?? null;

if (!$numero) {
    die("Numéro ND obligatoire.");
}

$stmt = $pdo->prepare("
    SELECT
        nd.*,
        nt.numero_nt,
        nt.exercice,
        nt.created_at AS date_nt,
        c.type_personne,
        c.raison_sociale,
        c.nom,
        c.postnom,
        c.prenom,
        c.nif,
        c.rccm,
        c.telephone,
        c.adresse,
        c.ville,
        u.nom AS liquidateur
    FROM notes_debit nd
    JOIN notes_taxation nt ON nd.note_taxation_id = nt.id
    JOIN contribuables c ON nt.contribuable_id = c.id
    LEFT JOIN users u ON nd.user_liquidateur_id = u.id
    WHERE nd.numero_nd = ?
    LIMIT 1
");
$stmt->execute([$numero]);
$nd = $stmt->fetch();

if (!$nd) {
    die("ND introuvable.");
}

$stmt = $pdo->prepare("
    SELECT
        d.*,
        ab.code_article,
        ab.nature_acte,
        ab.secteur,
        ab.taux_acte AS article_taux_acte,
        ab.devise_base AS article_devise_base,
        ab.libelle_taux AS article_libelle_taux
    FROM notes_taxation_details d
    LEFT JOIN articles_budgetaires ab ON d.article_id = ab.id
    WHERE d.note_taxation_id = ?
    ORDER BY d.id ASC
");
$stmt->execute([$nd['note_taxation_id']]);
$details = $stmt->fetchAll();

function cleanTextNDPDF($value)
{
    $value = trim(preg_replace("/\s+/u", " ", (string)($value ?? "")));
    return $value !== "" ? $value : "-";
}

function moneyNDPDF($value)
{
    return number_format((float)$value, 2, ',', ' ') . ' CDF';
}

function qtyNDPDF($value)
{
    $v = (float)$value;
    if (floor($v) == $v) {
        return number_format($v, 0, ',', ' ');
    }
    return number_format($v, 2, ',', ' ');
}

function sourceMoneyNDPDF($value, $devise)
{
    return number_format((float)$value, 2, ',', ' ') . ' ' . strtoupper($devise ?: 'CDF');
}

function nomContribuableNDPDF($c)
{
    if (!empty($c['raison_sociale'])) {
        return $c['raison_sociale'];
    }

    return trim(($c['nom'] ?? '') . ' ' . ($c['postnom'] ?? '') . ' ' . ($c['prenom'] ?? ''));
}

function formatDateNDPDF($date)
{
    if (!$date) return '-';
    return date('d/m/Y H:i:s', strtotime($date));
}

function fileNameSafeNDPDF($value)
{
    return preg_replace('/[^A-Za-z0-9_\-]/', '_', $value);
}

function detailTextNDPDF($d)
{
    $devise = strtoupper($d['devise_source'] ?? $d['article_devise_base'] ?? 'CDF');
    $tauxChange = (float)($d['taux_change'] ?? 1);
    if ($tauxChange <= 0) $tauxChange = 1;

    $quantite = (float)($d['quantite'] ?? 1);
    $principalCdf = (float)($d['montant_acte'] ?? 0);
    $faCdf = (float)($d['montant_frais_admin'] ?? 0);
    $ftCdf = (float)($d['montant_frais_tech'] ?? 0);

    $tauxActe = (float)($d['article_taux_acte'] ?? 0);
    if ($tauxActe <= 0 && $quantite > 0) {
        if ($devise === 'USD') {
            $tauxActe = ($principalCdf / $tauxChange) / $quantite;
        } else {
            $tauxActe = $principalCdf / $quantite;
        }
    }

    if ($devise === 'USD') {
        $principalUsd = $principalCdf / $tauxChange;
        $faUsd = $faCdf / $tauxChange;
        $ftUsd = $ftCdf / $tauxChange;

        return
            qtyNDPDF($quantite) . " x " .
            number_format($tauxActe, 2, ',', ' ') . " USD = " .
            number_format($principalUsd, 2, ',', ' ') . " USD\n" .
            "ou soit " . moneyNDPDF($principalCdf) . "\n" .
            "Frais administratif : " .
            number_format($faUsd, 2, ',', ' ') . " USD x " .
            number_format($tauxChange, 0, ',', ' ') . " = " .
            moneyNDPDF($faCdf) . "\n" .
            "Frais technique : " .
            number_format($ftUsd, 2, ',', ' ') . " USD x " .
            number_format($tauxChange, 0, ',', ' ') . " = " .
            moneyNDPDF($ftCdf);
    }

    return
        qtyNDPDF($quantite) . " x " .
        number_format($tauxActe, 2, ',', ' ') . " CDF = " .
        moneyNDPDF($principalCdf) . "\n" .
        "Frais administratif : " . moneyNDPDF($faCdf) . "\n" .
        "Frais technique : " . moneyNDPDF($ftCdf);
}

$totalGeneral = (float)($nd['montant_total'] ?? $nd['total_exigible'] ?? 0);

$qrContent = buildEncryptedQrContent(
    $pdo,
    'ND',
    $nd['numero_nd'],
    $totalGeneral
);

$GLOBALS['qrMatrix'] = QRcode::text(
    $qrContent,
    false,
    QR_ECLEVEL_L,
    0,
    1
);

class NDPDF extends FPDF
{
    function DrawQRCode($matrix, $x, $y, $size = 0.85)
    {
        if (!is_array($matrix) || empty($matrix)) return;

        $this->SetFillColor(0, 0, 0);

        foreach ($matrix as $rowIndex => $row) {
            $row = (string)$row;
            for ($colIndex = 0; $colIndex < strlen($row); $colIndex++) {
                if ($row[$colIndex] === '1') {
                    $this->Rect(
                        $x + ($colIndex * $size),
                        $y + ($rowIndex * $size),
                        $size,
                        $size,
                        'F'
                    );
                }
            }
        }
    }

    function QrPrintedSize($matrix, $size = 0.85)
    {
        if (!is_array($matrix) || empty($matrix)) return 0;
        return count($matrix) * $size;
    }

    function Header()
    {
        if (file_exists("../assets/images/logo_province.png")) {
            $this->Image("../assets/images/logo_province.png", 10, 8, 24);
        }

        $qrMatrix = $GLOBALS['qrMatrix'] ?? [];
        $qrX = 162;
        $qrY = 8;
        $qrCellSize = 0.85;
        $this->DrawQRCode($qrMatrix, $qrX, $qrY, $qrCellSize);

        $qrSize = $this->QrPrintedSize($qrMatrix, $qrCellSize);
        $this->SetFont('Arial', '', 6);
        $this->SetXY($qrX - 2, $qrY + $qrSize + 1);
        $this->Cell(42, 3, pdfTxt('QR Code sécurisé'), 0, 0, 'C');

        $this->SetY(8);
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(0, 5, pdfTxt('REPUBLIQUE DEMOCRATIQUE DU CONGO'), 0, 1, 'C');
        $this->Cell(0, 5, pdfTxt('PROVINCE DE LA TSHOPO'), 0, 1, 'C');

        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 5, pdfTxt('DIRECTION GENERALE DES RECETTES DE LA TSHOPO'), 0, 1, 'C');

        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 5, pdfTxt('SERVICE DE LIQUIDATION'), 0, 1, 'C');
        $this->Ln(16);
    }

    function Footer()
    {
        $this->SetY(-12);
        $this->SetFont('Arial', '', 8);
        $this->Cell(0, 5, pdfTxt('Page ' . $this->PageNo() . ' - Copyright ' . date('Y') . ' - cOllect_Pay, Tous droits réservés'), 0, 0, 'C');
    }

    function SectionTitle($title)
    {
        $this->SetFillColor(230, 230, 230);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(0, 7, pdfTxt($title), 1, 1, 'C', true);
    }

    function NbLines($w, $txt)
    {
        $cw = &$this->CurrentFont['cw'];
        if ($w == 0) $w = $this->w - $this->rMargin - $this->x;
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', (string)$txt);
        $nb = strlen($s);
        if ($nb > 0 && $s[$nb - 1] == "\n") $nb--;
        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $nl = 1;
        while ($i < $nb) {
            $c = $s[$i];
            if ($c == "\n") {
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
                continue;
            }
            if ($c == ' ') $sep = $i;
            $l += $cw[$c] ?? 0;
            if ($l > $wmax) {
                if ($sep == -1) {
                    if ($i == $j) $i++;
                } else {
                    $i = $sep + 1;
                }
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
            } else {
                $i++;
            }
        }
        return $nl;
    }

    function RowLabelValue($label, $value)
    {
        $this->SetFont('Arial', '', 8);
        $label = pdfTxt(cleanTextNDPDF($label));
        $value = pdfTxt(cleanTextNDPDF($value));

        $w1 = 55;
        $w2 = 131;
        $lineH = 5;
        $h = max($this->NbLines($w1, $label), $this->NbLines($w2, $value)) * $lineH;

        $x = $this->GetX();
        $y = $this->GetY();

        $this->Rect($x, $y, $w1, $h);
        $this->MultiCell($w1, $lineH, $label, 0, 'L');

        $this->SetXY($x + $w1, $y);
        $this->Rect($x + $w1, $y, $w2, $h);
        $this->MultiCell($w2, $lineH, $value, 0, 'L');

        $this->SetXY($x, $y + $h);
    }

    function MultiRow($widths, $texts, $aligns = [])
    {
        $lineH = 4.5;
        $maxLines = 1;

        foreach ($texts as $i => $txt) {
            $maxLines = max($maxLines, $this->NbLines($widths[$i], pdfTxt(cleanTextNDPDF($txt))));
        }

        $h = $maxLines * $lineH;
        $x = $this->GetX();
        $y = $this->GetY();

        foreach ($texts as $i => $txt) {
            $w = $widths[$i];
            $a = $aligns[$i] ?? 'L';

            $this->Rect($x, $y, $w, $h);
            $this->SetXY($x, $y);
            $this->MultiCell($w, $lineH, pdfTxt(cleanTextNDPDF($txt)), 0, $a);
            $x += $w;
            $this->SetXY($x, $y);
        }

        $this->SetXY($this->lMargin, $y + $h);
    }
}

$pdf = new NDPDF('P', 'mm', 'A4');
$pdf->SetMargins(12, 12, 12);
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 15);

/*
|--------------------------------------------------------------------------
| TITRE
|--------------------------------------------------------------------------
*/
$pdf->SetFont('Arial', 'B', 13);
$pdf->Cell(0, 9, pdfTxt('NOTE DE DÉBIT N° ' . $nd['numero_nd']), 0, 1, 'C');

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 7, pdfTxt('EXERCICE ' . ($nd['exercice'] ?? date('Y'))), 0, 1, 'C');
$pdf->Ln(3);

/*
|--------------------------------------------------------------------------
| CONTRIBUABLE
|--------------------------------------------------------------------------
*/
$pdf->SectionTitle('I. CONTRIBUABLE');
$pdf->RowLabelValue('Type', strtoupper($nd['type_personne'] ?? '-'));
$pdf->RowLabelValue('Nom / Raison sociale', nomContribuableNDPDF($nd));
$pdf->RowLabelValue('NIF', $nd['nif'] ?? '-');
$pdf->RowLabelValue('RCCM / Patente', $nd['rccm'] ?? '-');
$pdf->RowLabelValue('Contacts', $nd['telephone'] ?? '-');
$pdf->RowLabelValue('Adresse', trim(($nd['ville'] ?? '') . ' - ' . ($nd['adresse'] ?? '-')));

$pdf->Ln(3);

/*
|--------------------------------------------------------------------------
| RÉFÉRENCES
|--------------------------------------------------------------------------
*/
$pdf->SectionTitle('II. RÉFÉRENCES DE LIQUIDATION');
$pdf->RowLabelValue('Note de Taxation source', $nd['numero_nt'] ?? '-');
$pdf->RowLabelValue('Date NT', formatDateNDPDF($nd['date_nt'] ?? null));
$pdf->RowLabelValue('Date liquidation', $nd['date_liquidation'] ?? '-');
$pdf->RowLabelValue('Liquidateur', $nd['liquidateur'] ?? '-');
$pdf->RowLabelValue('Statut', strtoupper($nd['statut'] ?? '-'));

$pdf->Ln(3);

/*
|--------------------------------------------------------------------------
| DÉTAILS
|--------------------------------------------------------------------------
*/
$pdf->SectionTitle('III. BASE ET LIQUIDATION');

$pdf->SetFont('Arial', 'B', 7);
$pdf->SetFillColor(240, 240, 240);
$pdf->Cell(20, 7, pdfTxt('Période'), 1, 0, 'C', true);
$pdf->Cell(52, 7, pdfTxt('Désignation'), 1, 0, 'C', true);
$pdf->Cell(18, 7, pdfTxt('Qté'), 1, 0, 'C', true);
$pdf->Cell(42, 7, pdfTxt('Détail calcul'), 1, 0, 'C', true);
$pdf->Cell(27, 7, pdfTxt('Principal'), 1, 0, 'C', true);
$pdf->Cell(27, 7, pdfTxt('Total'), 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 7);

if (empty($details)) {
    $pdf->Cell(186, 8, pdfTxt('Aucun détail liquidé.'), 1, 1, 'L');
} else {
    foreach ($details as $d) {
        $periode = $d['periode_libelle'] ?? $d['periodicite_info'] ?? $d['periodicite'] ?? '-';
        $designation =
            cleanTextNDPDF($d['secteur'] ?? '') . "\n" .
            cleanTextNDPDF($d['nature_acte'] ?? $d['libelle_acte'] ?? '-') . "\n" .
            'Code : ' . cleanTextNDPDF($d['code_article'] ?? '-');

        $pdf->MultiRow(
            [20, 52, 18, 42, 27, 27],
            [
                $periode,
                $designation,
                qtyNDPDF($d['quantite'] ?? 1),
                detailTextNDPDF($d),
                moneyNDPDF($d['montant_acte'] ?? 0),
                moneyNDPDF($d['total_ligne_cdf'] ?? 0)
            ],
            ['L', 'L', 'L', 'L', 'R', 'R']
        );
    }
}

$pdf->Ln(2);

/*
|--------------------------------------------------------------------------
| TOTAUX
|--------------------------------------------------------------------------
*/
$pdf->SectionTitle('IV. SYNTHÈSE DE LIQUIDATION');

$pdf->RowLabelValue('Principal dû', moneyNDPDF($nd['montant_acte'] ?? 0));
$pdf->RowLabelValue('Frais administratifs', moneyNDPDF($nd['montant_frais_admin'] ?? 0));
$pdf->RowLabelValue('Frais techniques', moneyNDPDF($nd['montant_frais_tech'] ?? 0));
$pdf->RowLabelValue('Pénalités d’assiette', moneyNDPDF($nd['penalite_assiette'] ?? 0));
$pdf->RowLabelValue('Pénalités de recouvrement', moneyNDPDF($nd['penalite_recouvrement'] ?? 0));

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(55, 8, pdfTxt('TOTAL EXIGIBLE'), 1, 0, 'L');
$pdf->Cell(131, 8, pdfTxt(moneyNDPDF($totalGeneral)), 1, 1, 'R');

$pdf->Ln(3);

/*
|--------------------------------------------------------------------------
| OBSERVATION
|--------------------------------------------------------------------------
*/
$pdf->SectionTitle('V. OBSERVATION');
$pdf->SetFont('Arial', '', 8);
$pdf->MultiCell(
    0,
    5,
    pdfTxt(cleanTextNDPDF($nd['observation'] ?? 'Aucune observation.')),
    1,
    'L'
);

$pdf->Ln(4);

/*
|--------------------------------------------------------------------------
| VALIDATION
|--------------------------------------------------------------------------
*/
$pdf->SectionTitle('VI. VALIDATION');
$pdf->RowLabelValue('Décision contrôle', $nd['decision'] ?? '-');
$pdf->RowLabelValue('Date impression', date('d/m/Y H:i:s'));

$pdf->Ln(8);

$pdf->SetFont('Arial', '', 9);
$pdf->Cell(90, 20, pdfTxt('Signature du liquidateur'), 1, 0, 'C');
$pdf->Cell(96, 20, pdfTxt('Sceau / Chef de liquidation'), 1, 1, 'C');

$pdf->Ln(5);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(0, 6, pdfTxt('GUICHET UNIQUE'), 0, 1, 'C');
$pdf->SetFont('Arial', '', 8);
$pdf->Cell(0, 5, pdfTxt('Document vérifiable par QR Code sécurisé'), 0, 1, 'C');

$fileName = 'ND_' . fileNameSafeNDPDF($nd['numero_nd']) . '.pdf';
cpCleanOutputBeforePdf();
$pdf->Output('I', $fileName);
exit;
?>
