@extends('layouts.app')

@section('title', 'Reports - Income Method Trend')
@section('page-title', 'Income Method Trend')

@section('content')
<div class="container-fluid">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <h1 class="h3 mb-0">Income Method Trend ({{ $year }})</h1>
      <div class="text-muted">Method share over time (percentage per month)</div>
    </div>

    <form method="GET" action="{{ route('reports.income_method_trend') }}" class="d-flex gap-2 align-items-end">
      <div>
        <label class="form-label">Year</label>
        <input class="form-control" type="number" name="year" value="{{ $year }}" style="width:110px;">
      </div>
      <div>
        <label class="form-label">Top</label>
        <input class="form-control" type="number" name="top" value="{{ $topN }}" min="3" max="12" style="width:110px;">
      </div>
      <button class="btn btn-primary" type="submit"><i class="fas fa-sync-alt me-1"></i> Update</button>
    </form>
  </div>

  <div class="row">
    <div class="col-lg-8 mb-3">
      <div class="card">
        <div class="card-header"><strong>Method Share Over Time (%)</strong></div>
        <div class="card-body">
          <canvas id="methodTrend" height="120"></canvas>
        </div>
      </div>
    </div>

    <div class="col-lg-4 mb-3">
      <div class="card">
        <div class="card-header"><strong>Total Share (Year)</strong></div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-striped mb-0">
              <thead>
                <tr>
                  <th>Method</th>
                  <th class="text-end">Total</th>
                  <th class="text-end">%</th>
                </tr>
              </thead>
              <tbody>
              @forelse($table as $r)
                <tr>
                  <td>{{ $r['source'] }}</td>
                  <td class="text-end">{{ number_format((float)$r['total'], 2) }}</td>
                  <td class="text-end">{{ number_format((float)$r['percent'], 1) }}%</td>
                </tr>
              @empty
                <tr><td colspan="3" class="text-muted p-3">No income found for this year.</td></tr>
              @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

  </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const labels = @json($labels);
  const datasets = @json($datasets);

  new Chart(document.getElementById('methodTrend'), {
    type: 'line',
    data: {
      labels,
      datasets: datasets.map(d => ({
        label: d.label,
        data: d.data,
        tension: 0.25
      }))
    },
    options: {
      responsive: true,
      interaction: { mode: 'index', intersect: false },
      plugins: { legend: { display: true, position: 'bottom' } },
      scales: {
        y: {
          beginAtZero: true,
          max: 100,
          ticks: { callback: (v) => v + '%' }
        }
      }
    }
  });
});
</script>
@endsection
