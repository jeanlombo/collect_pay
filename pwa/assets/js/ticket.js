function formatMoneyTicket(v) {
    return Number(v || 0).toLocaleString('fr-FR') + ' CDF';
}

function generateTicketId() {
    const d = new Date();
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return 'TCK-' + y + m + day + '-' + Math.floor(Date.now() / 1000);
}

function buildTicketFromTaxation(taxation, agent = null) {
    return {
        ticket_id: generateTicketId(),
        local_id: taxation.local_id,
        numero_nt: taxation.numero_nt || null,
        contribuable_nom: taxation.contribuable_nom || '-',
        telephone: taxation.telephone || '-',
        plaque: taxation.plaque || '-',
        type_taxe: taxation.type_taxe || '-',
        article_id: taxation.article_id || null,
        quantite: taxation.quantite || 1,
        montant_cdf: taxation.montant_cdf || 0,
        sync: taxation.sync === true,
        agent_nom: agent ? (agent.nom || '-') : '-',
        created_at: taxation.created_at || new Date().toISOString()
    };
}

async function createTicketForTaxation(taxation) {
    let agent = null;

    if (typeof getCurrentAgent === 'function') {
        agent = await getCurrentAgent();
    }

    const ticket = buildTicketFromTaxation(taxation, agent);
    await addTicket(ticket);

    return ticket;
}

function buildTicketHtml(t) {
    return `
        <div style="font-family:monospace;width:285px;padding:8px;font-size:12px">
            <div style="text-align:center">
                <strong>cOllect_Pay</strong><br>
                TICKET DE TAXATION<br>
                ------------------------------<br>
            </div>

            N° Ticket : ${t.ticket_id}<br>
            N° Local : ${t.local_id}<br>
            N° NT : ${t.numero_nt || 'NON SYNCHRONISÉ'}<br>
            Date : ${new Date(t.created_at).toLocaleString('fr-FR')}<br>
            Agent : ${t.agent_nom || '-'}<br>
            ------------------------------<br>
            Assujetti : ${t.contribuable_nom || '-'}<br>
            Téléphone : ${t.telephone || '-'}<br>
            Plaque : ${t.plaque || '-'}<br>
            Taxe : ${t.type_taxe || '-'}<br>
            Quantité : ${t.quantite || 1}<br>
            ------------------------------<br>
            MONTANT : ${formatMoneyTicket(t.montant_cdf)}<br>
            ------------------------------<br>
            Statut : ${t.sync ? 'Synchronisé' : 'Non synchronisé'}<br>
            Merci.<br>
        </div>
    `;
}

function printTicketObject(t) {
    const w = window.open('', '_blank', 'width=340,height=650');
    w.document.write(`
        <html>
        <head>
            <title>Ticket ${t.ticket_id}</title>
        </head>
        <body onload="window.print();setTimeout(()=>window.close(),900)">
            ${buildTicketHtml(t)}
        </body>
        </html>
    `);
    w.document.close();
}

async function printLastTicket() {
    const tickets = await getAllTickets();

    if (!tickets.length) {
        alert('Aucun ticket à imprimer.');
        return;
    }

    tickets.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
    printTicketObject(tickets[0]);
}
