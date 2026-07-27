document.addEventListener('DOMContentLoaded', renderLocalAgents);

async function getAllOfflineUsersDB(){
    const db = await openDB();

    return new Promise((resolve,reject)=>{
        const tx = db.transaction(STORE_USERS,'readonly');
        const req = tx.objectStore(STORE_USERS).getAll();

        req.onsuccess = () => resolve(req.result || []);
        req.onerror = () => reject(req.error);
    });
}

async function switchLocalAgent(email){
    const user = await getOfflineUserByEmailDB(email);

    if(!user){
        alert('Agent introuvable localement.');
        return;
    }

    await saveOfflineSessionDB({
        user_id:user.user_id,
        nom:user.nom,
        email:user.email,
        role:user.role,
        centre_id:user.centre_id,
        service_id:user.service_id,
        province_id:user.province_id,
        matricule:user.matricule || '',
        date_login:new Date().toISOString()
    });

    localStorage.setItem('collect_pay_agent_session', JSON.stringify(user));

    alert('Agent actif changé : ' + (user.nom || user.email));
    renderLocalAgents();
}

async function renderLocalAgents(){
    const currentBox = document.getElementById('currentAgentBox');
    const listBox = document.getElementById('agentsList');

    const current = typeof getCurrentAgent === 'function' ? await getCurrentAgent() : null;
    const users = await getAllOfflineUsersDB();

    currentBox.innerHTML = current ? `
        <p>
            Nom : <strong>${current.nom || '-'}</strong><br>
            Email : ${current.email || '-'}<br>
            Rôle : ${current.role || '-'}<br>
            Centre : ${current.centre_id || '-'}<br>
            Service : ${current.service_id || '-'}
        </p>
    ` : 'Aucun agent connecté.';

    if(!users.length){
        listBox.innerHTML = 'Aucun profil agent enregistré.';
        return;
    }

    listBox.innerHTML = users.map(u => `
        <div class="agent-item">
            <strong>${u.nom || '-'}</strong><br>
            ${u.email || '-'}<br>
            Rôle : ${u.role || '-'}<br>
            Dernière synchro : ${u.last_sync || '-'}<br><br>
            <button class="btn-primary" onclick="switchLocalAgent('${u.email}')">
                Utiliser cet agent
            </button>
        </div>
    `).join('');
}
