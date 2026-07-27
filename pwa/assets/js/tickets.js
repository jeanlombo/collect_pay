document.addEventListener('DOMContentLoaded', renderTickets);

function moneyTickets(v) {
    return Number(v || 0).toLocaleString('fr-FR') + ' CDF';
}

async function renderTickets() {
    const box = document.getElementById('ticketsList');
    const q = (document.getElementById('searchTicket')?.value || '').toLowerCase();

    let tickets = await getAllTickets();
    tickets.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));

    if (q) {
        tickets = tickets.filter(t => {
            const txt = (
                (t.ticket_id || '') + ' ' +
                (t.local_id || '') + ' ' +
                (t.contribuable_nom || '') + ' ' +
                (t.plaque || '') + ' ' +
                (t.type_taxe || '')
            ).toLowerCase();

            return txt.includes(q);
        });
    }

    if (!tickets.length) {
        box.innerHTML = '<div class="ticket-card">Aucun ticket trouvé.</div>';
        return;
    }

    box.innerHTML = tickets.map(t => `
        <div class="ticket-card">
            <strong>${t.ticket_id}</strong><br>
            <span class="small">${new Date(t.created_at).toLocaleString('fr-FR')}</span><br><br>

            Assujetti : <strong>${t.contribuable_nom || '-'}</strong><br>
            Plaque : ${t.plaque || '-'}<br>
            Taxe : ${t.type_taxe || '-'}<br>
            Montant : <strong>${moneyTickets(t.montant_cdf)}</strong><br>
            Statut : ${t.sync ? '✅ Synchronisé' : '⏳ Non synchronisé'}

            <div class="ticket-actions">
                <button class="btn-primary" onclick="viewTicket('${t.ticket_id}')">👁 Voir</button>
                <button class="btn-green" onclick="reprintTicket('${t.ticket_id}')">🖨 Réimprimer</button>
                <button class="btn-red" onclick="removeTicket('${t.ticket_id}')">🗑 Supprimer</button>
            </div>
        </div>
    `).join('');
}

async function viewTicket(ticket_id) {
    const t = await getTicketById(ticket_id);

    if (!t) {
        alert('Ticket introuvable.');
        return;
    }

    const w = window.open('', '_blank', 'width=360,height=650');
    w.document.write(`
        <html>
        <head>
            <title>${t.ticket_id}</title>
        </head>
        <body>
            ${buildTicketHtml(t)}
            <br>
            <button onclick="window.print()">Imprimer</button>
        </body>
        </html>
    `);
    w.document.close();
}

async function reprintTicket(ticket_id) {
    const t = await getTicketById(ticket_id);

    if (!t) {
        alert('Ticket introuvable.');
        return;
    }

    printTicketObject(t);
}

async function removeTicket(ticket_id) {
    if (!confirm('Supprimer ce ticket localement ?')) {
        return;
    }

    await deleteTicket(ticket_id);
    renderTickets();
}
