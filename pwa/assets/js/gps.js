/*
|--------------------------------------------------------------------------
| cOllect_Pay Mobile - GPS obligatoire + GPS avancé
|--------------------------------------------------------------------------
| Ce fichier doit être chargé APRÈS app.js dans index.html :
| <script src="assets/js/gps.js?v=140"></script>
|--------------------------------------------------------------------------
*/

let gpsWatchId = null;
let gpsAccuracy = null;
let gpsTimestamp = null;
let gpsReady = false;

/*
|--------------------------------------------------------------------------
| Démarrage GPS avancé
|--------------------------------------------------------------------------
*/
function startAdvancedGPS(){

    if(!navigator.geolocation){
        gpsReady = false;
        showGpsInfo('⚠️ GPS non supporté par ce téléphone.');
        return;
    }

    gpsWatchId = navigator.geolocation.watchPosition(
        pos => {

            gpsLat = pos.coords.latitude;
            gpsLng = pos.coords.longitude;
            gpsAccuracy = pos.coords.accuracy || null;
            gpsTimestamp = new Date().toISOString();
            gpsReady = true;

            showGpsInfo(
                '📍 GPS connecté : ' +
                gpsLat.toFixed(6) + ', ' +
                gpsLng.toFixed(6) +
                (gpsAccuracy ? ' — précision ' + Math.round(gpsAccuracy) + ' m' : '')
            );
        },
        err => {

            gpsReady = false;

            let msg = '⚠️ Veuillez activer le GPS pour effectuer une taxation.';

            if(err && err.message){
                msg += ' (' + err.message + ')';
            }

            showGpsInfo(msg);
        },
        {
            enableHighAccuracy:true,
            maximumAge:5000,
            timeout:15000
        }
    );
}

function stopAdvancedGPS(){
    if(gpsWatchId !== null){
        navigator.geolocation.clearWatch(gpsWatchId);
        gpsWatchId = null;
    }
}

function showGpsInfo(message){
    let box = document.getElementById('gpsInfoBox');

    if(!box){
        box = document.createElement('div');
        box.id = 'gpsInfoBox';
        box.className = 'status';
        box.style.marginTop = '10px';

        const taxation = document.getElementById('taxationSection');

        if(taxation){
            const title = taxation.querySelector('h3');
            if(title && title.nextSibling){
                taxation.insertBefore(box, title.nextSibling);
            }else{
                taxation.prepend(box);
            }
        }
    }

    box.textContent = message;

    if(gpsReady){
        box.style.background = '#dcfce7';
        box.style.color = '#166534';
    }else{
        box.style.background = '#fee2e2';
        box.style.color = '#991b1b';
    }
}

/*
|--------------------------------------------------------------------------
| Vérification GPS avant taxation
|--------------------------------------------------------------------------
*/
function canTaxWithGPS(){

    if(!gpsReady || gpsLat === null || gpsLng === null){

        alert(
            '⚠️ Veuillez activer le GPS pour effectuer une taxation.\n\n' +
            'Le système doit enregistrer la position de l’agent afin de prouver le lieu de perception.'
        );

        showGpsInfo('⚠️ Veuillez activer le GPS pour effectuer une taxation.');

        return false;
    }

    return true;
}

/*
|--------------------------------------------------------------------------
| Protection automatique de saveTaxation()
|--------------------------------------------------------------------------
| On enveloppe la fonction existante pour bloquer l'enregistrement
| si le GPS n'est pas disponible.
|--------------------------------------------------------------------------
*/
document.addEventListener('DOMContentLoaded', () => {

    setTimeout(() => {

        startAdvancedGPS();

        if(typeof window.saveTaxation === 'function' && !window.saveTaxationGpsProtected){

            const originalSaveTaxation = window.saveTaxation;

            window.saveTaxation = async function(){

                if(!canTaxWithGPS()){
                    return;
                }

                return await originalSaveTaxation();
            };

            window.saveTaxationGpsProtected = true;
        }

    }, 900);

});
