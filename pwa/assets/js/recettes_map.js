/*
|--------------------------------------------------------------------------
| cOllect_Pay Mobile - Cartographie des recettes
|--------------------------------------------------------------------------
| Le système regroupe les recettes par zone.
| Les coordonnées ci-dessous peuvent être ajustées selon les vrais sites :
| Marché Central, Port, Aéroport, etc.
|--------------------------------------------------------------------------
*/

document.addEventListener('DOMContentLoaded', renderRecettesMap);

const TSHOPO_ZONES = [
    {
        code:'MARCHE_CENTRAL',
        nom:'Marché Central',
        lat:0.515,
        lng:25.190,
        rayon_km:3
    },
    {
        code:'PORT',
        nom:'Port',
        lat:0.520,
        lng:25.200,
        rayon_km:4
    },
    {
        code:'AEROPORT',
        nom:'Aéroport',
        lat:0.482,
        lng:25.337,
        rayon_km:6
    }
];

function moneyMap(v){
    return Number(v || 0).toLocaleString('fr-FR') + ' CDF';
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

    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));

    return R * c;
}

function detectZone(taxation){

    const lat = parseFloat(taxation.gps_lat || taxation.latitude || 0);
    const lng = parseFloat(taxation.gps_lng || taxation.longitude || 0);

    if(!lat || !lng){
        return {
            code:'SANS_GPS',
            nom:'Sans position GPS',
            distance:null
        };
    }

    let best = null;

    TSHOPO_ZONES.forEach(zone => {

        const d = distanceKm(lat,lng,zone.lat,zone.lng);

        if(!best || d < best.distance){
            best = {
                code:zone.code,
                nom:zone.nom,
                distance:d,
                rayon_km:zone.rayon_km
            };
        }
    });

    if(best && best.distance <= best.rayon_km){
        return best;
    }

    return {
        code:'AUTRE_ZONE',
        nom:'Autre zone Tshopo',
        distance:best ? best.distance : null
    };
}

async function renderRecettesMap(){

    const box = document.getElementById('mapContent');

    if(!box){
        return;
    }

    box.innerHTML = 'Chargement de la cartographie...';

    try{

        const all = typeof getAllTaxations === 'function'
            ? await getAllTaxations()
            : [];

        const map = {};

        all.forEach(t => {

            const zone = detectZone(t);

            if(!map[zone.code]){
                map[zone.code] = {
                    code:zone.code,
                    nom:zone.nom,
                    total:0,
                    count:0,
                    sync:0,
                    pending:0,
                    rows:[]
                };
            }

            map[zone.code].total += Number(t.montant_cdf || 0);
            map[zone.code].count++;

            if(t.sync === true){
                map[zone.code].sync++;
            }else{
                map[zone.code].pending++;
            }

            map[zone.code].rows.push(t);
        });

        const zones = Object.values(map).sort((a,b)=>b.total-a.total);

        const totalGeneral = zones.reduce((s,z)=>s+z.total,0);
        const maxTotal = zones.length ? zones[0].total : 1;

        let html = `
            <div style="text-align:center">
                <h2>Province de la Tshopo</h2>
                <strong>Total général : ${moneyMap(totalGeneral)}</strong><br>
                <small>Nombre de taxations : ${all.length}</small>
            </div>

            <hr>

            <h3>📍 Recettes par zone</h3>
        `;

        if(!zones.length){
            html += `<p>Aucune taxation locale disponible.</p>`;
        }

        zones.forEach(zone => {

            const pct = Math.max(4, Math.round((zone.total / maxTotal) * 100));

            html += `
                <div class="zone-card">
                    <strong>${zone.nom}</strong><br>
                    <span>${zone.count} opération(s) — ${moneyMap(zone.total)}</span><br>
                    <span>✅ ${zone.sync} synchronisée(s) — ⏳ ${zone.pending} en attente</span>

                    <div class="bar-wrap-map">
                        <div class="bar-fill-map" style="width:${pct}%"></div>
                    </div>
                </div>
            `;
        });

        html += `
            <h3>🏆 Classement détaillé</h3>

            <table class="table-map">
                <tr>
                    <th>Rang</th>
                    <th>Zone</th>
                    <th>Taxations</th>
                    <th>Total</th>
                </tr>
        `;

        zones.forEach((zone,idx) => {
            html += `
                <tr>
                    <td>${idx+1}</td>
                    <td>${zone.nom}</td>
                    <td>${zone.count}</td>
                    <td>${moneyMap(zone.total)}</td>
                </tr>
            `;
        });

        if(!zones.length){
            html += `<tr><td colspan="4">Aucune donnée.</td></tr>`;
        }

        html += `</table>`;

        html += `
            <h3>📋 Dernières taxations géolocalisées</h3>

            <table class="table-map">
                <tr>
                    <th>Date</th>
                    <th>Assujetti</th>
                    <th>Zone</th>
                    <th>Montant</th>
                </tr>
        `;

        all
            .sort((a,b)=>new Date(b.created_at)-new Date(a.created_at))
            .slice(0,20)
            .forEach(t => {
                const zone = detectZone(t);

                html += `
                    <tr>
                        <td>${new Date(t.created_at).toLocaleString('fr-FR')}</td>
                        <td>${String(t.contribuable_nom || '-').replace(/[<>]/g,'')}</td>
                        <td>${zone.nom}</td>
                        <td>${moneyMap(t.montant_cdf)}</td>
                    </tr>
                `;
            });

        if(!all.length){
            html += `<tr><td colspan="4">Aucune taxation.</td></tr>`;
        }

        html += `</table>`;

        box.innerHTML = html;

    }catch(e){
        box.innerHTML =
            '<p style="color:red;font-weight:bold">Erreur cartographie : ' +
            e.message +
            '</p>';
    }
}
