@extends('layouts.app')

@section('content')
<div class="container py-3">
  <h3 class="mb-3 fade-in">Telemetry Legacy</h3>
  
  <div class="mb-3 fade-in fade-in-delay-1">
    <a href="/telemetry/export/csv" class="btn btn-outline-primary btn-sm">Скачать CSV</a>
    <a href="/telemetry/export/excel" class="btn btn-outline-success btn-sm">Скачать Excel</a>
  </div>

  <div class="table-responsive fade-in fade-in-delay-2">
    <table class="table table-sm table-striped align-middle">
      <thead>
        <tr>
          <th>#</th>
          <th>recorded_at</th>
          <th>voltage</th>
          <th>temp</th>
          <th>source_file</th>
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
@endsection
