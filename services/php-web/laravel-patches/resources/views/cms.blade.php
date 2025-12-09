@extends('layouts.app')

@section('content')
<div class="container pb-5">
  <h2 class="mb-4">CMS-блоки</h2>

  <div class="card mb-3">
    <div class="card-header fw-semibold">Welcome</div>
    <div class="card-body">
      @if($cmsWelcome)
        {!! $cmsWelcome !!}
      @else
        <div class="text-muted">блок не найден</div>
      @endif
    </div>
  </div>

  <div class="card">
    <div class="card-header fw-semibold">Unsafe</div>
    <div class="card-body">
      @if($cmsUnsafe)
        {!! $cmsUnsafe !!}
      @else
        <div class="text-muted">блок не найден</div>
      @endif
    </div>
  </div>
</div>
@endsection
