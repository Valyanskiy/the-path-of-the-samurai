@extends('layouts.app')

@section('content')
<div class="container py-3">
  <h3 class="mb-3 fade-in">NASA OSDR</h3>
  <div class="small text-muted mb-2 fade-in">Источник {{ $src }}</div>

  <div class="row g-2 mb-3 fade-in fade-in-delay-1">
    <div class="col-auto">
      <select id="searchCol" class="form-select form-select-sm">
        <option value="1">dataset_id</option>
        <option value="2" selected>title</option>
        <option value="4">updated_at</option>
      </select>
    </div>
    <div class="col-auto">
      <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Поиск...">
    </div>
  </div>

  <div class="table-responsive fade-in fade-in-delay-2">
    <table class="table table-sm table-striped align-middle" id="dataTable">
      <thead>
        <tr>
          <th data-sort="0" style="cursor:pointer">#</th>
          <th data-sort="1" style="cursor:pointer">dataset_id</th>
          <th data-sort="2" style="cursor:pointer">title</th>
          <th>REST_URL</th>
          <th data-sort="4" style="cursor:pointer">updated_at</th>
          <th data-sort="5" style="cursor:pointer">inserted_at</th>
          <th>raw</th>
        </tr>
      </thead>
      <tbody>
      @forelse($items as $row)
        <tr>
          <td>{{ $row['id'] }}</td>
          <td>{{ $row['dataset_id'] ?? '—' }}</td>
          <td style="max-width:420px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
            {{ $row['title'] ?? '—' }}
          </td>
          <td>
            @if(!empty($row['rest_url']))
              <a href="{{ $row['rest_url'] }}" target="_blank" rel="noopener">открыть</a>
            @else — @endif
          </td>
          <td>{{ $row['updated_at'] ?? '—' }}</td>
          <td>{{ $row['inserted_at'] ?? '—' }}</td>
          <td>
            <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="collapse" data-bs-target="#raw-{{ $row['id'] }}-{{ md5($row['dataset_id'] ?? (string)$row['id']) }}">JSON</button>
          </td>
        </tr>
        <tr class="collapse raw-row" id="raw-{{ $row['id'] }}-{{ md5($row['dataset_id'] ?? (string)$row['id']) }}">
          <td colspan="7">
            <pre class="mb-0" style="max-height:260px;overflow:auto">{{ json_encode($row['raw'] ?? [], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) }}</pre>
          </td>
        </tr>
      @empty
        <tr><td colspan="7" class="text-center text-muted">нет данных</td></tr>
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

  input.addEventListener('input', () => {
    const val = input.value.toLowerCase();
    const col = parseInt(colSel.value);
    tbody.querySelectorAll('tr:not(.raw-row)').forEach(tr => {
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
      const rows = Array.from(tbody.querySelectorAll('tr:not(.raw-row)'));
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
