@extends('layouts.app')

@section('title', 'Reports - Quarterly Summary')
@section('page-title', 'Quarterly Summary')

@section('content')
<div class="container-fluid">

  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
      <h1 class="h3 mb-0">Quarterly Summary ({{ $year }})</h1>
      <div class="text-muted">Income, expenses and profit per quarter (Q1–Q4)</div>
    </div>
    <form method="GET" action="{{ route('reports.quarterly_summary') }}" class="d-flex gap-2">
      <input class="form-control" type="number" name="year" value="{{ $year }}" min="2000" max="2100" style="width:120px;">
      <button class="btn btn-primary" type="submit"><i class="fas fa-sync-alt me-1"></i> Update</button>
    </form>
  </div>

  <div class="row mb-3">
    <div class="col-lg-4 col-12 mb-2">
      <div class="small-box bg-info">
        <div class="inner">
          <h3>{{ number_format((float)$totalIncome, 2) }}</h3>
          <p>Total Income</p>
        </div>
        <div class="icon"><i class="fas fa-coins"></i></div>
      </div>
    </div>
    <div class="col-lg-4 col-12 mb-2">
      <div class="small-box bg-warning text-white">
        <div class="inner">
          <h3 class="text-white">{{ number_format((float)$totalExpenses, 2) }}</h3>
          <p class="text-white">Total Expenses</p>
        </div>
        <div class="icon text-white"><i class="fas fa-receipt"></i></div>
      </div>
    </div>
    <div class="col-lg-4 col-12 mb-2">
      <div class="small-box {{ $totalProfit >= 0 ? 'bg-success' : 'bg-danger' }}">
        <div class="inner">
          <h3>{{ number_format((float)$totalProfit, 2) }}</h3>
          <p>Total Profit</p>
        </div>
        <div class="icon"><i class="fas fa-chart-line"></i></div>
      </div>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header"><strong>By Quarter</strong></div>
    <div class="card-body">
      <canvas id="quarterlyChart" height="110"></canvas>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><strong>Quarterly Breakdown</strong></div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-striped mb-0">
          <thead>
            <tr>
              <th>Quarter</th>
              <th class="text-end">Income</th>
              <th class="text-end">Expenses</th>
              <th class="text-end">Profit</th>
            </tr>
          </thead>
          <tbody>
            @foreach($rows as $r)
              <tr>
                <td><strong>{{ $r['quarter'] }}</strong></td>
                <td class="text-end">{{ number_format((float)$r['income'], 2) }}</td>
                <td class="text-end">{{ number_format((float)$r['expenses'], 2) }}</td>
                <td class="text-end {{ $r['profit'] >= 0 ? 'text-success' : 'text-danger' }}">
                  {{ number_format((float)$r['profit'], 2) }}
                </td>
              </tr>
            @endforeach
          </tbody>
          <tfoot>
            <tr>
              <th>Total</th>
              <th class="text-end">{{ number_format((float)$totalIncome, 2) }}</th>
              <th class="text-end">{{ number_format((float)$totalExpenses, 2) }}</th>
              <th class="text-end {{ $totalProfit >= 0 ? 'text-success' : 'text-danger' }}">
                {{ number_format((float)$totalProfit, 2) }}
              </th>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', async function () {
  const Chart = await window.loadChart();
  const labels = @json($labels);
  const income = @json($incomeByQuarter);
  const expenses = @json($expenseByQuarter);
  const profit = @json($profitByQuarter);

  new Chart(document.getElementById('quarterlyChart'), {
    type: 'bar',
    data: {
      labels,
      datasets: [
        { label: 'Income', data: income },
        { label: 'Expenses', data: expenses },
        { label: 'Profit', data: profit },
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
