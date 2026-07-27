/*
|--------------------------------------------------------------------------
| cOllect_Pay Mobile - Carte interactive GPS
|--------------------------------------------------------------------------
| Carte locale sans dépendance externe.
| Fonctionne même sans Internet après cache PWA.
|--------------------------------------------------------------------------
*/

document.addEventListener('DOMContentLoaded', renderInteractiveMap);

const MAP_ZONES = [
    {
        code:'MARCHE_CENTRAL',
        nom:'Marché Central',
        lat:0.515,
        lng:25.190,
        radius:150
    },
    {
        code:'PORT',
        nom:'Port',
        lat:0.520,
        lng:25.200,
        radius:135
    },
    {
        code:'AEROPORT',
        nom:'Aéroport',
        lat:0.482,
        lng:25.337,
        radius:120
    }
];

/*
|--------------------------------------------------------------------------
| Bornes approximatives Kisangani / Tshopo
|--------------------------------------------------------------------------
*/
const MAP_BOUNDS = {
    minLat:0.430,
    maxLat:0.570,
    minLng:25.140,
    maxLng:25.370
};

function moneyMapInteractive(v){
    return Number(v || 0).toLocaleString('fr-FR') + ' CDF';
}

function safeMap(v){
    return String(v || '-').replace(/[<>]/g,'').trim();
}

function gpsToXY(lat,lng){
    const x =
        ((lng - MAP_BOUNDS.minLng) /
        (MAP_BOUNDS.maxLng - MAP_BOUNDS.minLng)) * 100;

    const y =
        100 -
        ((lat - MAP_BOUNDS.minLat) /
        (MAP_BOUNDS.maxLat - MAP_BOUNDS.minLat)) * 100;

    return {
        x: Math.min(96, Math.max(4, x)),
        y: Math.min(96, Math.max(4, y))
    };
}

function distanceKm(lat1,lng1,lat2,lng2){
    const R = 6371;
    const dLat = (lat2-lat1) * Math.PI / 180;
    const dLng = (lng2-lng1) * Math.PI / 180;

    const a =
        Math.sin(dLat/2) * Math.sin(dLat/2) +
        Math.cos(lat1*Math.PI/180) *
        Math.cos(lat2*Math.PI/180) *
        Math.sin(dLng/2) *
        Math.sin(dLng/2);

    return R * (2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a)));
}

function detectInteractiveZone(t){
    const lat = parseFloat(t.gps_lat || t.latitude || 0);
    const lng = parseFloat(t.gps_lng || t.longitude || 0);

    if(!lat || !lng){
        return 'Sans GPS';
    }

    let best = null;

    MAP_ZONES.forEach(z => {
        const d = distanceKm(lat,lng,z.lat,z.lng);

        if(!best || d < best.distance){
            best = {
                nom:z.nom,
                distance:d
            };
        }
    });

    if(best && best.distance <= 5){
        return best.nom;
    }

    return 'Autre zone Tshopo';
}

async function renderInteractiveMap(){
    const map = document.getElementById('gpsMap');
    const stats = document.getElementById('mapStats');
    const popup = document.getElementById('mapPopup');

    if(!map || !stats){
        return;
    }

    map.querySelectorAll('.map-zone,.map-point').forEach(el => el.remove());
    popup.style.display = 'none';

    const all = typeof getAllTaxations === 'function'
        ? await getAllTaxations()
        : [];

    /*
    |--------------------------------------------------------------------------
    | Zones
    |--------------------------------------------------------------------------
    */
    MAP_ZONES.forEach(zone => {
        const xy = gpsToXY(zone.lat,zone.lng);

        const z = document.createElement('div');
        z.className = 'map-zone';
        z.style.left = xy.x + '%';
        z.style.top = xy.y + '%';
        z.style.width = zone.radius + 'px';
        z.style.height = zone.radius + 'px';
        z.innerHTML = zone.nom;

        map.appendChild(z);
    });

    /*
    |--------------------------------------------------------------------------
    | Points GPS
    |--------------------------------------------------------------------------
    */
    all.forEach((t,idx) => {
        const lat = parseFloat(t.gps_lat || t.latitude || 0);
        const lng = parseFloat(t.gps_lng || t.longitude || 0);

        const hasGps = !!(lat && lng);

        const xy = hasGps
            ? gpsToXY(lat,lng)
            : {
                x: 6 + (idx % 5) * 5,
                y: 92 - Math.floor(idx / 5) * 5
            };

        const p = document.createElement('div');
        p.className =
            'map-point ' +
            (!hasGps ? 'no-gps' : (t.sync ? 'sync' : 'pending'));

        p.style.left = xy.x + '%';
        p.style.top = xy.y + '%';
        p.textContent = idx + 1;

        p.onclick = (e) => {
            const zone = detectInteractiveZone(t);

            popup.innerHTML = `
                <strong>${safeMap(t.contribuable_nom)}</strong><br>
                Montant : <strong>${moneyMapInteractive(t.montant_cdf)}</strong><br>
                Zone : ${zone}<br>
                Taxe : ${safeMap(t.type_taxe)}<br>
                Statut : ${t.sync ? '✅ Synchronisée' : '⏳ En attente'}<br>
                GPS : ${hasGps ? lat.toFixed(6)+', '+lng.toFixed(6) : 'Sans GPS'}<br>
                Date : ${new Date(t.created_at).toLocaleString('fr-FR')}
            `;

            popup.style.left = Math.min(70, xy.x) + '%';
            popup.style.top = Math.max(5, xy.y - 8) + '%';
            popup.style.display = 'block';
        };

        map.appendChild(p);
    });

    /*
    |--------------------------------------------------------------------------
    | Statistiques par zone
    |--------------------------------------------------------------------------
    */
    const zoneMap = {};

    all.forEach(t => {
        const zone = detectInteractiveZone(t);

        if(!zoneMap[zone]){
            zoneMap[zone] = {
                zone,
                count:0,
                total:0,
                sync:0,
                pending:0
            };
        }

        zoneMap[zone].count++;
        zoneMap[zone].total += Number(t.montant_cdf || 0);

        if(t.sync){
            zoneMap[zone].sync++;
        }else{
            zoneMap[zone].pending++;
        }
    });

    const rows = Object.values(zoneMap).sort((a,b)=>b.total-a.total);

    let html = `
        <h3>📊 Recettes par zone</h3>
        <table class="table-map">
            <tr>
                <th>Zone</th>
                <th>Taxations</th>
                <th>Total</th>
                <th>Sync</th>
            </tr>
    `;

    rows.forEach(r => {
        html += `
            <tr>
                <td>${r.zone}</td>
                <td>${r.count}</td>
                <td>${moneyMapInteractive(r.total)}</td>
                <td>✅ ${r.sync} / ⏳ ${r.pending}</td>
            </tr>
        `;
    });

    if(!rows.length){
        html += `<tr><td colspan="4">Aucune taxation disponible.</td></tr>`;
    }

    html += `</table>`;

    html += `
        <br>
        <p class="small">
            Cette carte fonctionne localement à partir des coordonnées GPS enregistrées dans le téléphone.
            Elle ne dépend pas de Google Maps ou d’Internet.
        </p>
    `;

    stats.innerHTML = html;
}
