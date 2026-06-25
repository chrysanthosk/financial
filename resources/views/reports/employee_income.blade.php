@extends('layouts.app')

@section('title', 'Employee Income')

@section('content')
<div class="container-fluid">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">Employee Income</h1>
    <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary">
      <i class="fas fa-arrow-left"></i> Back
    </a>
  </div>

  <div class="card mb-3">
    <div class="card-header"><strong>Filters</strong></div>
    <div class="card-body">
      <form class="row g-2 align-items-end" method="GET" action="{{ route('reports.employee_income') }}">
        <div class="col-sm-3">
          <label class="form-label">Year</label>
          <select name="year" class="form-control">
            @foreach($years as $y)
              <option value="{{ $y }}" {{ (int)$y === (int)$year ? 'selected' : '' }}>{{ $y }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-sm-3">
          <label class="form-label">Month (optional)</label>
          <select name="month" class="form-control">
            <option value="" {{ is_null($month) ? 'selected' : '' }}>All year</option>
            @foreach($monthOptions as $m => $label)
              <option value="{{ $m }}" {{ (int)$month === (int)$m ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-sm-6">
          <button class="btn btn-primary me-2" type="submit">
            <i class="fas fa-filter"></i> Apply
          </button>

          <a class="btn btn-outline-secondary" href="{{ route('reports.employee_income') }}">
            Reset
          </a>
        </div>
      </form>
    </div>
  </div>

  <div class="row">
    <div class="col-lg-7">
      <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
          <strong>Totals by Employee</strong>
          <span class="text-muted small">
            Total: <strong>{{ number_format($grandTotal ?? 0, 2) }}</strong>
          </span>
        </div>
        <div class="card-body">
          @if(empty($labels))
            <div class="text-muted">No employee income data for the selected period.</div>
          @else
            <canvas id="employeeIncomeChart" height="120"></canvas>
          @endif
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card mb-3">
        <div class="card-header"><strong>Leaderboard</strong></div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
              <thead>
                <tr>
                  <th>Employee</th>
                  <th class="text-end">Total</th>
                </tr>
              </thead>
              <tbody>
                @forelse($rows as $r)
                  <tr>
                    <td>{{ $r->employee_name }}</td>
                    <td class="text-end">{{ number_format((float)$r->total, 2) }}</td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="2" class="text-center text-muted py-3">No data</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

</div>

@if(!empty($labels))
  <script>
document.addEventListener('DOMContentLoaded', function () {
    (function () {
      const ctx = document.getElementById('employeeIncomeChart');
      if (!ctx) return;

      new Chart(ctx, {
        type: 'bar',
        data: {
          labels: @json($labels),
          datasets: [{
            label: 'Total',
            data: @json($series),
          }]
        },
        options: {
          responsive: true,
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: {
                label: (c) => ' ' + Number(c.raw || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})
              }
            }
          },
          scales: {
            y: {
              ticks: {
                callback: (v) => Number(v).toLocaleString()
              }
            }
          }
        }
      });
    })();
  });
</script>
@endif
@endsection
