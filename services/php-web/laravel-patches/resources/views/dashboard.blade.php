@extends('layouts.app')

@section('content')
<div class="container pb-5">
  {{-- верхние карточки --}}
  <div class="row g-3 mb-2">
    <div class="col-6 col-md-3"><div class="border rounded p-2 text-center">
      <div class="small text-muted">Скорость МКС</div>
      <div class="fs-4" id="issSpeed">{!! isset(($iss['payload'] ?? [])['velocity']) ? number_format($iss['payload']['velocity'],0,'',' ') : '<span class="placeholder col-4"></span>' !!}</div>
    </div></div>
    <div class="col-6 col-md-3"><div class="border rounded p-2 text-center">
      <div class="small text-muted">Высота МКС</div>
      <div class="fs-4" id="issAlt">{!! isset(($iss['payload'] ?? [])['altitude']) ? number_format($iss['payload']['altitude'],0,'',' ') : '<span class="placeholder col-4"></span>' !!}</div>
    </div></div>
  </div>

  <div class="row g-3">
    {{-- левая колонка: JWST наблюдение --}}
    <div class="col-lg-7">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h5 class="card-title">JWST — выбранное наблюдение</h5>
          <div class="text-muted">Этот блок остаётся как был (JSON/сводка). Основная галерея ниже.</div>
        </div>
      </div>
    </div>

    {{-- правая колонка: карта МКС --}}
    <div class="col-lg-5">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h5 class="card-title">МКС — положение и движение</h5>
          <div id="map" class="rounded mb-2 border" style="height:300px"></div>
          <div class="row g-2">
            <div class="col-6"><canvas id="issSpeedChart" height="110"></canvas></div>
            <div class="col-6"><canvas id="issAltChart"   height="110"></canvas></div>
          </div>
        </div>
      </div>
    </div>

    {{-- НИЖНЯЯ ПОЛОСА: ГАЛЕРЕЯ JWST --}}
    <div class="col-12">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="card-title m-0">JWST — последние изображения</h5>
            <form id="jwstFilter" class="row g-2 align-items-center">
              <div class="col-auto">
                <select class="form-select form-select-sm" name="source" id="srcSel">
                  <option value="jpg" selected>Все JPG</option>
                  <option value="suffix">По суффиксу</option>
                  <option value="program">По программе</option>
                </select>
              </div>
              <div class="col-auto">
                <input type="text" class="form-control form-control-sm" name="suffix" id="suffixInp" placeholder="_cal / _thumb" style="width:140px;display:none">
                <input type="text" class="form-control form-control-sm" name="program" id="progInp" placeholder="2734" style="width:110px;display:none">
              </div>
              <div class="col-auto">
                <select class="form-select form-select-sm" name="instrument" style="width:130px">
                  <option value="">Любой инструмент</option>
                  <option>NIRCam</option><option>MIRI</option><option>NIRISS</option><option>NIRSpec</option><option>FGS</option>
                </select>
              </div>
              <div class="col-auto">
                <select class="form-select form-select-sm" name="perPage" style="width:90px">
                  <option>12</option><option selected>24</option><option>36</option><option>48</option>
                </select>
              </div>
              <div class="col-auto">
                <button class="btn btn-sm btn-primary" type="submit">Показать</button>
              </div>
            </form>
          </div>

          <style>
            .jwst-slider{position:relative}
            .jwst-track{display:flex; gap:.75rem; overflow:auto; scroll-snap-type:x mandatory; padding:.25rem}
            .jwst-item{flex:0 0 180px; scroll-snap-align:start}
            .jwst-item img{width:100%; height:180px; object-fit:cover; border-radius:.5rem}
            .jwst-cap{font-size:.85rem; margin-top:.25rem}
            .jwst-nav{position:absolute; top:40%; transform:translateY(-50%); z-index:2}
            .jwst-prev{left:-.25rem} .jwst-next{right:-.25rem}
          </style>

          <div class="jwst-slider">
            <button class="btn btn-light border jwst-nav jwst-prev" type="button" aria-label="Prev">‹</button>
            <div id="jwstTrack" class="jwst-track border rounded"></div>
            <button class="btn btn-light border jwst-nav jwst-next" type="button" aria-label="Next">›</button>
          </div>

          <div id="jwstInfo" class="small text-muted mt-2"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async function () {
  // ====== карта и графики МКС ======
  if (typeof L !== 'undefined' && typeof Chart !== 'undefined') {
    const last = @json(($iss['payload'] ?? []));
    let lat0 = Number(last.latitude || 0), lon0 = Number(last.longitude || 0);
    const map = L.map('map', { attributionControl:false }).setView([lat0||0, lon0||0], lat0?3:2);
    L.tileLayer('https://{s}.tile.openstreetmap.de/{z}/{x}/{y}.png', { noWrap:true }).addTo(map);
    const trail  = L.polyline([], {weight:3}).addTo(map);
    const marker = L.marker([lat0||0, lon0||0]).addTo(map).bindPopup('МКС');

    const speedChart = new Chart(document.getElementById('issSpeedChart'), {
      type: 'line', data: { labels: [], datasets: [{ label: 'Скорость', data: [] }] },
      options: { responsive: true, scales: { x: { display: false } } }
    });
    const altChart = new Chart(document.getElementById('issAltChart'), {
      type: 'line', data: { labels: [], datasets: [{ label: 'Высота', data: [] }] },
      options: { responsive: true, scales: { x: { display: false } } }
    });

    async function loadTrend() {
      try {
        const lastRes = await fetch('/api/iss/last');
        const last = await lastRes.json();
        const p = last.payload || {};
        
        if (p.latitude && p.longitude) {
          const newPos = [p.latitude, p.longitude];
          const pts = trail.getLatLngs();
          pts.push(newPos);
          if (pts.length > 240) pts.shift();
          trail.setLatLngs(pts);
          marker.setLatLng(newPos);
        }
        
        document.getElementById('issSpeed').textContent = p.velocity ? Math.round(p.velocity).toLocaleString() : '—';
        document.getElementById('issAlt').textContent = p.altitude ? Math.round(p.altitude).toLocaleString() : '—';
        
        const now = new Date().toLocaleTimeString();
        speedChart.data.labels.push(now);
        speedChart.data.datasets[0].data.push(p.velocity || 0);
        altChart.data.labels.push(now);
        altChart.data.datasets[0].data.push(p.altitude || 0);
        if (speedChart.data.labels.length > 20) {
          speedChart.data.labels.shift();
          speedChart.data.datasets[0].data.shift();
          altChart.data.labels.shift();
          altChart.data.datasets[0].data.shift();
        }
        speedChart.update();
        altChart.update();
      } catch(e) { console.error(e); }
    }
    loadTrend();
    setInterval(loadTrend, {{ $issEverySeconds ?? 120 }} * 1000);
  }

  // ====== JWST ГАЛЕРЕЯ ======
  const track = document.getElementById('jwstTrack');
  const info  = document.getElementById('jwstInfo');
  const form  = document.getElementById('jwstFilter');
  const srcSel = document.getElementById('srcSel');
  const sfxInp = document.getElementById('suffixInp');
  const progInp= document.getElementById('progInp');

  function toggleInputs(){
    sfxInp.style.display  = (srcSel.value==='suffix')  ? '' : 'none';
    progInp.style.display = (srcSel.value==='program') ? '' : 'none';
  }
  srcSel.addEventListener('change', toggleInputs); toggleInputs();

  async function loadFeed(qs){
    track.innerHTML = '<div class="d-flex align-items-center justify-content-center gap-2 p-4 text-secondary"><span class="spinner-border spinner-border-sm"></span>Загрузка…</div>';
    info.textContent= '';
    try{
      const url = '/api/jwst/feed?'+new URLSearchParams(qs).toString();
      const r = await fetch(url);
      const js = await r.json();
      track.innerHTML = '';
      (js.items||[]).forEach(it=>{
        const fig = document.createElement('figure');
        fig.className = 'jwst-item m-0';
        fig.innerHTML = `
          <a href="${it.link||it.url}" target="_blank" rel="noreferrer">
            <img loading="lazy" src="${it.url}" alt="JWST">
          </a>
          <figcaption class="jwst-cap">${(it.caption||'').replaceAll('<','&lt;')}</figcaption>`;
        track.appendChild(fig);
      });
      info.textContent = `Источник: ${js.source} · Показано ${js.count||0}`;
    }catch(e){
      track.innerHTML = '<div class="p-3 text-danger">Ошибка загрузки</div>';
    }
  }

  form.addEventListener('submit', function(ev){
    ev.preventDefault();
    loadFeed(Object.fromEntries(new FormData(form).entries()));
  });

  document.querySelector('.jwst-prev').addEventListener('click', ()=> track.scrollBy({left:-600, behavior:'smooth'}));
  document.querySelector('.jwst-next').addEventListener('click', ()=> track.scrollBy({left: 600, behavior:'smooth'}));

  loadFeed({source:'jpg', perPage:24});
});
</script>
@endsection
