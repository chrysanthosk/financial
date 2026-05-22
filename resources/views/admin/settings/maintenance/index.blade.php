@extends('layouts.app')

@section('title', 'Admin - Data Maintenance')

@section('content')
<div class="container-fluid">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">Admin / Data Maintenance</h1>
  </div>

  @if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
  @endif

  @error('confirm')
    <div class="alert alert-danger">{{ $message }}</div>
  @enderror

  <div class="alert alert-warning">
    <i class="fas fa-triangle-exclamation me-1"></i>
    These actions permanently delete data and <strong>cannot be undone</strong>.
    Only operational tables are listed here — financial records are never reachable from this page.
    You must type the exact name to confirm each action.
  </div>

  <div class="card">
    <div class="card-header"><strong>Operational data</strong></div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-striped align-middle mb-0">
          <thead>
          <tr>
            <th style="width:180px;">Target</th>
            <th>Description</th>
            <th style="width:140px;">Current</th>
            <th style="width:360px;">Clear</th>
          </tr>
          </thead>
          <tbody>
          @foreach($targets as $key => $t)
            <tr>
              <td><strong>{{ $t['label'] }}</strong><div class="text-muted small"><code>{{ $key }}</code></div></td>
              <td class="small text-muted">{{ $t['desc'] }}</td>
              <td class="small">
                @if(($t['type'] ?? '') === 'table')
                  @if(is_null($t['count'] ?? null))
                    <span class="text-muted">n/a</span>
                  @else
                    {{ number_format($t['count']) }} rows
                  @endif
                @else
                  {{ number_format(($t['size'] ?? 0) / 1024, 0) }} KB
                @endif
              </td>
              <td>
                <form method="POST" action="{{ route('admin.settings.maintenance.truncate', $key) }}"
                      class="d-flex gap-2"
                      onsubmit="return confirm('Permanently clear {{ $t['label'] }}? This cannot be undone.');">
                  @csrf
                  <input type="text" name="confirm" class="form-control form-control-sm"
                         placeholder="type &quot;{{ $key }}&quot;" autocomplete="off">
                  <button class="btn btn-sm btn-outline-danger text-nowrap" type="submit">
                    <i class="fas fa-trash me-1"></i> Clear
                  </button>
                </form>
              </td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>
@endsection
