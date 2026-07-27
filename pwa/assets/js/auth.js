/*
|--------------------------------------------------------------------------
| cOllect_Pay Mobile - Auth PWA v133
|--------------------------------------------------------------------------
*/

function cpIsLocalCollectPayAuth() {
    return window.location.pathname.indexOf('/collect_pay/') === 0;
}

function cpPwaBaseUrl() {
    return cpIsLocalCollectPayAuth() ? '/collect_pay/pwa' : '/pwa';
}

async function simpleHash(text) {
    text = String(text || '');

    if (window.crypto && window.crypto.subtle && typeof TextEncoder !== 'undefined') {
        const encoder = new TextEncoder();
        const data = encoder.encode(text);
        const hashBuffer = await window.crypto.subtle.digest('SHA-256', data);
        const hashArray = Array.from(new Uint8Array(hashBuffer));

        return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
    }

    return btoa(unescape(encodeURIComponent(text)));
}

async function saveOfflineUser(user, passwordPlain) {
    const password_hash = await simpleHash(passwordPlain);

    const offlineUser = {
        user_id: user.user_id,
        nom: user.nom || '',
        email: user.email || '',
        role: user.role || '',
        centre_id: user.centre_id || null,
        service_id: user.service_id || null,
        province_id: user.province_id || null,
        matricule: user.matricule || '',
        password_hash: password_hash,
        last_sync: new Date().toISOString()
    };

    await saveOfflineUserDB(offlineUser);

    await saveOfflineSessionDB({
        user_id: offlineUser.user_id,
        nom: offlineUser.nom,
        email: offlineUser.email,
        role: offlineUser.role,
        centre_id: offlineUser.centre_id,
        service_id: offlineUser.service_id,
        province_id: offlineUser.province_id,
        matricule: offlineUser.matricule,
        date_login: new Date().toISOString()
    });

    localStorage.setItem('collect_pay_agent_session', JSON.stringify(offlineUser));

    return offlineUser;
}

async function getCurrentAgent() {
    try {
        if (typeof getOfflineSessionDB === 'function') {
            const session = await getOfflineSessionDB();
            if (session) return session;
        }

        return JSON.parse(localStorage.getItem('collect_pay_agent_session') || 'null');
    } catch (e) {
        return null;
    }
}

async function requireOfflineLogin() {
    const agent = await getCurrentAgent();

    if (!agent) {
        window.location.href = cpPwaBaseUrl() + '/login.html?v=133';
        return false;
    }

    return true;
}

async function logoutAgent() {
    if (typeof clearOfflineSessionDB === 'function') {
        await clearOfflineSessionDB();
    }

    localStorage.removeItem('collect_pay_agent_session');
    window.location.href = cpPwaBaseUrl() + '/login.html?v=133';
}
