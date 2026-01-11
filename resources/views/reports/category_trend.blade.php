@extends('layouts.app')

@section('title', 'Reports - Category Trend')
@section('page-title', 'Category Trend Over Time')

@section('content')
<div class="container-fluid">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <h1 class="h3 mb-0">Category Trend ({{ $year }})</h1>
      <div class="text-muted">Top {{ $topN }} categories by total spend</div>
    </div>

    <form method="GET" action="{{ route('reports.category_trend') }}" class="d-flex gap-2 align-items-end">
      <div>
        <label class="form-label">Year</label>
        <input class="form-control" type="number" name="year" value="{{ $year }}" min="2000" max="2100" style="width:120px;">
      </div>
      <div>
        <label class="form-label">Top</label>
        <input class="form-control" type="number" name="top" value="{{ $topN }}" min="3" max="12" style="width:120px;">
      </div>
      <button class="btn btn-primary" type="submit"><i class="fas fa-sync-alt me-1"></i> Update</button>
    </form>
  </div>

  <div class="card">
    <div class="card-header"><strong>Monthly Spend by Category</strong></div>
    <div class="card-body">
      <canvas id="catTrend" height="110"></canvas>
    </div>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  const labels = @json($labels);
  const ds = @json($datasets);

  new Chart(document.getElementById('catTrend'), {
    type: 'line',
    data: {
      labels,
      datasets: ds.map(d => ({
        label: d.label,
        data: d.data,
        tension: 0.25
      }))
    },
    options: {
      responsive: true,
      interaction: { mode: 'index', intersect: false },
      plugins: { legend: { display: true, position: 'bottom' } },
      scales: { y: { beginAtZero: true } }
    }
  });
</script>
@endsection
