async function syncTaxations() {
    if (!navigator.onLine) {
        alert('Connexion absente. Réessayez quand Internet revient.');
        return;
    }

    const pending = await getPendingTaxations();

    if (!pending.length) {
        alert('Aucune taxation à synchroniser.');
        return;
    }

    const total = pending.length;
    const syncedItems = [];

    setProgress(
        'syncProgressBox',
        'syncProgressBar',
        'syncProgressText',
        5,
        'Préparation de la synchronisation...'
    );

    try {
        for (let i = 0; i < pending.length; i++) {

            const original = pending[i];

            /*
            |--------------------------------------------------------------------------
            | IMPORTANT :
            | On crée une copie légère avant l'envoi au serveur.
            | Cela évite d'envoyer les grosses photos/signatures Base64.
            |--------------------------------------------------------------------------
            */
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

            const percentStart = Math.round((i / total) * 100);

            setProgress(
                'syncProgressBox',
                'syncProgressBar',
                'syncProgressText',
                percentStart,
                'Synchronisation ' + (i + 1) + ' / ' + total + '...'
            );

            const response = await fetch('/collect_pay/api/sync_taxations.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    taxations: [item]
                })
            });

            const text = await response.text();

            let result;

            try {
                result = JSON.parse(text);
            } catch (e) {
                alert('Réponse serveur invalide : ' + text.substring(0, 250));
                return;
            }

            if (!result.success) {
                alert(result.message || 'Erreur de synchronisation.');
                return;
            }

            for (const row of result.items || []) {
                await markTaxationSynced(row.local_id, row.numero_nt || null);
                syncedItems.push(row);
            }

            const percentEnd = Math.round(((i + 1) / total) * 100);

            setProgress(
                'syncProgressBox',
                'syncProgressBar',
                'syncProgressText',
                percentEnd,
                'Synchronisation ' + (i + 1) + ' / ' + total + ' terminée.'
            );
        }

        setProgress(
            'syncProgressBox',
            'syncProgressBar',
            'syncProgressText',
            100,
            'Synchronisation réussie avec succès : ' + syncedItems.length + ' taxation(s).'
        );

        alert('Synchronisation réussie avec succès : ' + syncedItems.length + ' taxation(s).');

        renderPendingList();
        hideProgress('syncProgressBox');

    } catch (error) {
        alert('Erreur réseau/API : ' + error.message);
    }
}
