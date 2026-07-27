<?php
$page_title = "Lecteur QR Collect_Pay";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?= $page_title ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#06152b">

<script src="https://unpkg.com/html5-qrcode"></script>

<style>
*{box-sizing:border-box}

body{
    margin:0;
    font-family:Segoe UI,Arial,sans-serif;
    background:linear-gradient(135deg,#06152b,#0f3460);
    min-height:100vh;
    color:#111827;
    padding:18px;
}

.app{
    max-width:520px;
    margin:auto;
}

.header{
    color:white;
    text-align:center;
    margin-bottom:20px;
}

.header h1{
    margin:0;
    font-size:24px;
    font-weight:900;
}

.header p{
    color:#dbeafe;
    margin-top:8px;
}

.card{
    background:white;
    border-radius:24px;
    padding:18px;
    box-shadow:0 25px 60px rgba(0,0,0,.25);
}

#reader{
    width:100%;
    border-radius:18px;
    overflow:hidden;
    border:2px dashed #0f3460;
    background:#f8fafc;
}

.result{
    margin-top:16px;
    padding:15px;
    border-radius:16px;
    font-weight:900;
    display:none;
}

.result.ok{
    display:block;
    background:#dcfce7;
    color:#166534;
}

.result.bad{
    display:block;
    background:#fee2e2;
    color:#991b1b;
}

.details{
    margin-top:15px;
    display:none;
}

.details table{
    width:100%;
    border-collapse:collapse;
}

.details td{
    border:1px solid #e5e7eb;
    padding:9px;
    font-size:14px;
}

.details td:first-child{
    font-weight:900;
    background:#f8fafc;
}

button{
    width:100%;
    margin-top:14px;
    padding:13px;
    border:none;
    border-radius:16px;
    font-weight:900;
    color:white;
    background:linear-gradient(135deg,#0f766e,#134e4a);
}
</style>
</head>

<body>

<div class="app">

    <div class="header">
        <h1>Lecteur QR Collect_Pay</h1>
        <p>Application officielle de vérification des documents</p>
    </div>

    <div class="card">
        <div id="reader"></div>

        <div id="result" class="result"></div>

        <div id="details" class="details">
            <table>
                <tr><td>Type</td><td id="doc_type"></td></tr>
                <tr><td>Numéro</td><td id="doc_numero"></td></tr>
                <tr><td>Montant</td><td id="doc_montant"></td></tr>
                <tr><td>Statut</td><td id="doc_statut"></td></tr>
                <tr><td>Date vérification</td><td id="doc_date"></td></tr>
            </table>
        </div>

        <button onclick="location.reload()">Scanner encore</button>
    </div>

</div>
<script>
let alreadyScanned = false;

function showResult(ok, message, data = null) {
    const result = document.getElementById('result');
    const details = document.getElementById('details');

    result.className = ok ? 'result ok' : 'result bad';
    result.innerHTML = message;

    if (ok && data) {
        details.style.display = 'block';
        document.getElementById('doc_type').innerText = data.type_document || '-';
        document.getElementById('doc_numero').innerText = data.numero_document || '-';
        document.getElementById('doc_montant').innerText = data.montant || '0';
        document.getElementById('doc_statut').innerText = data.statut || '-';
        document.getElementById('doc_date').innerText = new Date().toLocaleString();
    } else {
        details.style.display = 'none';
    }
}

function onScanSuccess(decodedText) {
    if (alreadyScanned) return;
    alreadyScanned = true;

    fetch('verify.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'qr_content=' + encodeURIComponent(decodedText)
    })
    .then(response => response.json())
    .then(data => {
        if (data.valid) {
            showResult(true, '✅ DOCUMENT AUTHENTIQUE', data.document);
        } else {
            showResult(false, '❌ ' + data.message);
        }
    })
    .catch(() => {
        showResult(false, '❌ Erreur de communication avec le serveur Collect_Pay.');
    });
}

function onScanFailure(error) {}

const scanner = new Html5QrcodeScanner(
    "reader",
    {
        fps: 10,
        qrbox: 250
    }
);

scanner.render(onScanSuccess, onScanFailure);

if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('service-worker.js');
}
</script>

</body>
</html>