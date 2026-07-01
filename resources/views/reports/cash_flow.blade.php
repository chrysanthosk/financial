@extends('layouts.app')

@section('title', 'Reports - Cash Flow')
@section('page-title', 'Cash Flow / Net Position')

@section('content')
<div class="container-fluid">

  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
      <h1 class="h3 mb-0">Cash Flow / Net Position ({{ $year }})</h1>
      <div class="text-muted">Monthly net movement and running balance (opening balance carried from prior years)</div>
    </div>
    <form method="GET" action="{{ route('reports.cash_flow') }}" class="d-flex gap-2">
      <input class="form-control" type="number" name="year" value="{{ $year }}" min="2000" max="2100" style="width:120px;">
      <button class="btn btn-primary" type="submit"><i class="fas fa-sync-alt me-1"></i> Update</button>
    </form>
  </div>

  <div class="row mb-3">
    <div class="col-lg-6 col-12 mb-2">
      <div class="small-box {{ $opening >= 0 ? 'bg-secondary' : 'bg-danger' }}">
        <div class="inner">
          <h3>{{ number_format((float)$opening, 2) }}</h3>
          <p>Opening Balance (1 Jan {{ $year }})</p>
        </div>
        <div class="icon"><i class="fas fa-hourglass-start"></i></div>
      </div>
    </div>
    <div class="col-lg-6 col-12 mb-2">
      <div class="small-box {{ $closing >= 0 ? 'bg-success' : 'bg-danger' }}">
        <div class="inner">
          <h3>{{ number_format((float)$closing, 2) }}</h3>
          <p>Closing Balance (31 Dec {{ $year }})</p>
        </div>
        <div class="icon"><i class="fas fa-hourglass-end"></i></div>
      </div>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header"><strong>Running Balance</strong></div>
    <div class="card-body">
      <canvas id="cashFlowChart" height="110"></canvas>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><strong>Monthly Detail</strong></div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-striped mb-0">
          <thead>
            <tr>
              <th>Month</th>
              <th class="text-end">Income</th>
              <th class="text-end">Expenses</th>
              <th class="text-end">Net</th>
              <th class="text-end">Running Balance</th>
            </tr>
          </thead>
          <tbody>
            @foreach($labels as $i => $label)
              <tr>
                <td><strong>{{ $label }}</strong></td>
                <td class="text-end">{{ number_format((float)$incomeByMonth[$i], 2) }}</td>
                <td class="text-end">{{ number_format((float)$expenseByMonth[$i], 2) }}</td>
                <td class="text-end {{ $netByMonth[$i] >= 0 ? 'text-success' : 'text-danger' }}">
                  {{ number_format((float)$netByMonth[$i], 2) }}
                </td>
                <td class="text-end {{ $runningByMonth[$i] >= 0 ? 'text-success' : 'text-danger' }}">
                  {{ number_format((float)$runningByMonth[$i], 2) }}
                </td>
              </tr>
            @endforeach
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
  const net = @json($netByMonth);
  const running = @json($runningByMonth);

  new Chart(document.getElementById('cashFlowChart'), {
    type: 'line',
    data: {
      labels,
      datasets: [
        { label: 'Running Balance', data: running, tension: 0.25 },
        { label: 'Monthly Net', data: net, type: 'bar' },
      ]
    },
    options: {
      responsive: true,
      interaction: { mode: 'index', intersect: false },
      plugins: { legend: { display: true } },
      scales: { y: { beginAtZero: false } }
    }
  });
});
</script>
@endsection
