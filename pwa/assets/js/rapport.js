document.addEventListener('DOMContentLoaded', async () => {
    const today = new Date().toISOString().slice(0, 10);

    const debut = document.getElementById('date_debut');
    const fin = document.getElementById('date_fin');

    if (debut) debut.value = today;
    if (fin) fin.value = today;

    await generateReportZ();
});

function moneyRZ(v) {
    return Number(v || 0).toLocaleString('fr-FR') + ' CDF';
}

function dateOnlyRZ(iso) {
    if (!iso) return '';
    return String(iso).slice(0, 10);
}

function safeRZ(v) {
    return String(v || '-').replace(/[<>]/g, '').trim();
}

async function getAgentRZ() {
    try {
        if (typeof getCurrentAgent === 'function') {
            const agent = await getCurrentAgent();
            if (agent) return agent;
        }
    } catch(e) {}
    return null;
}

function groupSum(rows, keyGetter, labelGetter) {
    const map = {};

    rows.forEach(row => {
        const key = keyGetter(row) || 'autre';
        const label = labelGetter(row) || key;

        if (!map[key]) {
            map[key] = { key, label, count: 0, amount: 0 };
        }

        map[key].count++;
        map[key].amount += Number(row.montant_cdf || 0);
    });

    return Object.values(map).sort((a, b) => b.amount - a.amount);
}

