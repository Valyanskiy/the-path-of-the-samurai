@extends('layouts.app')

@section('content')
<div class="container py-3">
  <h3 class="mb-3 fade-in">Telemetry Legacy</h3>
  
  <div class="mb-3 fade-in fade-in-delay-1">
    <a href="/telemetry/export/csv" class="btn btn-outline-primary btn-sm">Скачать CSV</a>
    <a href="/telemetry/export/excel" class="btn btn-outline-success btn-sm">Скачать Excel</a>
  </div>

  <div class="card mb-3 fade-in fade-in-delay-1">
    <div class="card-body">
      <h6 class="card-title">Voltage / Temperature</h6>
      <canvas id="telemetryChart" height="100"></canvas>
    </div>
  </div>

  <div class="row g-2 mb-3 fade-in fade-in-delay-2">
    <div class="col-auto">
      <select id="searchCol" class="form-select form-select-sm">
        <option value="1">recorded_at</option>
        <option value="2">voltage</option>
        <option value="3">temp</option>
        <option value="4" selected>source_file</option>
      </select>
    </div>
    <div class="col-auto">
      <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Поиск...">
    </div>
  </div>

  <div class="table-responsive fade-in fade-in-delay-3">
    <table class="table table-sm table-striped align-middle" id="dataTable">
      <thead>
        <tr>
          <th data-sort="0" style="cursor:pointer">#</th>
          <th data-sort="1" style="cursor:pointer">recorded_at</th>
          <th data-sort="2" style="cursor:pointer">voltage</th>
          <th data-sort="3" style="cursor:pointer">temp</th>
          <th data-sort="4" style="cursor:pointer">source_file</th>
        </tr>
      </thead>
      <tbody>
      @forelse($items as $row)
        <tr>
          <td>{{ $row->id }}</td>
          <td>{{ $row->recorded_at }}</td>
          <td>{{ $row->voltage }}</td>
          <td>{{ $row->temp }}</td>
          <td>{{ $row->source_file }}</td>
        </tr>
      @empty
        <tr><td colspan="5" class="text-center text-muted">нет данных</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const table = document.getElementById('dataTable');
  const tbody = table.querySelector('tbody');
  const input = document.getElementById('searchInput');
  const colSel = document.getElementById('searchCol');
  let sortDir = {};

  // Charts data from table
  const labels = [], voltages = [], temps = [];
  tbody.querySelectorAll('tr').forEach(tr => {
    if (tr.cells.length >= 4) {
      labels.push(tr.cells[1]?.textContent.trim() || '');
      voltages.push(parseFloat(tr.cells[2]?.textContent) || 0);
      temps.push(parseFloat(tr.cells[3]?.textContent) || 0);
    }
  });

  if (typeof Chart !== 'undefined' && labels.length) {
    new Chart(document.getElementById('telemetryChart'), {
      type: 'line',
      data: {
        labels,
        datasets: [
          { label: 'Voltage', data: voltages, borderColor: '#0d6efd', tension: 0.3 },
          { label: 'Temp', data: temps, borderColor: '#dc3545', tension: 0.3 }
        ]
      },
      options: { responsive: true }
    });
  }

  input.addEventListener('input', () => {
    const val = input.value.toLowerCase();
    const col = parseInt(colSel.value);
    tbody.querySelectorAll('tr').forEach(tr => {
      const text = tr.cells[col]?.textContent.toLowerCase() || '';
      tr.style.display = text.includes(val) ? '' : 'none';
    });
  });

  table.querySelectorAll('th[data-sort]').forEach(th => {
    th.addEventListener('click', () => {
      const col = parseInt(th.dataset.sort);
      const asc = sortDir[col] = !sortDir[col];
      table.querySelectorAll('th[data-sort]').forEach(h => h.dataset.dir = '');
      th.dataset.dir = asc ? 'asc' : 'desc';
      const rows = Array.from(tbody.querySelectorAll('tr'));
      rows.sort((a, b) => {
        const av = a.cells[col]?.textContent.trim() || '';
        const bv = b.cells[col]?.textContent.trim() || '';
        return asc ? av.localeCompare(bv, undefined, {numeric: true}) : bv.localeCompare(av, undefined, {numeric: true});
      });
      rows.forEach(r => tbody.appendChild(r));
    });
  });
});
</script>
@endsection
