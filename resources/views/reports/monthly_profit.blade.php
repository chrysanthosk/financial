@extends('layouts.app')

@section('title', 'Reports - Monthly Profit')
@section('page-title', 'Monthly Profit')

@section('content')
<div class="container-fluid">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <h1 class="h3 mb-0">Monthly Profit ({{ $year }})</h1>
      <div class="text-muted">Profit = monthly income total − monthly expenses total</div>
    </div>
    <form method="GET" action="{{ route('reports.monthly_profit') }}" class="d-flex gap-2">
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

  <div class="card">
    <div class="card-header"><strong>Profit by Month</strong></div>
    <div class="card-body">
      <canvas id="profitChart" height="110"></canvas>
    </div>
  </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', async function () {
    const Chart = await window.loadChart();
  const labels = @json($labels);
  const income = @json($incomeByMonth);
  const expenses = @json($expenseByMonth);
  const profit = @json($profitByMonth);

  new Chart(document.getElementById('profitChart'), {
    type: 'line',
    data: {
      labels,
      datasets: [
        { label: 'Income', data: income, tension: 0.25 },
        { label: 'Expenses', data: expenses, tension: 0.25 },
        { label: 'Profit', data: profit, tension: 0.25 },
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