async function generateReportZ() {
    const box = document.getElementById('reportContent');
    if (!box) return;

    box.innerHTML = 'Chargement du rapport...';

    try {
        const debut = document.getElementById('date_debut')?.value || new Date().toISOString().slice(0,10);
        const fin = document.getElementById('date_fin')?.value || debut;

        const allTaxations = typeof getAllTaxations === 'function' ? await getAllTaxations() : [];
        const allTickets = typeof getAllTickets === 'function' ? await getAllTickets() : [];

        const rows = allTaxations.filter(t => {
            const d = dateOnlyRZ(t.created_at);
            return d >= debut && d <= fin;
        });

        const tickets = allTickets.filter(t => {
            const d = dateOnlyRZ(t.created_at);
            return d >= debut && d <= fin;
        });

        const totalOps = rows.length;
        const totalTickets = tickets.length;
        const totalAmount = rows.reduce((s, t) => s + Number(t.montant_cdf || 0), 0);
        const synced = rows.filter(t => t.sync === true).length;
        const pending = rows.filter(t => t.sync !== true).length;

        const agent = await getAgentRZ();

        const reportNo = 'RZ-' + debut.replaceAll('-', '') + '-' + Math.floor(Math.random() * 99999);

        const byType = groupSum(
            rows,
            row => row.type_taxe || 'autre',
            row => {
                if (row.type_taxe === 'chargement') return 'Taxe de chargement';
                if (row.type_taxe === 'dechargement') return 'Taxe de déchargement';
                if (row.type_taxe === 'peage') return 'Taxe de péage';
                return row.type_taxe || 'Autres taxes';
            }
        );

        const byArticle = groupSum(
            rows,
            row => row.article_id || 'sans_article',
            row => 'Article #' + (row.article_id || '-')
        );

        let html = `
            <div style="text-align:center">
                <h2>RAPPORT Z DETAILLE</h2>
                <strong>cOllect_Pay Mobile</strong><br>
                <small>Numéro rapport : ${reportNo}</small><br>
                <small>Généré le : ${new Date().toLocaleString('fr-FR')}</small>
            </div>

            <hr>

            <p>
                <strong>Période :</strong> ${debut} au ${fin}<br>
                <strong>Agent :</strong> ${safeRZ(agent?.nom || 'Agent local')}<br>
                <strong>Rôle :</strong> ${safeRZ(agent?.role || '-')}<br>
                <strong>Centre :</strong> ${safeRZ(agent?.centre_id || '-')}<br>
                <strong>Service :</strong> ${safeRZ(agent?.service_id || '-')}
            </p>

            <div class="kpi-grid-report">
                <div class="kpi-report"><span>Nombre taxations</span><strong>${totalOps}</strong></div>
                <div class="kpi-report"><span>Nombre tickets</span><strong>${totalTickets}</strong></div>
                <div class="kpi-report"><span>Montant total</span><strong>${moneyRZ(totalAmount)}</strong></div>
                <div class="kpi-report"><span>Non synchronisées</span><strong>${pending}</strong></div>
            </div>

            <h3>Résumé synchronisation</h3>
            <table class="table-report">
                <tr><th>Statut</th><th>Nombre</th></tr>
                <tr><td>Synchronisées</td><td>${synced}</td></tr>
                <tr><td>En attente</td><td>${pending}</td></tr>
            </table>

            <h3>Total par type de taxe</h3>
            <table class="table-report">
                <tr><th>Type</th><th>Opérations</th><th>Total</th></tr>
        `;

        byType.forEach(row => {
            html += `<tr><td>${safeRZ(row.label)}</td><td>${row.count}</td><td>${moneyRZ(row.amount)}</td></tr>`;
        });

        if (!byType.length) {
            html += `<tr><td colspan="3">Aucune donnée.</td></tr>`;
        }

        html += `
            </table>

            <h3>Classement par acte taxable</h3>
            <table class="table-report">
                <tr><th>Rang</th><th>Acte</th><th>Opérations</th><th>Total</th></tr>
        `;

        byArticle.forEach((row, idx) => {
            html += `<tr><td>${idx + 1}</td><td>${safeRZ(row.label)}</td><td>${row.count}</td><td>${moneyRZ(row.amount)}</td></tr>`;
        });

        if (!byArticle.length) {
            html += `<tr><td colspan="4">Aucune donnée.</td></tr>`;
        }

        html += `
            </table>

            <h3>Détail des taxations</h3>
            <table class="table-report">
                <tr><th>Date</th><th>Assujetti</th><th>Taxe</th><th>Montant</th><th>Sync</th></tr>
        `;

        rows.sort((a, b) => new Date(b.created_at) - new Date(a.created_at)).forEach(row => {
            html += `
                <tr>
                    <td>${new Date(row.created_at).toLocaleString('fr-FR')}</td>
                    <td>${safeRZ(row.contribuable_nom)}</td>
                    <td>${safeRZ(row.type_taxe)}</td>
                    <td>${moneyRZ(row.montant_cdf)}</td>
                    <td>${row.sync ? 'Oui' : 'Non'}</td>
                </tr>
            `;
        });

        if (!rows.length) {
            html += `<tr><td colspan="5">Aucune taxation sur cette période.</td></tr>`;
        }

        html += `
            </table>

            <br><br>

            <table style="width:100%;font-size:13px">
                <tr>
                    <td style="text-align:center">Signature Agent<br><br><br>____________________</td>
                    <td style="text-align:center">Contrôle / Chef Poste<br><br><br>____________________</td>
                </tr>
            </table>

            <br>

            <p style="text-align:center">
                <strong>cOllect_Pay Mobile</strong><br>
                Rapport généré localement avant/après synchronisation.
            </p>
        `;

        box.innerHTML = html;

        window.__LAST_REPORT_Z__ = {
            reportNo, debut, fin, agent, totalOps, totalTickets,
            totalAmount, synced, pending, byType, byArticle, rows
        };

    } catch (e) {
        box.innerHTML = '<p style="color:red;font-weight:bold">Erreur rapport : ' + e.message + '</p>';
    }
}

async function exportReportZJson() {
    if (!window.__LAST_REPORT_Z__) {
        await generateReportZ();
    }

    const data = window.__LAST_REPORT_Z__ || {};
    const name = (data.reportNo || 'rapport_z') + '.json';

    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);

    const a = document.createElement('a');
    a.href = url;
    a.download = name;
    a.click();

    URL.revokeObjectURL(url);
}
