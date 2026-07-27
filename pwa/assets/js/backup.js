/*
|--------------------------------------------------------------------------
| cOllect_Pay Mobile - Sauvegarde / Restauration / Nettoyage local
|--------------------------------------------------------------------------
*/
document.addEventListener('DOMContentLoaded', renderLocalStats);

async function getLocalBackupData() {
    const taxations = typeof getAllTaxations === 'function' ? await getAllTaxations() : [];
    const tickets = typeof getAllTickets === 'function' ? await getAllTickets() : [];
    const session = typeof getCurrentAgent === 'function' ? await getCurrentAgent() : null;
    const articles = JSON.parse(localStorage.getItem('collect_pay_articles_offline') || '[]');
    const lastImport = localStorage.getItem('collect_pay_last_import') || null;

    return {
        app: 'cOllect_Pay Mobile',
        version: '1.0',
        exported_at: new Date().toISOString(),
        session,
        articles,
        lastImport,
        taxations,
        tickets
    };
}

async function exportLocalBackup() {
    const data = await getLocalBackupData();

    const filename =
        'collect_pay_backup_' +
        new Date().toISOString().slice(0,19).replace(/[:T]/g, '-') +
        '.json';

    const blob = new Blob([JSON.stringify(data, null, 2)], {type: 'application/json'});
    const url = URL.createObjectURL(blob);

    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();

    URL.revokeObjectURL(url);

    alert('Sauvegarde exportée avec succès.');
}

async function restoreLocalBackup() {
    const input = document.getElementById('backupFile');

    if (!input || !input.files || !input.files.length) {
        alert('Veuillez choisir un fichier JSON.');
        return;
    }

    const file = input.files[0];
    const text = await file.text();

    let data;

    try {
        data = JSON.parse(text);
    } catch(e) {
        alert('Fichier JSON invalide.');
        return;
    }

    if (!data || data.app !== 'cOllect_Pay Mobile') {
        if (!confirm('Ce fichier ne semble pas être une sauvegarde cOllect_Pay. Continuer ?')) {
            return;
        }
    }

    if (Array.isArray(data.articles)) {
        localStorage.setItem('collect_pay_articles_offline', JSON.stringify(data.articles));
    }

    if (data.lastImport) {
        localStorage.setItem('collect_pay_last_import', data.lastImport);
    }

    if (data.session) {
        localStorage.setItem('collect_pay_agent_session', JSON.stringify(data.session));

        if (typeof saveOfflineSessionDB === 'function') {
            await saveOfflineSessionDB(data.session);
        }
    }

    if (Array.isArray(data.taxations)) {
        for (const t of data.taxations) {
            if (t && t.local_id && typeof addTaxation === 'function') {
                await addTaxation(t);
            }
        }
    }

    if (Array.isArray(data.tickets)) {
        for (const t of data.tickets) {
            if (t && t.ticket_id && typeof addTicket === 'function') {
                await addTicket(t);
            }
        }
    }

    alert('Restauration terminée avec succès.');
    await renderLocalStats();
}

async function cleanupSyncedTaxations() {
    if (!confirm('Supprimer uniquement les taxations déjà synchronisées ?')) return;

    if (typeof deleteTaxation !== 'function') {
        alert('La fonction deleteTaxation() est absente dans indexeddb.js.');
        return;
    }

    const all = typeof getAllTaxations === 'function' ? await getAllTaxations() : [];
    const synced = all.filter(t => t.sync === true);

    for (const item of synced) {
        await deleteTaxation(item.local_id);
    }

    alert(synced.length + ' taxation(s) synchronisée(s) supprimée(s).');

    await renderLocalStats();
}

async function renderLocalStats() {
    const box = document.getElementById('localStats');
    if (!box) return;

    const taxations = typeof getAllTaxations === 'function' ? await getAllTaxations() : [];
    const tickets = typeof getAllTickets === 'function' ? await getAllTickets() : [];
    const articles = JSON.parse(localStorage.getItem('collect_pay_articles_offline') || '[]');

    const synced = taxations.filter(t => t.sync === true).length;
    const pending = taxations.filter(t => t.sync !== true).length;
    const total = taxations.reduce((s, t) => s + Number(t.montant_cdf || 0), 0);

    box.innerHTML = `
        <p>
            Articles importés : <strong>${articles.length}</strong><br>
            Taxations locales : <strong>${taxations.length}</strong><br>
            Synchronisées : <strong>${synced}</strong><br>
            En attente : <strong>${pending}</strong><br>
            Tickets : <strong>${tickets.length}</strong><br>
            Montant local : <strong>${Number(total).toLocaleString('fr-FR')} CDF</strong>
        </p>
    `;
}
