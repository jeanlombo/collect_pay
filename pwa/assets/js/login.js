/*
|--------------------------------------------------------------------------
| cOllect_Pay Mobile - Login PWA v133
|--------------------------------------------------------------------------
*/

function showLoginAlert(message, type = 'error') {
    const box = document.getElementById('loginAlert');

    if (!box) {
        alert(message);
        return;
    }

    box.className = 'alert ' + type;
    box.textContent = message;
    box.style.display = 'block';
}

function cpIsLocalCollectPay() {
    return window.location.pathname.indexOf('/collect_pay/') === 0;
}

function cpBaseUrlPwa() {
    return cpIsLocalCollectPay() ? '/collect_pay' : '';
}

function cpApiUrl(path) {
    return cpBaseUrlPwa() + '/' + String(path || '').replace(/^\/+/, '');
}

function goToDashboard() {
    window.location.replace('dashboard.html?v=133');
}

async function tryOfflineLogin(email, password) {
    const user = await getOfflineUserByEmailDB(email);

    if (!user) {
        showLoginAlert(
            'Aucun profil local trouvé. Faites une première connexion avec Internet.'
        );
        return false;
    }

    const hash = await simpleHash(password);

    if (hash !== user.password_hash) {
        showLoginAlert('Mot de passe offline incorrect.');
        return false;
    }

    await saveOfflineSessionDB({
        user_id: user.user_id,
        nom: user.nom,
        email: user.email,
        role: user.role,
        centre_id: user.centre_id,
        service_id: user.service_id,
        province_id: user.province_id,
        matricule: user.matricule || '',
        date_login: new Date().toISOString()
    });

    localStorage.setItem('collect_pay_agent_session', JSON.stringify(user));

    showLoginAlert('Connexion offline réussie. Ouverture du Dashboard...', 'success');

    setTimeout(goToDashboard, 600);
    return true;
}

async function loginAgent() {
    const emailInput = document.getElementById('login_email');
    const passwordInput = document.getElementById('login_password');

    const email = emailInput ? emailInput.value.trim() : '';
    const password = passwordInput ? passwordInput.value.trim() : '';

    if (!email || !password) {
        showLoginAlert('Veuillez saisir l’identifiant et le mot de passe.');
        return;
    }

    if (navigator.onLine) {
        try {
            showLoginAlert('Connexion au serveur...', 'success');

            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), 10000);

            const apiUrl = cpApiUrl('api/pwa_login.php?v=133');

            const response = await fetch(apiUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({email: email, password: password}),
                signal: controller.signal,
                cache: 'no-store'
            });

            clearTimeout(timeout);

            const text = await response.text();

            let result;
            try {
                result = JSON.parse(text);
            } catch (e) {
                showLoginAlert('Réponse serveur invalide : ' + text.substring(0, 220));
                return;
            }

            if (!result.success) {
                showLoginAlert(result.message || 'Connexion refusée.');
                return;
            }

            await saveOfflineUser(result.user, password);

            showLoginAlert('Connexion réussie. Ouverture du Dashboard...', 'success');
            setTimeout(goToDashboard, 600);
            return;

        } catch (e) {
            showLoginAlert(
                'Serveur inaccessible. Tentative de connexion offline...',
                'success'
            );

            await tryOfflineLogin(email, password);
            return;
        }
    }

    await tryOfflineLogin(email, password);
}

document.addEventListener('DOMContentLoaded', () => {
    const email = document.getElementById('login_email');
    const password = document.getElementById('login_password');

    [email, password].forEach(input => {
        if (!input) return;

        input.addEventListener('keydown', event => {
            if (event.key === 'Enter') {
                loginAgent();
            }
        });
    });
});
