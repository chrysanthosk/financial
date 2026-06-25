@extends('layouts.app')

@section('title', 'Reports - Previous Year Comparison')
@section('page-title', 'Previous Year Comparison')

@section('content')
<div class="container-fluid">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <h1 class="h3 mb-0">Previous Year Comparison</h1>
      <div class="text-muted">Full-year totals and profit trend: {{ $year }} vs {{ $prevYear }}</div>
    </div>

    <form method="GET" action="{{ route('reports.prev_year_comparison') }}" class="d-flex gap-2">
      <input class="form-control" type="number" name="year" value="{{ $year }}" min="2000" max="2100" style="width:120px;">
      <button class="btn btn-primary" type="submit"><i class="fas fa-sync-alt me-1"></i> Update</button>
    </form>
  </div>

  <div class="row mb-3">
    <div class="col-lg-6 mb-2">
      <div class="card">
        <div class="card-header"><strong>{{ $year }} Totals</strong></div>
        <div class="card-body">
          <div>Income: <strong>{{ number_format((float)$current['income'], 2) }}</strong></div>
          <div>Expenses: <strong>{{ number_format((float)$current['expenses'], 2) }}</strong></div>
          <div>Profit: <strong>{{ number_format((float)$current['profit'], 2) }}</strong></div>
        </div>
      </div>
    </div>

    <div class="col-lg-6 mb-2">
      <div class="card">
        <div class="card-header"><strong>{{ $prevYear }} Totals</strong></div>
        <div class="card-body">
          <div>Income: <strong>{{ number_format((float)$previous['income'], 2) }}</strong></div>
          <div>Expenses: <strong>{{ number_format((float)$previous['expenses'], 2) }}</strong></div>
          <div>Profit: <strong>{{ number_format((float)$previous['profit'], 2) }}</strong></div>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><strong>Profit by Month ({{ $year }} vs {{ $prevYear }})</strong></div>
    <div class="card-body">
      <canvas id="profitCompare" height="110"></canvas>
    </div>
  </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const labels = @json($labels);
  const cur = @json($curProfit);
  const prev = @json($prevProfit);

  new Chart(document.getElementById('profitCompare'), {
    type: 'line',
    data: {
      labels,
      datasets: [
        { label: '{{ $year }} Profit', data: cur, tension: 0.25 },
        { label: '{{ $prevYear }} Profit', data: prev, tension: 0.25 },
      ]
    },
    options: {
      responsive: true,
      interaction: { mode: 'index', intersect: false },
      plugins: { legend: { display: true } },
      scales: { y: { beginAtZero: true } }
    }
  });
});
</script>
@endsection
