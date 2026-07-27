let signatureCanvas,
    signatureCtx,
    drawing = false;

let gpsLat = null;
let gpsLng = null;

let articlesOffline = [];

/*
|--------------------------------------------------------------------------
| AGENT CONNECTÉ OFFLINE
|--------------------------------------------------------------------------
*/
async function getCurrentAgent() {

    try {

        if (typeof getOfflineSessionDB === 'function') {

            const session =
                await getOfflineSessionDB();

            if (session) {
                return session;
            }
        }

        return JSON.parse(
            localStorage.getItem(
                'collect_pay_agent'
            ) || 'null'
        );

    } catch (e) {

        return null;

    }
}

/*
|--------------------------------------------------------------------------
| INITIALISATION
|--------------------------------------------------------------------------
*/
document.addEventListener(
    'DOMContentLoaded',
    async () => {

        updateStatus();

        initSignature();

        requestGPS();

        await loadArticlesFromCache();

        bindCalculEvents();

        renderPendingList();

        /*
        |--------------------------------------------------------------------------
        | Affichage agent connecté
        |--------------------------------------------------------------------------
        */
        const agent =
            await getCurrentAgent();

        if (agent) {

            const status =
                document.getElementById(
                    'statusBox'
                );

            if (status) {

                status.innerHTML +=
                    '<br>👤 Agent : <strong>' +
                    (
                        agent.nom ||
                        agent.full_name ||
                        agent.username ||
                        '-'
                    ) +
                    '</strong>';
            }
        }
    }
);

/*
|--------------------------------------------------------------------------
| ETAT DE CONNEXION
|--------------------------------------------------------------------------
*/
function updateStatus() {

    const b =
        document.getElementById(
            'statusBox'
        );

    if (!b) return;

    if (navigator.onLine) {

        b.textContent =
            '✅ En ligne : synchronisation disponible.';

        b.style.background =
            '#dcfce7';

        b.style.color =
            '#166534';
    }
    else {

        b.textContent =
            '📴 Hors connexion : stockage local actif.';

        b.style.background =
            '#ffedd5';

        b.style.color =
            '#9a3412';
    }
}

window.addEventListener(
    'online',
    updateStatus
);

window.addEventListener(
    'offline',
    updateStatus
);

/*
|--------------------------------------------------------------------------
| GPS
|--------------------------------------------------------------------------
*/
function requestGPS() {

    if (
        !navigator.geolocation
    ) {
        return;
    }

    navigator.geolocation.getCurrentPosition(

        p => {

            gpsLat =
                p.coords.latitude;

            gpsLng =
                p.coords.longitude;
        },

        () => {},

        {
            enableHighAccuracy:true,
            timeout:8000
        }
    );
}

/*
|--------------------------------------------------------------------------
| BARRES DE PROGRESSION
|--------------------------------------------------------------------------
*/
function setProgress(
    boxId,
    barId,
    textId,
    percent,
    text
) {

    const box =
        document.getElementById(
            boxId
        );

    const bar =
        document.getElementById(
            barId
        );

    const txt =
        document.getElementById(
            textId
        );

    if (box)
        box.style.display =
            'block';

    if (bar)
        bar.style.width =
            percent + '%';

    if (txt)
        txt.textContent =
            text;
}

function hideProgress(
    boxId
) {

    const box =
        document.getElementById(
            boxId
        );

    if (!box)
        return;

    setTimeout(() => {

        box.style.display =
            'none';

    }, 1800);
}

/*
|--------------------------------------------------------------------------
| CHARGEMENT DES ARTICLES
|--------------------------------------------------------------------------
*/
async function loadArticlesFromCache() {

    articlesOffline =
        JSON.parse(
            localStorage.getItem(
                'collect_pay_articles_offline'
            ) || '[]'
        );

    fillArticlesByType();
}

