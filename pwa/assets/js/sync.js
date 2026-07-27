/*
|--------------------------------------------------------------------------
| cOllect_Pay Mobile - Synchronisation PWA
|--------------------------------------------------------------------------
*/
let syncInProgress = false;

async function syncTaxations(autoMode = false) {
    if (syncInProgress) return;

    if (!navigator.onLine) {
        if (!autoMode) alert('Connexion absente. Réessayez quand Internet revient.');
        return;
    }

    const pending = await getPendingTaxations();

    if (!pending.length) {
        if (!autoMode) alert('Aucune taxation à synchroniser.');
        return;
    }

    syncInProgress = true;

    const total = pending.length;
    const syncedItems = [];

    if (typeof setProgress === 'function') {
        setProgress('syncProgressBox','syncProgressBar','syncProgressText',5,
            autoMode ? 'Connexion retrouvée. Synchronisation automatique...' : 'Préparation de la synchronisation...'
        );
    }

    try {
        for (let i = 0; i < pending.length; i++) {
            const original = pending[i];

            const item = {
                local_id: original.local_id,
                contribuable_nom: original.contribuable_nom || '',
                telephone: original.telephone || '',
                plaque: original.plaque || '',
                type_taxe: original.type_taxe || '',
                article_id: original.article_id || null,
                base_imposable: original.base_imposable || 0,
                quantite: original.quantite || 1,
                montant_cdf: original.montant_cdf || 0,
                gps_lat: original.gps_lat || null,
                gps_lng: original.gps_lng || null,
                agent_id: original.agent_id || null,
                centre_id: original.centre_id || null,
                service_id: original.service_id || null,
                created_at: original.created_at || new Date().toISOString()
            };

            if (typeof setProgress === 'function') {
                setProgress(
                    'syncProgressBox',
                    'syncProgressBar',
                    'syncProgressText',
                    Math.round((i / total) * 100),
                    'Synchronisation ' + (i + 1) + ' / ' + total + '...'
                );
            }

            const response = await fetch('/collect_pay/api/sync_taxations.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ taxations: [item] })
            });

            const text = await response.text();
            let result;

            try {
                result = JSON.parse(text);
            } catch (e) {
                throw new Error('Réponse serveur invalide : ' + text.substring(0, 200));
            }

            if (!result.success) {
                throw new Error(result.message || 'Erreur de synchronisation.');
            }

            for (const row of result.items || []) {
                await markTaxationSynced(row.local_id, row.numero_nt || null);
                syncedItems.push(row);
            }

            if (typeof setProgress === 'function') {
                setProgress(
                    'syncProgressBox',
                    'syncProgressBar',
                    'syncProgressText',
                    Math.round(((i + 1) / total) * 100),
                    'Synchronisation ' + (i + 1) + ' / ' + total + ' terminée.'
                );
            }
        }

        if (typeof setProgress === 'function') {
            setProgress(
                'syncProgressBox',
                'syncProgressBar',
                'syncProgressText',
                100,
                'Synchronisation réussie : ' + syncedItems.length + ' taxation(s).'
            );
        }

        if (typeof renderPendingList === 'function') {
            await renderPendingList();
        }

        if (!autoMode) {
            alert('Synchronisation réussie avec succès : ' + syncedItems.length + ' taxation(s).');
        } else {
            showLocalNotice('✅ Synchronisation automatique réussie : ' + syncedItems.length + ' taxation(s).');
        }

        if (typeof hideProgress === 'function') {
            hideProgress('syncProgressBox');
        }

    } catch (error) {
        if (!autoMode) {
            alert('Erreur synchronisation : ' + error.message);
        } else {
            showLocalNotice('⚠️ Synchronisation automatique échouée : ' + error.message);
        }
    } finally {
        syncInProgress = false;
    }
}

window.addEventListener('online', async () => {
    showLocalNotice('📶 Connexion retrouvée. Vérification des taxations en attente...');

    setTimeout(async () => {
        await syncTaxations(true);
    }, 1500);
});

function showLocalNotice(message) {
    let box = document.getElementById('localNoticeBox');

    if (!box) {
        box = document.createElement('div');
        box.id = 'localNoticeBox';
        box.style.position = 'fixed';
        box.style.left = '12px';
        box.style.right = '12px';
        box.style.bottom = '16px';
        box.style.zIndex = '9999';
        box.style.background = '#0f3460';
        box.style.color = '#fff';
        box.style.padding = '12px';
        box.style.borderRadius = '14px';
        box.style.fontWeight = '800';
        box.style.boxShadow = '0 10px 30px rgba(0,0,0,.25)';
        document.body.appendChild(box);
    }

    box.textContent = message;
    box.style.display = 'block';

    setTimeout(() => {
        box.style.display = 'none';
    }, 4500);
}
