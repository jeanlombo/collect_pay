<?php
if (!function_exists('verificationPublicBase')) {
    function verificationPublicBase(): string {
        $script = $_SERVER['SCRIPT_NAME'] ?? '';

        if (stripos($script, '/collect_pay/') === 0) return '/collect_pay';
        if (stripos($script, '/cOllect_pay/') === 0) return '/cOllect_pay';

        return '';
    }
}

$baseUrl = verificationPublicBase();
$currentPage = basename($_SERVER['SCRIPT_NAME'] ?? 'login.php');
$returnUrl = ($currentPage === 'index.php') ? $baseUrl . '/index.php' : $baseUrl . '/login.php';
?>

<style>
.verif-box{
    text-align:center;
    margin:18px auto 24px;
}

.verif-small-text{
    color:#64748b;
    font-weight:700;
    font-size:13px;
    max-width:420px;
    margin:0 auto 12px;
    line-height:1.5;
}

.verif-btn-open{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:10px;
    padding:14px 28px;
    background:linear-gradient(135deg,#0f3460,#06152b);
    color:white;
    border:none;
    border-radius:50px;
    cursor:pointer;
    font-size:15px;
    font-weight:900;
    box-shadow:0 12px 28px rgba(15,52,96,.35);
    transition:.25s;
}

.verif-btn-open:hover{
    transform:translateY(-2px);
    box-shadow:0 16px 38px rgba(15,52,96,.45);
}

.verif-modal-bg{
    display:none;
    position:fixed;
    inset:0;
    background:rgba(2,6,23,.68);
    z-index:99999;
    align-items:center;
    justify-content:center;
    padding:18px;
}

.verif-modal{
    background:white;
    width:min(560px,100%);
    border-radius:24px;
    padding:0;
    box-shadow:0 30px 80px rgba(0,0,0,.38);
    overflow:hidden;
    animation:verifPop .25s ease;
}

@keyframes verifPop{
    from{transform:scale(.94);opacity:0}
    to{transform:scale(1);opacity:1}
}

.verif-modal-head{
    background:linear-gradient(135deg,#06152b,#0f3460);
    color:white;
    padding:22px;
}

.verif-modal-head h3{
    margin:0;
    font-size:22px;
    font-weight:1000;
}

.verif-modal-head p{
    margin:7px 0 0;
    color:#dbeafe;
    font-weight:700;
    font-size:13px;
}

.verif-modal-body{
    padding:22px;
}

.verif-modal-body label{
    display:block;
    font-weight:900;
    color:#0f172a;
    margin-bottom:6px;
}

.verif-modal-body select,
.verif-modal-body input{
    width:100%;
    padding:13px 14px;
    border:1px solid #d1d5db;
    border-radius:14px;
    font-weight:800;
    margin-bottom:14px;
}

.verif-actions{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    justify-content:flex-end;
    margin-top:8px;
}

.verif-actions button,
.verif-actions a{
    border:0;
    border-radius:14px;
    padding:12px 16px;
    font-weight:1000;
    cursor:pointer;
    text-decoration:none;
    display:inline-block;
}

.verif-search{
    background:#0f3460;
    color:white;
}

.verif-close{
    background:#e5e7eb;
    color:#111827;
}

.verif-login{
    background:#f6b21a;
    color:#06152b;
}

@media(max-width:600px){
    .verif-actions{
        flex-direction:column;
    }

    .verif-actions button,
    .verif-actions a{
        width:100%;
        text-align:center;
    }
}
</style>

<div class="verif-box">
    <div class="verif-small-text">
        Vérifiez instantanément l’authenticité d’une NT, ND, NP, AMR ou Quittance.
    </div>

    <button type="button" class="verif-btn-open" onclick="openVerifModal()">
        🛡 Vérifier un document
    </button>
</div>

<div class="verif-modal-bg" id="verifModal" onclick="closeVerifOutside(event)">
    <div class="verif-modal">
        <div class="verif-modal-head">
            <h3>Portail de vérification cOllect_Pay</h3>
            <p>Recherchez un document officiel émis par la plateforme.</p>
        </div>

        <div class="verif-modal-body">
            <form method="GET" action="<?= htmlspecialchars($baseUrl . '/verification/resultat.php') ?>">
                <label>Type du document</label>
                <select name="type_document">
                    <option value="ALL">Tous les documents</option>
                    <option value="NT">Note de Taxation</option>
                    <option value="ND">Note de Débit</option>
                    <option value="NP">Note de Perception</option>
                    <option value="NPF">NP Fractionnée</option>
                    <option value="AMR">AMR</option>
                    <option value="QT">Quittance</option>
                </select>

                <label>Numéro du document</label>
                <input name="numero_document"
                       placeholder="Ex : NP-BU-CPR-26-000012"
                       required>

                <div class="verif-actions">
                    <button type="button" class="verif-close" onclick="closeVerifModal()">
                        Fermer
                    </button>

                    <a class="verif-login" href="<?= htmlspecialchars($returnUrl) ?>">
                        ← Retour login
                    </a>

                    <button type="submit" class="verif-search">
                        Rechercher
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openVerifModal(){
    const modal = document.getElementById('verifModal');
    if(modal){
        modal.style.display = 'flex';
    }
}

function closeVerifModal(){
    const modal = document.getElementById('verifModal');
    if(modal){
        modal.style.display = 'none';
    }
}

function closeVerifOutside(event){
    if(event.target && event.target.id === 'verifModal'){
        closeVerifModal();
    }
}

document.addEventListener('keydown', function(e){
    if(e.key === 'Escape'){
        closeVerifModal();
    }
});
</script>