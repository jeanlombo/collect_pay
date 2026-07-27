/*
|--------------------------------------------------------------------------
| cOllect_Pay Mobile - Ticket RawBT Premium 58mm
|--------------------------------------------------------------------------
| Compatible :
| - RawBT Android
| - OCPP-M15
| - PT-80B
| - Bluetooth Printer 58mm
|--------------------------------------------------------------------------
*/

function formatMoneyTicket(v) {
    return Number(v || 0).toLocaleString('fr-FR') + ' CDF';
}

function generateTicketId() {
    const d = new Date();
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    const h = String(d.getHours()).padStart(2, '0');
    const min = String(d.getMinutes()).padStart(2, '0');
    const s = String(d.getSeconds()).padStart(2, '0');

    return 'TCK-' + y + m + day + '-' + h + min + s;
}

function cleanTxt(v) {
    return String(v || '-')
        .replace(/[<>]/g, '')
        .replace(/\s+/g, ' ')
        .trim();
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

        agent_nom: agent ? (agent.nom || agent.full_name || agent.username || '-') : '-',
        agent_matricule: agent ? (agent.matricule || '-') : '-',
        service_id: taxation.service_id || (agent ? agent.service_id : null),
        centre_id: taxation.centre_id || (agent ? agent.centre_id : null),

        created_at: taxation.created_at || new Date().toISOString()
    };
}

async function createTicketForTaxation(taxation) {
    let agent = null;

    if (typeof getCurrentAgent === 'function') {
        agent = await getCurrentAgent();
    }

    const ticket = buildTicketFromTaxation(taxation, agent);

    if (typeof addTicket === 'function') {
        await addTicket(ticket);
    }

    return ticket;
}

/*
|--------------------------------------------------------------------------
| Ticket RawBT grand format
|--------------------------------------------------------------------------
| RawBT comprend :
| [C] = centré
| [L] = gauche
| <b>...</b> = gras
| <font size='big'>...</font> = grand texte
| <qrcode size='8'>...</qrcode> = QR Code
|--------------------------------------------------------------------------
*/
function buildRawBTTicket(t) {
    const date = new Date(t.created_at).toLocaleString('fr-FR');

    const ticketNo = cleanTxt(t.ticket_id);
    const localNo = cleanTxt(t.local_id);
    const ntNo = cleanTxt(t.numero_nt || 'NON SYNCHRONISE');

    const nom = cleanTxt(t.contribuable_nom);
    const tel = cleanTxt(t.telephone);
    const plaque = cleanTxt(t.plaque);
    const taxe = cleanTxt(t.type_taxe);
    const agent = cleanTxt(t.agent_nom);
    const matricule = cleanTxt(t.agent_matricule);
    const montant = formatMoneyTicket(t.montant_cdf);

    const qrData =
        'cOllect_Pay|' +
        ticketNo + '|' +
        localNo + '|' +
        ntNo + '|' +
        montant;

    return `
[C]<b><font size='big'>cOllect_Pay Mobile</font></b>
[C]<b>DGRT - Recettes Publiques</b>
[C]Taxation mobile offline
[C]================================
[C]<b><font size='big'>TICKET DE TAXATION</font></b>
[C]================================

[L]<b>N Ticket</b>  : ${ticketNo}
[L]<b>Date/Heure</b> : ${date}
[L]<b>Agent</b>     : ${agent}
[L]<b>Matricule</b> : ${matricule}

[C]--------------------------------
[C]<b>ASSUJETTI</b>
[C]--------------------------------
[L]<b>Nom</b>       : ${nom}
[L]<b>Telephone</b> : ${tel}
[L]<b>Plaque</b>    : ${plaque}

[C]--------------------------------
[C]<b>TAXE</b>
[C]--------------------------------
[L]<b>Type</b>      : ${taxe}
[L]<b>Quantite</b>  : ${t.quantite || 1}
[L]<b>Reference</b> : ${ntNo}

[C]================================
[C]<b>MONTANT</b>
[C]<b><font size='big'>${montant}</font></b>
[C]================================

[C]Merci pour votre contribution.
[C]La nation vous remercie.

[C]<qrcode size='8'>${qrData}</qrcode>
[C]<b>cOllect_Pay Mobile</b>
[C]Solution de taxation offline

[C]================================
[C]<b>MERCI ET BONNE ROUTE !</b>
[C]================================



`;
}

