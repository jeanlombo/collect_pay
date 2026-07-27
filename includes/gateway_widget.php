<?php
if (!function_exists('gatewayPublicBase')) {
    function gatewayPublicBase(): string {
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        if (stripos($script, '/collect_pay/') === 0) return '/collect_pay';
        if (stripos($script, '/cOllect_pay/') === 0) return '/cOllect_pay';
        return '';
    }
}
?>
<style>
.gateway-mini{background:white;border:1px solid #e5e7eb;border-radius:18px;padding:16px;box-shadow:0 12px 28px rgba(15,23,42,.10);margin:16px 0}.gateway-mini h3{margin:0 0 8px;color:#06152b;font-weight:1000}.gateway-mini p{margin:0 0 12px;color:#64748b;font-weight:700;font-size:13px}.gateway-mini form{display:grid;grid-template-columns:1fr 2fr auto;gap:10px}.gateway-mini select,.gateway-mini input{width:100%;padding:11px 12px;border:1px solid #d1d5db;border-radius:12px;font-weight:800}.gateway-mini button{background:#f6b21a;color:#06152b;border:none;border-radius:12px;padding:11px 14px;font-weight:1000;cursor:pointer}@media(max-width:760px){.gateway-mini form{grid-template-columns:1fr}}
</style>
<div class="gateway-mini">
<h3>🔎 Gateway de vérification</h3>
<p>Vérifiez l’authenticité d’un document cOllect_Pay avant acceptation.</p>
<form method="GET" action="<?= htmlspecialchars(gatewayPublicBase() . '/gateway.php') ?>">
<select name="type_document"><option value="ALL">Tous les documents</option><option value="NT">Note de Taxation</option><option value="ND">Note de Débit</option><option value="NP">Note de Perception</option><option value="NPF">NP Fractionnée</option><option value="AMR">AMR</option><option value="QT">Quittance</option></select>
<input name="numero_document" placeholder="Saisir le numéro du document" required>
<button type="submit">Rechercher</button>
</form>
</div>
