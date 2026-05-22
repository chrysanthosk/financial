@extends('layouts.app')

@section('title', 'Admin - Application Log')

@php
    $levelClasses = [
        'emergency' => 'bg-danger',
        'alert'     => 'bg-danger',
        'critical'  => 'bg-danger',
        'error'     => 'bg-danger',
        'warning'   => 'bg-warning text-dark',
        'notice'    => 'bg-info text-dark',
        'info'      => 'bg-primary',
        'debug'     => 'bg-secondary',
    ];

    // Builds the URL to sort by $field, toggling direction on the active column
    // while preserving the current filters.
    $sortUrl = function (string $field) use ($sort, $direction) {
        $dir = ($sort === $field && $direction === 'asc') ? 'desc' : 'asc';
        $params = array_merge(request()->query(), ['sort' => $field, 'direction' => $dir]);
        unset($params['page']);

        return route('admin.settings.logs.index', $params);
    };
@endphp

@section('content')
<div class="container-fluid">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">Admin / Application Log</h1>
  </div>

  <div class="card mb-3">
    <div class="card-header"><strong>Filters</strong></div>
    <div class="card-body">
      <form method="GET" action="{{ route('admin.settings.logs.index') }}">
        <div class="row">

          <div class="col-md-3 mb-3">
            <label class="form-label">Level</label>
            <select name="level" class="form-control">
              <option value="">All</option>
              @foreach($levels as $lvl)
                <option value="{{ $lvl }}" @selected(request('level') === $lvl)>{{ ucfirst($lvl) }}</option>
              @endforeach
            </select>
          </div>

          <div class="col-md-3 mb-3">
            <label class="form-label">From</label>
            <input type="date" name="from" class="form-control" value="{{ request('from') }}">
          </div>

          <div class="col-md-3 mb-3">
            <label class="form-label">To</label>
            <input type="date" name="to" class="form-control" value="{{ request('to') }}">
          </div>

          <div class="col-md-3 mb-3">
            <label class="form-label">Search</label>
            <input type="text" name="search" class="form-control" value="{{ request('search') }}"
                   placeholder="search in message / stack trace">
          </div>

        </div>

        <div class="d-flex gap-2">
          <button class="btn btn-primary" type="submit">
            <i class="fas fa-filter me-1"></i> Apply
          </button>

          <a href="{{ route('admin.settings.logs.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-times me-1"></i> Reset
          </a>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <strong>Entries</strong>
      <div class="text-muted small">
        Showing {{ $logs->firstItem() ?? 0 }}–{{ $logs->lastItem() ?? 0 }} of {{ $logs->total() }}
        @if($fileExists)
          · {{ number_format($fileSize / 1024, 0) }} KB
          @if($truncated)
            <span class="text-warning">(showing most recent portion only)</span>
          @endif
        @endif
      </div>
    </div>

    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover table-striped mb-0">
          <thead>
          <tr>
            @foreach(['time' => 'Time', 'level' => 'Level', 'env' => 'Env', 'message' => 'Message'] as $field => $label)
              @php $w = ['time' => 'width:160px;', 'level' => 'width:110px;', 'env' => 'width:90px;'][$field] ?? ''; @endphp
              <th style="{{ $w }}">
                <a href="{{ $sortUrl($field) }}" class="text-reset text-decoration-none">
                  {{ $label }}
                  @if($sort === $field)
                    <i class="fas fa-caret-{{ $direction === 'asc' ? 'up' : 'down' }} ms-1"></i>
                  @endif
                </a>
              </th>
            @endforeach
          </tr>
          </thead>
          <tbody>
          @forelse($logs as $entry)
            <tr>
              <td class="text-muted small">{{ $entry['timestamp'] }}</td>
              <td>
                <span class="badge {{ $levelClasses[$entry['level_lower']] ?? 'bg-secondary' }}">
                  {{ strtoupper($entry['level']) }}
                </span>
              </td>
              <td class="small text-muted">{{ $entry['env'] }}</td>
              <td class="small">
                <div>{{ $entry['message'] }}</div>
                @if($entry['trace'] !== '')
                  <details class="mt-1">
                    <summary class="text-primary" style="cursor:pointer;">stack trace</summary>
                    <pre class="mb-0 mt-2" style="white-space:pre-wrap; word-break:break-word;">{{ $entry['trace'] }}</pre>
                  </details>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="4" class="text-center text-muted p-4">
                @if(! $fileExists)
                  No log file found yet.
                @else
                  No log entries match the selected filters.
                @endif
              </td>
            </tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>

    @if($logs->hasPages())
      <div class="card-footer">
        {{ $logs->links() }}
      </div>
    @endif
  </div>

</div>
@endsection
