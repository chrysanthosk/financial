@extends('layouts.app')

@section('title', 'Reports - Profit Margin by Year')
@section('page-title', 'Profit Margin by Year')

@section('content')
<div class="container-fluid">

  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
      <h1 class="h3 mb-0">Profit Margin by Year</h1>
      <div class="text-muted">
        Profit margin % (profit ÷ income) per year
        ({{ $endYear - $span + 1 }}–{{ $endYear }})
      </div>
    </div>
    <form method="GET" action="{{ route('reports.profit_margin_by_year') }}" class="d-flex gap-2">
      <input class="form-control" type="number" name="year" value="{{ $endYear }}" min="2000" max="2100" style="width:110px;" title="Latest year">
      <input class="form-control" type="number" name="years" value="{{ $span }}" min="1" max="50" style="width:90px;" title="Number of years">
      <button class="btn btn-primary" type="submit"><i class="fas fa-sync-alt me-1"></i> Update</button>
    </form>
  </div>

  <div class="card mb-3">
    <div class="card-header"><strong>Profit Margin %</strong></div>
    <div class="card-body">
      <canvas id="profitMarginChart" height="110"></canvas>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><strong>Yearly Breakdown</strong></div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-striped mb-0">
          <thead>
            <tr>
              <th>Year</th>
              <th class="text-end">Income</th>
              <th class="text-end">Profit</th>
              <th class="text-end">Margin %</th>
            </tr>
          </thead>
          <tbody>
            @forelse($rows as $r)
              <tr>
                <td><strong>{{ $r['year'] }}</strong></td>
                <td class="text-end">{{ number_format((float)$r['income'], 2) }}</td>
                <td class="text-end {{ $r['profit'] >= 0 ? 'text-success' : 'text-danger' }}">
                  {{ number_format((float)$r['profit'], 2) }}
                </td>
                <td class="text-end {{ $r['margin'] >= 0 ? 'text-success' : 'text-danger' }}">
                  {{ number_format((float)$r['margin'], 1) }}%
                </td>
              </tr>
            @empty
              <tr><td colspan="4" class="text-center text-muted">No data</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', async function () {
  const Chart = await window.loadChart();
  const labels = @json($labels);
  const margin = @json($marginByYear);

  new Chart(document.getElementById('profitMarginChart'), {
    type: 'line',
    data: {
      labels,
      datasets: [
        { label: 'Margin %', data: margin, tension: 0.25 },
      ]
    },
    options: {
      responsive: true,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { display: true },
        tooltip: { callbacks: { label: (c) => `${c.dataset.label}: ${c.parsed.y}%` } }
      },
      scales: { y: { ticks: { callback: (v) => `${v}%` } } }
    }
  });
});
</script>
@endsection
