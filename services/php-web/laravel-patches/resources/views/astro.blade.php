@extends('layouts.app')

@section('content')
<div class="container pb-5">
  <h2 class="mb-4 fade-in">Астрономические события (AstronomyAPI)</h2>

  <div class="card shadow-sm fade-in fade-in-delay-1">
    <div class="card-body">
      <form id="astroForm" class="row g-2 align-items-center mb-3">
        <div class="col-auto">
          <label class="form-label mb-0">Широта</label>
          <input type="number" step="0.0001" class="form-control form-control-sm" name="lat" value="55.7558">
        </div>
        <div class="col-auto">
          <label class="form-label mb-0">Долгота</label>
          <input type="number" step="0.0001" class="form-control form-control-sm" name="lon" value="37.6176">
        </div>
        <div class="col-auto">
          <label class="form-label mb-0">Высота (м)</label>
          <input type="number" class="form-control form-control-sm" name="elevation" value="150" style="width:90px">
        </div>
        <div class="col-auto">
          <label class="form-label mb-0">Время</label>
          <input type="time" class="form-control form-control-sm" name="time" value="12:00">
        </div>
        <div class="col-auto">
          <label class="form-label mb-0">Дни</label>
          <input type="number" min="1" max="30" class="form-control form-control-sm" name="days" value="7" style="width:90px">
        </div>
        <div class="col-auto align-self-end">
          <button class="btn btn-sm btn-primary" type="submit">Показать</button>
        </div>
      </form>

      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead>
            <tr><th>#</th><th>Тело</th><th>Событие</th><th>Когда (UTC)</th><th>Дополнительно</th></tr>
          </thead>
          <tbody id="astroBody">
            <tr><td colspan="5" class="text-muted">нет данных</td></tr>
          </tbody>
        </table>
      </div>

      <details class="mt-2">
        <summary>Полный JSON</summary>
        <pre id="astroRaw" class="bg-light rounded p-2 small m-0" style="white-space:pre-wrap"></pre>
      </details>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('astroForm');
  const body = document.getElementById('astroBody');
  const raw  = document.getElementById('astroRaw');

  function normalize(node){
    const name = node.name || node.body || node.object || node.target || '';
    const type = node.type || node.event_type || node.category || node.kind || '';
    const when = node.time || node.date || node.occursAt || node.peak || node.instant || '';
    const extra = node.magnitude || node.mag || node.altitude || node.note || '';
    return {name, type, when, extra};
  }

  function collect(root){
    const rows = [];
    (function dfs(x){
      if (!x || typeof x !== 'object') return;
      if (Array.isArray(x)) { x.forEach(dfs); return; }
      if ((x.type || x.event_type || x.category) && (x.name || x.body || x.object || x.target)) {
        rows.push(normalize(x));
      }
      Object.values(x).forEach(dfs);
    })(root);
    return rows;
  }

  async function load(q){
    body.innerHTML = '<tr><td colspan="5" class="text-center py-4"><span class="spinner-border spinner-border-sm text-primary"></span> Загрузка…</td></tr>';
    const url = '/api/astro/events?' + new URLSearchParams(q).toString();
    try{
      const r  = await fetch(url);
      const js = await r.json();
      raw.textContent = JSON.stringify(js, null, 2);

      const rows = collect(js);
      if (!rows.length) {
        body.innerHTML = '<tr><td colspan="5" class="text-muted">события не найдены</td></tr>';
        return;
      }
      body.innerHTML = rows.slice(0,200).map((r,i)=>`
        <tr>
          <td>${i+1}</td>
          <td>${r.name || '—'}</td>
          <td>${r.type || '—'}</td>
          <td><code>${r.when || '—'}</code></td>
          <td>${r.extra || ''}</td>
        </tr>
      `).join('');
    }catch(e){
      body.innerHTML = '<tr><td colspan="5" class="text-danger">ошибка загрузки</td></tr>';
    }
  }

  form.addEventListener('submit', ev=>{
    ev.preventDefault();
    load(Object.fromEntries(new FormData(form).entries()));
  });

  load({lat: form.lat.value, lon: form.lon.value, elevation: form.elevation.value, time: form.time.value, days: form.days.value});
});
</script>
@endsection
