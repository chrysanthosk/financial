@extends('layouts.app')

@section('title', 'Reports - Previous Year Monthly Income Comparison')
@section('page-title', 'Previous Year Monthly Income Comparison')

@section('content')
<div class="container-fluid">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <h1 class="h3 mb-0">Previous Year Monthly Income Comparison</h1>
      <div class="text-muted">Monthly income trend: {{ $year }} vs {{ $prevYear }}</div>
    </div>

    <form method="GET" action="{{ route('reports.prev_year_monthly_income') }}" class="d-flex gap-2">
      <input class="form-control" type="number" name="year" value="{{ $year }}" min="2000" max="2100" style="width:120px;">
      <button class="btn btn-primary" type="submit"><i class="fas fa-sync-alt me-1"></i> Update</button>
    </form>
  </div>

  <div class="row mb-3">
    <div class="col-lg-6 mb-2">
      <div class="card">
        <div class="card-header"><strong>{{ $year }} Total Income</strong></div>
        <div class="card-body">
          <div>Income: <strong>{{ number_format((float)$totalYear, 2) }}</strong></div>
        </div>
      </div>
    </div>

    <div class="col-lg-6 mb-2">
      <div class="card">
        <div class="card-header"><strong>{{ $prevYear }} Total Income</strong></div>
        <div class="card-body">
          <div>Income: <strong>{{ number_format((float)$totalPrev, 2) }}</strong></div>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><strong>Income by Month ({{ $year }} vs {{ $prevYear }})</strong></div>
    <div class="card-body">
      <canvas id="incomeCompare" height="110"></canvas>
    </div>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  const labels = @json($labels);
  const cur = @json($incomeYear);
  const prev = @json($incomePrev);

  new Chart(document.getElementById('incomeCompare'), {
    type: 'line',
    data: {
      labels,
      datasets: [
        { label: '{{ $year }} Income', data: cur, tension: 0.25 },
        { label: '{{ $prevYear }} Income', data: prev, tension: 0.25 },
      ]
    },
    options: {
      responsive: true,
      interaction: { mode: 'index', intersect: false },
      plugins: { legend: { display: true } },
      scales: { y: { beginAtZero: true } }
    }
  });
</script>
@endsection
