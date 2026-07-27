/*
|--------------------------------------------------------------------------
| cOllect_Pay Mobile - Dashboard Premium DGRT
| Version : v160
|--------------------------------------------------------------------------
*/
window.addEventListener('DOMContentLoaded', function () {
    if (typeof renderDashboard === 'function') renderDashboard();
});

function moneyDashPwa(v){ return Number(v || 0).toLocaleString('fr-FR') + ' CDF'; }
function todayDash(){ return new Date().toISOString().slice(0,10); }
function dateOnlyDash(v){ return v ? String(v).slice(0,10) : ''; }
function safeDash(v){ return String(v || '-').replace(/[<>]/g, '').trim(); }

function safeDateTimeDash(v){
    try {
        if (!v) return '-';
        const d = new Date(v);
        if (isNaN(d.getTime())) return '-';
        return d.toLocaleTimeString('fr-FR');
    } catch(e) { return '-'; }
}

function groupDash(rows, keyGetter, labelGetter){
    const map = {};
    rows.forEach(r => {
        const key = keyGetter(r) || 'autre';
        const label = labelGetter(r) || key;
        if(!map[key]) map[key] = {key,label,count:0,total:0};
        map[key].count++;
        map[key].total += Number(r.montant_cdf || 0);
    });
    return Object.values(map).sort((a,b)=>b.total-a.total);
}

async function safeGetAllTaxationsDash(){
    try {
        if (typeof getAllTaxations === 'function') {
            const rows = await getAllTaxations();
            return Array.isArray(rows) ? rows : [];
        }
    } catch(e) { console.error('Erreur getAllTaxations:', e); }
    return [];
}

async function safeGetAllTicketsDash(){
    try {
        if (typeof getAllTickets === 'function') {
            const rows = await getAllTickets();
            return Array.isArray(rows) ? rows : [];
        }
    } catch(e) { console.error('Erreur getAllTickets:', e); }
    return [];
}

async function safeGetCurrentAgentDash(){
    try {
        if (typeof getCurrentAgent === 'function') return await getCurrentAgent();
    } catch(e) { console.error('Erreur getCurrentAgent:', e); }
    try { return JSON.parse(localStorage.getItem('collect_pay_agent_session') || 'null'); }
    catch(e) { return null; }
}