/*
|--------------------------------------------------------------------------
| IMPORTATION DES DONNÉES
|--------------------------------------------------------------------------
*/
async function importDataWithProgress() {

    if (
        !navigator.onLine
    ) {

        alert(
            'Connexion absente. Connectez-vous au serveur avant d’importer les données.'
        );

        return;
    }

    try {

        setProgress(
            'importProgressBox',
            'importProgressBar',
            'importProgressText',
            10,
            'Connexion au serveur...'
        );

        const response =
            await fetch(
                '/collect_pay/api/articles_offline.php'
            );

        setProgress(
            'importProgressBox',
            'importProgressBar',
            'importProgressText',
            45,
            'Téléchargement des articles et taux...'
        );

        const result =
            await response.json();

        if (
            !result.success
        ) {

            alert(
                result.message ||
                'Erreur lors de l’importation.'
            );

            return;
        }

        setProgress(
            'importProgressBox',
            'importProgressBar',
            'importProgressText',
            75,
            'Enregistrement local des données...'
        );

        articlesOffline =
            result.items || [];

        localStorage.setItem(
            'collect_pay_articles_offline',
            JSON.stringify(
                articlesOffline
            )
        );

        localStorage.setItem(
            'collect_pay_last_import',
            new Date()
                .toISOString()
        );

        fillArticlesByType();

        setProgress(
            'importProgressBox',
            'importProgressBar',
            'importProgressText',
            100,
            'Importation réussie avec succès : ' +
            articlesOffline.length +
            ' acte(s) importé(s).'
        );

        alert(
            'Importation réussie avec succès.'
        );

        hideProgress(
            'importProgressBox'
        );

    }
    catch (e) {

        alert(
            'Erreur importation : ' +
            e.message
        );
    }
}
async function saveTaxation() {

    const nom =
        document
            .getElementById(
                'contribuable_nom'
            )
            .value
            .trim();

    if (!nom) {
        alert(
            'Nom / raison sociale obligatoire.'
        );
        return;
    }

    const article =
        getSelectedArticle();

    if (!article) {
        alert(
            'Veuillez sélectionner un acte taxable.'
        );
        return;
    }

    calculateAmount();

    const photoCompressed =
        await compressImageFile(
            document
                .getElementById('photo')
                .files[0] || null
        );

    const montant =
        parseFloat(
            document
                .getElementById(
                    'montant_cdf'
                )
                .value || 0
        );

    if (montant <= 0) {
        alert(
            'Montant invalide.'
        );
        return;
    }

    const agent =
        await getCurrentAgent();

    const data = {

        local_id:
            'OFF-' +
            Date.now() +
            '-' +
            Math.floor(
                Math.random() * 99999
            ),

        agent_id:
            agent?.user_id ||
            agent?.id ||
            null,

        centre_id:
            agent?.centre_id ||
            null,

        service_id:
            agent?.service_id ||
            null,

        contribuable_nom: nom,

        telephone:
            document
                .getElementById(
                    'telephone'
                )
                .value
                .trim(),

        plaque:
            document
                .getElementById(
                    'plaque'
                )
                .value
                .trim(),

        type_taxe:
            document
                .getElementById(
                    'type_taxe'
                )
                .value,

        article_id:
            parseInt(
                article.id
            ),

        base_imposable:
            parseFloat(
                document
                    .getElementById(
                        'base_imposable'
                    )
                    .value || 0
            ),

        quantite:
            parseFloat(
                document
                    .getElementById(
                        'quantite'
                    )
                    .value || 1
            ),

        montant_cdf:
            montant,

        gps_lat:
            gpsLat,

        gps_lng:
            gpsLng,

        photo:
            photoCompressed,

        signature:
            signatureCanvas
                .toDataURL(
                    'image/jpeg',
                    0.45
                ),

        sync:false,

        created_at:
            new Date()
                .toISOString()
    };

    await addTaxation(data);

    /*
    |--------------------------------------------------------------------------
    | Création automatique du ticket
    |--------------------------------------------------------------------------
    */
    if (
        typeof
        createTicketForTaxation
        === 'function'
    ) {

        const ticket =
            await
            createTicketForTaxation(
                data
            );

        const imprimer =
            confirm(
                'Taxation enregistrée.\n\nVoulez-vous imprimer le ticket ?'
            );

        if (imprimer) {
            printTicketObject(
                ticket
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Vibration téléphone
    |--------------------------------------------------------------------------
    */
    if (
        navigator.vibrate
    ) {
        navigator.vibrate(
            [200,100,200]
        );
    }

    alert(
        '✅ Taxation enregistrée offline.'
    );

    document.getElementById(
        'contribuable_nom'
    ).value = '';

    document.getElementById(
        'telephone'
    ).value = '';

    document.getElementById(
        'plaque'
    ).value = '';

    document.getElementById(
        'base_imposable'
    ).value = 0;

    document.getElementById(
        'quantite'
    ).value = 1;

    document.getElementById(
        'montant_cdf'
    ).value = 0;

    document.getElementById(
        'photo'
    ).value = '';

    clearSignature();

    calculateAmount();

    renderPendingList();

    if (
        typeof
        renderTickets
        === 'function'
    ) {
        renderTickets();
    }
}
function fillArticlesByType() {

    const select =
        document.getElementById('article_id');

    if (!select) return;

    select.innerHTML =
        '<option value="">-- Choisir un acte taxable --</option>';

    articlesOffline.forEach(article => {

        const option =
            document.createElement('option');

        option.value = article.id;

        option.textContent =
            (article.code_article || '') +
            ' - ' +
            (article.nature_acte || 'Acte taxable');

        option.dataset.taux =
            article.taux_acte || 0;

        option.dataset.devise =
            article.devise_base || 'CDF';

        select.appendChild(option);

    });

    calculateAmount();
}

function getSelectedArticle() {

    const id =
        parseInt(
            document.getElementById('article_id').value
        );

    return articlesOffline.find(
        a => parseInt(a.id) === id
    );
}

function calculateAmount() {

    const article =
        getSelectedArticle();

    const montant =
        document.getElementById(
            'montant_cdf'
        );

    const info =
        document.getElementById(
            'calculInfo'
        );

    if (!article) {

        montant.value = 0;

        info.innerHTML =
            'Choisissez un acte taxable pour calculer le montant.';

        return;
    }

    const qte =
        parseFloat(
            document.getElementById('quantite').value
        ) || 1;

    const base =
        parseFloat(
            document.getElementById('base_imposable').value
        ) || 0;

    let total = 0;

    if (base > 0) {

        total =
            base * qte;

    } else {

        total =
            (parseFloat(article.taux_acte) || 0)
            * qte;
    }

    montant.value =
        total.toFixed(2);

    info.innerHTML =
        'Montant calculé : <strong>' +
        Number(total)
            .toLocaleString('fr-FR')
        +
        ' CDF</strong>';
}

function bindCalculEvents() {

    document
        .getElementById('article_id')
        ?.addEventListener(
            'change',
            calculateAmount
        );

    document
        .getElementById('quantite')
        ?.addEventListener(
            'input',
            calculateAmount
        );

    document
        .getElementById('base_imposable')
        ?.addEventListener(
            'input',
            calculateAmount
        );
}

function initSignature() {

    signatureCanvas =
        document.getElementById(
            'signaturePad'
        );

    if (!signatureCanvas)
        return;

    signatureCtx =
        signatureCanvas.getContext('2d');

    signatureCanvas.width =
        signatureCanvas.offsetWidth;

    signatureCanvas.height =
        200;

    signatureCanvas.addEventListener(
        'pointerdown',
        () => drawing = true
    );

    signatureCanvas.addEventListener(
        'pointerup',
        () => drawing = false
    );

    signatureCanvas.addEventListener(
        'pointermove',
        e => {

            if (!drawing)
                return;

            const rect =
                signatureCanvas
                    .getBoundingClientRect();

            signatureCtx.lineWidth = 2;

            signatureCtx.lineCap =
                'round';

            signatureCtx.lineTo(
                e.clientX - rect.left,
                e.clientY - rect.top
            );

            signatureCtx.stroke();

            signatureCtx.beginPath();

            signatureCtx.moveTo(
                e.clientX - rect.left,
                e.clientY - rect.top
            );
        }
    );
}

function clearSignature() {

    if (!signatureCtx)
        return;

    signatureCtx.clearRect(
        0,
        0,
        signatureCanvas.width,
        signatureCanvas.height
    );
}

async function compressImageFile(file) {

    if (!file)
        return null;

    return new Promise(resolve => {

        const reader =
            new FileReader();

        reader.onload =
            e => resolve(e.target.result);

        reader.readAsDataURL(file);
    });
}

async function renderPendingList() {

    const box =
        document.getElementById(
            'pendingList'
        );

    if (!box)
        return;

    const items =
        await getAllTaxations();

    if (!items.length) {

        box.innerHTML =
            'Aucune taxation enregistrée.';

        return;
    }

    box.innerHTML =
        items
        .reverse()
        .map(i => `
            <div class="item">
                <strong>
                    ${i.contribuable_nom}
                </strong><br>

                ${i.type_taxe}
                —
                ${Number(i.montant_cdf)
                    .toLocaleString('fr-FR')}
                CDF<br>

                ${
                    i.sync
                    ? '✅ Synchronisée'
                    : '⏳ En attente'
                }
            </div>
        `)
        .join('');
}