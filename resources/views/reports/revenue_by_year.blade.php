@extends('layouts.app')

@section('title', 'Reports - Revenue by Year')
@section('page-title', 'Revenue by Year')

@section('content')
<div class="container-fluid">

  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
      <h1 class="h3 mb-0">Revenue by Year</h1>
      <div class="text-muted">
        Company income, expenses and profit per year
        ({{ $endYear - $span + 1 }}–{{ $endYear }})
      </div>
    </div>
    <form method="GET" action="{{ route('reports.revenue_by_year') }}" class="d-flex gap-2">
      <input class="form-control" type="number" name="year" value="{{ $endYear }}" min="2000" max="2100" style="width:110px;" title="Latest year">
      <input class="form-control" type="number" name="years" value="{{ $span }}" min="1" max="50" style="width:90px;" title="Number of years">
      <button class="btn btn-primary" type="submit"><i class="fas fa-sync-alt me-1"></i> Update</button>
    </form>
  </div>

  <div class="row mb-3">
    <div class="col-lg-4 col-12 mb-2">
      <div class="small-box bg-info">
        <div class="inner">
          <h3>{{ number_format((float)$totalIncome, 2) }}</h3>
          <p>Total Income ({{ $span }} yr)</p>
        </div>
        <div class="icon"><i class="fas fa-coins"></i></div>
      </div>
    </div>
    <div class="col-lg-4 col-12 mb-2">
      <div class="small-box bg-warning text-white">
        <div class="inner">
          <h3 class="text-white">{{ number_format((float)$totalExpenses, 2) }}</h3>
          <p class="text-white">Total Expenses ({{ $span }} yr)</p>
        </div>
        <div class="icon text-white"><i class="fas fa-receipt"></i></div>
      </div>
    </div>
    <div class="col-lg-4 col-12 mb-2">
      <div class="small-box {{ $totalProfit >= 0 ? 'bg-success' : 'bg-danger' }}">
        <div class="inner">
          <h3>{{ number_format((float)$totalProfit, 2) }}</h3>
          <p>Total Profit ({{ $span }} yr)</p>
        </div>
        <div class="icon"><i class="fas fa-chart-line"></i></div>
      </div>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header"><strong>Revenue by Year</strong></div>
    <div class="card-body">
      <canvas id="revenueByYearChart" height="110"></canvas>
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
              <th class="text-end">Expenses</th>
              <th class="text-end">Profit</th>
            </tr>
          </thead>
          <tbody>
            @forelse($rows as $r)
              <tr>
                <td><strong>{{ $r['year'] }}</strong></td>
                <td class="text-end">{{ number_format((float)$r['income'], 2) }}</td>
                <td class="text-end">{{ number_format((float)$r['expenses'], 2) }}</td>
                <td class="text-end {{ $r['profit'] >= 0 ? 'text-success' : 'text-danger' }}">
                  {{ number_format((float)$r['profit'], 2) }}
                </td>
              </tr>
            @empty
              <tr><td colspan="4" class="text-center text-muted">No data</td></tr>
            @endforelse
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
  const income = @json($incomeByYear);
  const expenses = @json($expenseByYear);
  const profit = @json($profitByYear);

  new Chart(document.getElementById('revenueByYearChart'), {
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