/*
|--------------------------------------------------------------------------
| Fallback texte simple
|--------------------------------------------------------------------------
*/
function buildTicketText(t) {
    const date = new Date(t.created_at).toLocaleString('fr-FR');

    return (
`================================
        cOllect_Pay Mobile
================================
       TICKET DE TAXATION
================================

N Ticket : ${t.ticket_id}
Date     : ${date}
Agent    : ${t.agent_nom || '-'}

--------------------------------
ASSUJETTI
--------------------------------
Nom      : ${t.contribuable_nom || '-'}
Tel      : ${t.telephone || '-'}
Plaque   : ${t.plaque || '-'}

--------------------------------
TAXE
--------------------------------
Type     : ${t.type_taxe || '-'}
Qte      : ${t.quantite || 1}
Ref      : ${t.numero_nt || 'NON SYNCHRONISE'}

================================
MONTANT  : ${formatMoneyTicket(t.montant_cdf)}
================================

Merci pour votre contribution.
La nation vous remercie.

cOllect_Pay Mobile
MERCI ET BONNE ROUTE !


`
    );
}

/*
|--------------------------------------------------------------------------
| Impression RawBT
|--------------------------------------------------------------------------
*/
function printViaRawBT(ticket) {
    try {
        const rawbtText = buildRawBTTicket(ticket);

        const base64 = btoa(
            unescape(
                encodeURIComponent(rawbtText)
            )
        );

        window.location.href = 'rawbt:base64,' + base64;

    } catch (e) {
        alert('Erreur RawBT : ' + e.message);
    }
}

/*
|--------------------------------------------------------------------------
| Impression navigateur classique
|--------------------------------------------------------------------------
*/
function buildTicketHtml(t) {
    const safeText = buildTicketText(t)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');

    return `
        <div style="
            font-family:monospace;
            width:285px;
            padding:8px;
            font-size:14px;
            line-height:1.35;
            white-space:pre-wrap;
        ">
${safeText}
        </div>
    `;
}

function printTicketBrowser(t) {
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

/*
|--------------------------------------------------------------------------
| Impression principale
|--------------------------------------------------------------------------
*/
function printTicketObject(t) {
    const choix = confirm(
        'Imprimer via RawBT Bluetooth ?\n\nOK = RawBT Bluetooth\nAnnuler = Impression navigateur'
    );

    if (choix) {
        printViaRawBT(t);
    } else {
        printTicketBrowser(t);
    }
}

/*
|--------------------------------------------------------------------------
| Imprimer dernier ticket
|--------------------------------------------------------------------------
*/
async function printLastTicket() {
    if (typeof getAllTickets !== 'function') {
        alert('Module tickets non chargé.');
        return;
    }

    const tickets = await getAllTickets();

    if (!tickets.length) {
        alert('Aucun ticket à imprimer.');
        return;
    }

    tickets.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));

    printTicketObject(tickets[0]);
}

/*
|--------------------------------------------------------------------------
| Voir ticket
|--------------------------------------------------------------------------
*/
function viewTicketObject(t) {
    const w = window.open('', '_blank', 'width=360,height=650');

    w.document.write(`
        <html>
        <head>
            <title>${t.ticket_id}</title>
        </head>
        <body>
            ${buildTicketHtml(t)}
            <br>
            <button onclick="window.print()">Imprimer navigateur</button>
        </body>
        </html>
    `);

    w.document.close();
}
