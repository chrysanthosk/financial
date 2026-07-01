@extends('layouts.app')

@section('title', 'Emp. Income')

@section('content')
<div class="container-fluid">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">Emp. Income</h1>

    <a href="{{ route('admin.emp_income.create') }}" class="btn btn-primary">
      <i class="fas fa-plus"></i> Add
    </a>
  </div>

  <div class="card mb-3">
    <div class="card-body">

      <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-2">
          <label class="form-label">Year</label>
          <input type="number" name="year" class="form-control" value="{{ $year }}" min="2000" max="2100">
        </div>

        <div class="col-md-2">
          <label class="form-label">Month (optional)</label>
          <select name="month" class="form-control">
            <option value="">All</option>
            @for($m=1; $m<=12; $m++)
              <option value="{{ $m }}" @selected((string)$month === (string)$m)>{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}</option>
            @endfor
          </select>
        </div>

        <div class="col-md-3">
          <button class="btn btn-outline-secondary">
            <i class="fas fa-filter"></i> Filter
          </button>
          <a href="{{ route('admin.emp_income.index') }}" class="btn btn-outline-secondary">
            Reset
          </a>
        </div>
      </form>

    </div>
  </div>

  <div class="card">
    <div class="card-body">

      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead>
            <tr>
              <th>Employee</th>
              <th style="width:120px;">Month</th>
              <th style="width:120px;">Year</th>
              <th class="text-end" style="width:180px;">Total</th>
              <th class="text-end" style="width:160px;">Bonus</th>
              <th class="text-end" style="width:220px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            @forelse($rows as $r)
              <tr>
                <td>{{ $r->employee?->name ?? '—' }}</td>
                <td>{{ str_pad($r->month, 2, '0', STR_PAD_LEFT) }}</td>
                <td>{{ $r->year }}</td>
                <td class="text-end">{{ number_format((float)$r->total_amount, 2) }}</td>
                <td class="text-end">
                  {{ number_format((float)($bonuses[$r->id]['amount'] ?? 0), 2) }}
                  <span class="text-muted small">({{ number_format(($bonuses[$r->id]['rate'] ?? 0) * 100, 0) }}%)</span>
                </td>
                <td class="text-end">
                  <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.emp_income.edit', $r) }}">
                    <i class="fas fa-edit"></i> Edit
                  </a>

                  <form method="POST" action="{{ route('admin.emp_income.destroy', $r) }}" class="d-inline"
                        onsubmit="return confirm('Delete this record?');">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger" type="submit">
                      <i class="fas fa-trash"></i> Delete
                    </button>
                  </form>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="6" class="text-muted">No records found.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <div class="mt-3">
        {{ $rows->links() }}
      </div>

    </div>
  </div>

</div>
@endsection
