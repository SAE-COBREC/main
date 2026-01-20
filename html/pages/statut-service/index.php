<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Statut du service - Alizon</title>
  <link rel="icon" type="image/png" href="../../../img/favicon.svg">
  <link rel="stylesheet" href="../../styles/droit/styleDroit.css">
  <link rel="stylesheet" href="../../styles/Header/stylesHeader.css">
  <link rel="stylesheet" href="../../styles/Footer/stylesFooter.css">
</head>
<?php require_once "../../partials/header.php" ?>

<main style="max-width:1000px;margin:1.5rem auto;padding:0 1rem">
  <h1>Statut du service</h1>
  <p>Simulation des statuts des services Alizon. Les états sont simulés côté client pour les tests.</p>

  <section style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1rem;margin-top:1rem">
    <div class="status-card" data-service="API" style="padding:1rem;border-radius:8px;border:1px solid #e6e6e6;background:#fff;box-shadow:0 6px 20px rgba(0,0,0,0.04)">
      <h3>API</h3>
      <p class="status">Chargement...</p>
      <p class="detail" style="font-size:0.9rem;color:#666;margin-top:0.5rem">Dernière mise à jour : --</p>
    </div>

    <div class="status-card" data-service="Base de données" style="padding:1rem;border-radius:8px;border:1px solid #e6e6e6;background:#fff;box-shadow:0 6px 20px rgba(0,0,0,0.04)">
      <h3>Base de données</h3>
      <p class="status">Chargement...</p>
      <p class="detail" style="font-size:0.9rem;color:#666;margin-top:0.5rem">Dernière mise à jour : --</p>
    </div>

    

    <div class="status-card" data-service="Web" style="padding:1rem;border-radius:8px;border:1px solid #e6e6e6;background:#fff;box-shadow:0 6px 20px rgba(0,0,0,0.04)">
      <h3>Site web</h3>
      <p class="status">Chargement...</p>
      <p class="detail" style="font-size:0.9rem;color:#666;margin-top:0.5rem">Dernière mise à jour : --</p>
    </div>
  </section>

  <div style="margin-top:1rem">
    <button id="refreshBtn" style="padding:0.5rem 1rem;border-radius:6px;border:0;background:#0078d4;color:#fff;cursor:pointer">Rafraîchir (simuler)</button>
    <button id="autoToggle" style="padding:0.5rem 1rem;border-radius:6px;border:0;background:#6c757d;color:#fff;cursor:pointer;margin-left:0.5rem">Auto: OFF</button>
  </div>

  <section style="margin-top:1.5rem">
    <h2>Historique & Uptime</h2>
    <div style="display:flex;gap:1rem;flex-wrap:wrap;align-items:center">
      <div style="flex:1;min-width:300px">
        <canvas id="uptimeChart" style="width:100%;height:180px"></canvas>
      </div>
      <div id="uptimeStats" style="display:flex;flex-direction:column;gap:0.5rem;min-width:220px">
        <!-- uptime per service -->
      </div>
    </div>
  </section>

  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
    const cards = Array.from(document.querySelectorAll('.status-card'));

    function updateCard(serviceKey, info){
      const card = cards.find(c => c.getAttribute('data-service') === serviceKey || c.getAttribute('data-service') === serviceKey.replace(/_/g,' '));
      if(!card) return;
      const statusEl = card.querySelector('.status');
      const detailEl = card.querySelector('.detail');
      statusEl.textContent = info.status || 'Indisponible';
      detailEl.textContent = info.message || '';
      const state = info.status || '';
      card.style.borderColor = state === 'OK' ? '#cfead7' : state === 'Dégradé' ? '#f6e2b3' : '#f5c6cb';
      card.style.background = state === 'OK' ? '#f7fffb' : state === 'Dégradé' ? '#fffdf6' : '#fff5f6';
    }

    async function fetchStatus(){
      try{
        const res = await fetch('check.php',{cache:'no-cache'});
        if(!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json();
        if(data.web) updateCard('Site web', data.web);
        if(data.database) updateCard('Base de données', data.database);
        if(data.delivraptor) updateCard('API', data.delivraptor);
        updateCard('Delivraptor', data.delivraptor || {});
      }catch(e){
        cards.forEach(c=>{
          const statusEl = c.querySelector('.status');
          const detailEl = c.querySelector('.detail');
          statusEl.textContent = 'Indisponible';
          detailEl.textContent = 'Échec de la vérification : ' + e.message;
          c.style.borderColor = '#f5c6cb'; c.style.background = '#fff5f6';
        });
      }
    }

    async function fetchHistory(){
      try{
        const res = await fetch('history.php',{cache:'no-cache'});
        if(!res.ok) throw new Error('HTTP ' + res.status);
        const history = await res.json();
        return history || [];
      }catch(e){ return []; }
    }

    function computeSeries(history){
      const recent = history.slice(-50);
      const labels = recent.map(r => new Date(r.ts * 1000).toLocaleTimeString());
      const services = {};
      recent.forEach(r => {
        Object.keys(r.result).forEach(k => {
          if(!services[k]) services[k]=[];
          const s = r.result[k].status === 'OK' ? 1 : 0;
          services[k].push(s);
        });
      });
      return {labels, services};
    }

    function renderUptime(stats){
      const container = document.getElementById('uptimeStats');
      container.innerHTML='';
      Object.keys(stats).forEach(k => {
        const div = document.createElement('div');
        div.style.display='flex'; div.style.justifyContent='space-between'; div.style.alignItems='center';
        div.innerHTML = `<strong>${k}</strong><span>${stats[k].toFixed(1)}%</span>`;
        container.appendChild(div);
      });
    }

    let uptimeChart = null;
    function renderChart(labels, services){
      const ctx = document.getElementById('uptimeChart').getContext('2d');
      const datasets = [];
      const colors = ['#28a745','#ffc107','#dc3545','#0078d4','#6f42c1'];
      let i=0;
      for(const k in services){
        datasets.push({
          label: k,
          data: services[k],
          borderColor: colors[i%colors.length],
          backgroundColor: colors[i%colors.length]+'44',
          fill:false,
          tension:0.1,
          pointRadius:2,
          yAxisID: 'y'
        });
        i++;
      }
      if(uptimeChart) uptimeChart.destroy();
      uptimeChart = new Chart(ctx, {
        type: 'line',
        data: {labels, datasets},
        options: {scales:{y:{min:0,max:1,ticks:{stepSize:1,callback: v => v===1? 'Up':'Down'}}},plugins:{legend:{position:'bottom'}}}
      });
    }

    async function refreshAll(){
      await fetchStatus();
      const history = await fetchHistory();
      const {labels, services} = computeSeries(history);
      const stats = {};
      Object.keys(services).forEach(k => {
        const arr = services[k];
        const sum = arr.reduce((a,b)=>a+b,0);
        stats[k] = (arr.length? (sum/arr.length*100):0);
      });
      renderUptime(stats);
      renderChart(labels, services);
    }

    document.getElementById('refreshBtn').addEventListener('click', refreshAll);

    let auto = false; let timer = null;
    document.getElementById('autoToggle').addEventListener('click', function(){
      auto = !auto;
      this.textContent = 'Auto: ' + (auto ? 'ON' : 'OFF');
      this.style.background = auto ? '#28a745' : '#6c757d';
      if(auto){ timer = setInterval(refreshAll,4000); } else { clearInterval(timer); }
    });

    // Initial
    refreshAll();
  </script>

</main>

<?php require_once "../../partials/footer.html" ?>
</html>
