@extends('layouts.app')

@section('title', 'Admin - Audit Log')

@php
    // Builds the URL to sort by $field, toggling direction on the active column
    // while preserving the current filters.
    $sortUrl = function (string $field) use ($sort, $direction) {
        $dir = ($sort === $field && $direction === 'asc') ? 'desc' : 'asc';
        $params = array_merge(request()->query(), ['sort' => $field, 'direction' => $dir]);
        unset($params['page']);

        return route('admin.audit.index', $params);
    };
@endphp

@section('content')
<div class="container-fluid">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">Admin / Audit Log</h1>
  </div>

  <div class="card mb-3">
    <div class="card-header"><strong>Filters</strong></div>
    <div class="card-body">
      <form method="GET" action="{{ route('admin.audit.index') }}">
        <div class="row">

          <div class="col-md-3 mb-3">
            <label class="form-label">Category</label>
            <select name="category" class="form-control">
              <option value="">All</option>
              @foreach(($categories ?? []) as $c)
                <option value="{{ $c }}" @selected(request('category') === $c)>{{ $c }}</option>
              @endforeach
            </select>
          </div>

          <div class="col-md-3 mb-3">
            <label class="form-label">Action contains</label>
            <input type="text" name="action" class="form-control" value="{{ request('action') }}" placeholder="smtp. / user. / auth. / income. / expense.">
          </div>

          <div class="col-md-3 mb-3">
            <label class="form-label">User</label>
            <select name="user_id" class="form-control">
              <option value="">All</option>
              @foreach(($users ?? []) as $u)
                @php
                  $uName = trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? ''));
                @endphp
                <option value="{{ $u->id }}" @selected((string)request('user_id') === (string)$u->id)>
                  {{ $u->email }}{{ $uName !== '' ? ' — '.$uName : '' }}
                </option>
              @endforeach
            </select>
          </div>

          <div class="col-md-3 mb-3">
            <label class="form-label">IP contains</label>
            <input type="text" name="ip" class="form-control" value="{{ request('ip') }}" placeholder="127.0.0.1">
          </div>

          <div class="col-md-3 mb-3">
            <label class="form-label">From</label>
            <input type="date" name="from" class="form-control" value="{{ request('from') }}">
          </div>

          <div class="col-md-3 mb-3">
            <label class="form-label">To</label>
            <input type="date" name="to" class="form-control" value="{{ request('to') }}">
          </div>

          <div class="col-md-6 mb-3">
            <label class="form-label">Search</label>
            <input type="text" name="search" class="form-control" value="{{ request('search') }}"
                   placeholder="search in action/category/target/ip">
          </div>

        </div>

        <div class="d-flex gap-2">
          <button class="btn btn-primary" type="submit">
            <i class="fas fa-filter me-1"></i> Apply
          </button>

          <a href="{{ route('admin.audit.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-times me-1"></i> Reset
          </a>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <strong>Events</strong>
      <div class="text-muted small">
        Showing {{ $logs->firstItem() ?? 0 }}–{{ $logs->lastItem() ?? 0 }} of {{ $logs->total() }}
      </div>
    </div>

    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover table-striped mb-0">
          <thead>
          <tr>
            @foreach(['time' => ['Time','140px'], 'user' => ['User','240px'], 'category' => ['Category','120px'], 'action' => ['Action','240px'], 'target' => ['Target','160px'], 'ip' => ['IP','140px']] as $field => $meta)
              <th style="width:{{ $meta[1] }};">
                <a href="{{ $sortUrl($field) }}" class="text-reset text-decoration-none">
                  {{ $meta[0] }}
                  @if($sort === $field)
                    <i class="fas fa-caret-{{ $direction === 'asc' ? 'up' : 'down' }} ms-1"></i>
                  @endif
                </a>
              </th>
            @endforeach
            <th>Meta</th>
          </tr>
          </thead>
          <tbody>
          @forelse($logs as $log)
            @php
              $logUserName = $log->user
                ? trim(($log->user->first_name ?? '') . ' ' . ($log->user->last_name ?? ''))
                : '';
            @endphp

            <tr>
              <td class="text-muted small">
                {{ $log->created_at?->format('Y-m-d H:i:s') }}
              </td>

              <td class="small">
                @if($log->user)
                  <div><strong>{{ $log->user->email }}</strong></div>
                  @if($logUserName !== '')
                    <div class="text-muted">{{ $logUserName }}</div>
                  @endif
                @else
                  <span class="text-muted">Guest/Deleted</span>
                @endif
              </td>

              <td>
                <span class="badge bg-secondary">{{ $log->category }}</span>
              </td>

              <td class="small">
                <code>{{ $log->action }}</code>
              </td>

              <td class="small">
                @if($log->target_type || $log->target_id)
                  <div><strong>{{ $log->target_type ?? '-' }}</strong></div>
                  <div class="text-muted">{{ $log->target_id ?? '-' }}</div>
                @else
                  <span class="text-muted">—</span>
                @endif
              </td>

              <td class="small">
                <code>{{ $log->ip ?? '-' }}</code>
              </td>

              <td class="small">
                @if(!empty($log->meta))
                  <details>
                    <summary class="text-primary" style="cursor:pointer;">view</summary>
                    <pre class="mb-0 mt-2"><code>{{ json_encode($log->meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre>
                  </details>
                @else
                  <span class="text-muted">—</span>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="text-center text-muted p-4">
                No audit events found for the selected filters.
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