async function renderDashboard(){
    const box = document.getElementById('dashboardContent');
    if(!box) return;

    box.innerHTML = '<div class="dash-card">Chargement du dashboard...</div>';

    try{
        const all = await safeGetAllTaxationsDash();
        const tickets = await safeGetAllTicketsDash();
        const agent = await safeGetCurrentAgentDash();

        const today = todayDash();
        const rowsToday = all.filter(t => dateOnlyDash(t.created_at) === today);

        const totalToday = rowsToday.reduce((s,t)=>s+Number(t.montant_cdf || 0),0);
        const totalGlobal = all.reduce((s,t)=>s+Number(t.montant_cdf || 0),0);
        const pending = all.filter(t => t.sync !== true).length;
        const synced = all.filter(t => t.sync === true).length;
        const ticketsToday = tickets.filter(t => dateOnlyDash(t.created_at) === today).length;

        const byType = groupDash(
            rowsToday,
            r => r.type_taxe || 'autre',
            r => {
                if(r.type_taxe === 'chargement') return 'Chargement';
                if(r.type_taxe === 'dechargement') return 'Déchargement';
                if(r.type_taxe === 'peage') return 'Péage';
                return r.type_taxe || 'Autres';
            }
        );

        const byArticle = groupDash(
            rowsToday,
            r => r.article_id || 'sans_article',
            r => r.libelle_acte || r.nature_acte || ('Article #' + (r.article_id || '-'))
        );

        const maxType = byType.length ? Math.max(1, byType[0].total) : 1;
        const topActe = byArticle.length ? byArticle[0] : null;
        const topType = byType.length ? byType[0] : null;

        const heroTotal = document.getElementById('heroTotal');
        const heroDate = document.getElementById('heroDate');
        const agentHeader = document.getElementById('agentHeader');

        if(heroTotal) heroTotal.textContent = moneyDashPwa(totalToday);
        if(heroDate) heroDate.textContent = new Date().toLocaleDateString('fr-FR');
        if(agentHeader){
            agentHeader.textContent = (agent?.nom || 'Agent local') + ' — ' + (agent?.role || 'Terrain');
        }

        let html = `
            <section class="kpi-grid-premium">
                <div class="kpi-premium"><span>Taxations du jour</span><strong>${rowsToday.length}</strong><small>Opérations enregistrées</small></div>
                <div class="kpi-premium"><span>En attente Sync</span><strong>${pending}</strong><small>À envoyer au serveur</small></div>
                <div class="kpi-premium"><span>Synchronisées</span><strong>${synced}</strong><small>Déjà envoyées</small></div>
                <div class="kpi-premium"><span>Tickets du jour</span><strong>${ticketsToday}</strong><small>Tickets imprimés/localisés</small></div>
            </section>

            <section class="dash-card">
                <h3>🏆 Performance du jour</h3>
                <div class="performance-box">
                    <div><span>Top type taxe</span><strong>${topType ? safeDash(topType.label) : '-'}</strong><small>${topType ? moneyDashPwa(topType.total) : '0 CDF'}</small></div>
                    <div><span>Top acte taxable</span><strong>${topActe ? safeDash(topActe.label) : '-'}</strong><small>${topActe ? moneyDashPwa(topActe.total) : '0 CDF'}</small></div>
                    <div><span>Total local global</span><strong>${moneyDashPwa(totalGlobal)}</strong><small>Toutes périodes confondues</small></div>
                </div>
            </section>

            <section class="dash-card">
                <h3>📊 Répartition par type de taxe</h3>
        `;

        if(byType.length){
            html += `<div class="chart-list">`;
            byType.forEach(row => {
                const pct = Math.max(4, Math.round((row.total / maxType) * 100));
                html += `
                    <div class="chart-row">
                        <div class="chart-label"><strong>${safeDash(row.label)}</strong><span>${row.count} opération(s) — ${moneyDashPwa(row.total)}</span></div>
                        <div class="bar-wrap"><div class="bar-fill" style="width:${pct}%"></div></div>
                    </div>
                `;
            });
            html += `</div>`;
        } else {
            html += `<p class="empty-text">Aucune recette enregistrée aujourd'hui.</p>`;
        }

        html += `
            </section>
            <section class="dash-card">
                <h3>🏷️ Top actes taxables</h3>
                <table class="premium-table">
                    <tr><th>Rang</th><th>Acte</th><th>Ops</th><th>Total</th></tr>
        `;

        byArticle.slice(0,10).forEach((row,idx)=>{
            html += `<tr><td>${idx+1}</td><td>${safeDash(row.label)}</td><td>${row.count}</td><td>${moneyDashPwa(row.total)}</td></tr>`;
        });
        if(!byArticle.length) html += `<tr><td colspan="4">Aucune donnée aujourd'hui.</td></tr>`;

        html += `
                </table>
            </section>
            <section class="dash-card">
                <h3>📋 Dernières taxations</h3>
                <table class="premium-table">
                    <tr><th>Heure</th><th>Assujetti</th><th>Montant</th><th>Sync</th></tr>
        `;

        [...all].sort((a,b)=>new Date(b.created_at || 0)-new Date(a.created_at || 0)).slice(0,8).forEach(row => {
            html += `<tr><td>${safeDateTimeDash(row.created_at)}</td><td>${safeDash(row.contribuable_nom)}</td><td>${moneyDashPwa(row.montant_cdf)}</td><td>${row.sync ? '✅' : '⏳'}</td></tr>`;
        });
        if(!all.length) html += `<tr><td colspan="4">Aucune taxation enregistrée.</td></tr>`;

        html += `</table></section>`;
        box.innerHTML = html;

    }catch(e){
        console.error('Erreur dashboard:', e);
        box.innerHTML = '<div class="dash-card"><p style="color:red;font-weight:bold">Erreur dashboard : ' + safeDash(e.message || e) + '</p></div>';
    }
}

window.renderDashboard = renderDashboard;
