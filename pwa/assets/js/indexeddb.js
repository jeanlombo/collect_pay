/*
|--------------------------------------------------------------------------
| cOllect_Pay Mobile - IndexedDB
| Version : v160
|--------------------------------------------------------------------------
*/
const DB_NAME = 'collect_pay_offline_db';
const DB_VERSION = 4;

const STORE_TAXATIONS = 'taxations';
const STORE_USERS = 'offline_users';
const STORE_SESSION = 'offline_session';
const STORE_TICKETS = 'tickets';

function openDB() {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open(DB_NAME, DB_VERSION);

        req.onupgradeneeded = event => {
            const db = event.target.result;

            if (!db.objectStoreNames.contains(STORE_TAXATIONS)) {
                const store = db.createObjectStore(STORE_TAXATIONS, { keyPath: 'local_id' });
                store.createIndex('sync', 'sync', { unique: false });
                store.createIndex('created_at', 'created_at', { unique: false });
                store.createIndex('type_taxe', 'type_taxe', { unique: false });
                store.createIndex('article_id', 'article_id', { unique: false });
            }

            if (!db.objectStoreNames.contains(STORE_USERS)) {
                const userStore = db.createObjectStore(STORE_USERS, { keyPath: 'email' });
                userStore.createIndex('user_id', 'user_id', { unique: false });
                userStore.createIndex('role', 'role', { unique: false });
            }

            if (!db.objectStoreNames.contains(STORE_SESSION)) {
                db.createObjectStore(STORE_SESSION, { keyPath: 'id' });
            }

            if (!db.objectStoreNames.contains(STORE_TICKETS)) {
                const ticketStore = db.createObjectStore(STORE_TICKETS, { keyPath: 'ticket_id' });
                ticketStore.createIndex('local_id', 'local_id', { unique: false });
                ticketStore.createIndex('created_at', 'created_at', { unique: false });
                ticketStore.createIndex('sync', 'sync', { unique: false });
            }
        };

        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
        req.onblocked = () => console.warn('IndexedDB bloquée : fermez les autres onglets de la PWA.');
    });
}

/* TAXATIONS */
async function addTaxation(data) {
    const db = await openDB();

    if (!data.local_id) {
        data.local_id = 'LOCAL-' + Date.now() + '-' + Math.floor(Math.random() * 999999);
    }

    if (!data.created_at) data.created_at = new Date().toISOString();
    if (data.sync === undefined) data.sync = false;

    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE_TAXATIONS, 'readwrite');
        tx.objectStore(STORE_TAXATIONS).put(data);
        tx.oncomplete = () => resolve(true);
        tx.onerror = () => reject(tx.error);
    });
}

async function getAllTaxations() {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE_TAXATIONS, 'readonly');
        const req = tx.objectStore(STORE_TAXATIONS).getAll();
        req.onsuccess = () => resolve(req.result || []);
        req.onerror = () => reject(req.error);
    });
}

async function getPendingTaxations() {
    const all = await getAllTaxations();
    return all.filter(item => item.sync !== true);
}

async function markTaxationSynced(local_id, numero_nt = null) {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE_TAXATIONS, 'readwrite');
        const store = tx.objectStore(STORE_TAXATIONS);
        const req = store.get(local_id);

        req.onsuccess = () => {
            const item = req.result;
            if (item) {
                item.sync = true;
                item.numero_nt = numero_nt;
                item.synced_at = new Date().toISOString();
                store.put(item);
            }
        };

        tx.oncomplete = () => resolve(true);
        tx.onerror = () => reject(tx.error);
    });
}

async function deleteTaxation(local_id) {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE_TAXATIONS, 'readwrite');
        tx.objectStore(STORE_TAXATIONS).delete(local_id);
        tx.oncomplete = () => resolve(true);
        tx.onerror = () => reject(tx.error);
    });
}

/* USERS */
async function saveOfflineUserDB(user) {
    const db = await openDB();
    if (!user.email) throw new Error('Email utilisateur manquant.');
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE_USERS, 'readwrite');
        tx.objectStore(STORE_USERS).put(user);
        tx.oncomplete = () => resolve(true);
        tx.onerror = () => reject(tx.error);
    });
}

async function getOfflineUserByEmailDB(email) {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE_USERS, 'readonly');
        const req = tx.objectStore(STORE_USERS).get(email);
        req.onsuccess = () => resolve(req.result || null);
        req.onerror = () => reject(req.error);
    });
}

async function getAllOfflineUsersDB() {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE_USERS, 'readonly');
        const req = tx.objectStore(STORE_USERS).getAll();
        req.onsuccess = () => resolve(req.result || []);
        req.onerror = () => reject(req.error);
    });
}

/* SESSION */
async function saveOfflineSessionDB(session) {
    const db = await openDB();
    session.id = 'current';
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE_SESSION, 'readwrite');
        tx.objectStore(STORE_SESSION).put(session);
        tx.oncomplete = () => resolve(true);
        tx.onerror = () => reject(tx.error);
    });
}

async function getOfflineSessionDB() {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE_SESSION, 'readonly');
        const req = tx.objectStore(STORE_SESSION).get('current');
        req.onsuccess = () => resolve(req.result || null);
        req.onerror = () => reject(req.error);
    });
}

async function clearOfflineSessionDB() {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE_SESSION, 'readwrite');
        tx.objectStore(STORE_SESSION).delete('current');
        tx.oncomplete = () => resolve(true);
        tx.onerror = () => reject(tx.error);
    });
}

/* TICKETS */
async function addTicket(ticket) {
    const db = await openDB();

    if (!ticket.ticket_id) {
        ticket.ticket_id = 'TCK-' + Date.now() + '-' + Math.floor(Math.random() * 999999);
    }

    if (!ticket.created_at) ticket.created_at = new Date().toISOString();

    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE_TICKETS, 'readwrite');
        tx.objectStore(STORE_TICKETS).put(ticket);
        tx.oncomplete = () => resolve(true);
        tx.onerror = () => reject(tx.error);
    });
}

async function getAllTickets() {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE_TICKETS, 'readonly');
        const req = tx.objectStore(STORE_TICKETS).getAll();
        req.onsuccess = () => resolve(req.result || []);
        req.onerror = () => reject(req.error);
    });
}

async function getTicketById(ticket_id) {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE_TICKETS, 'readonly');
        const req = tx.objectStore(STORE_TICKETS).get(ticket_id);
        req.onsuccess = () => resolve(req.result || null);
        req.onerror = () => reject(req.error);
    });
}

async function deleteTicket(ticket_id) {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE_TICKETS, 'readwrite');
        tx.objectStore(STORE_TICKETS).delete(ticket_id);
        tx.oncomplete = () => resolve(true);
        tx.onerror = () => reject(tx.error);
    });
}

/* Compatibilité globale */
window.openDB = openDB;
window.addTaxation = addTaxation;
window.getAllTaxations = getAllTaxations;
window.getPendingTaxations = getPendingTaxations;
window.markTaxationSynced = markTaxationSynced;
window.deleteTaxation = deleteTaxation;

window.saveOfflineUserDB = saveOfflineUserDB;
window.getOfflineUserByEmailDB = getOfflineUserByEmailDB;
window.getAllOfflineUsersDB = getAllOfflineUsersDB;

window.saveOfflineSessionDB = saveOfflineSessionDB;
window.getOfflineSessionDB = getOfflineSessionDB;
window.clearOfflineSessionDB = clearOfflineSessionDB;

window.addTicket = addTicket;
window.getAllTickets = getAllTickets;
window.getTicketById = getTicketById;
window.deleteTicket = deleteTicket;
