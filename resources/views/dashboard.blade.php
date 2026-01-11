@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
<div class="mb-3">
    <h2 class="h4 mb-1">{{ $greeting }}, {{ $displayName }} 👋</h2>
    <div class="text-muted">Here’s a quick overview of this month’s performance.</div>
</div>

<div class="row">
    {{-- Today Income --}}
    <div class="col-lg-3 col-6">
        <div class="small-box bg-success">
            <div class="inner">
                <h3>{{ number_format((float)$todayIncome, 2) }}</h3>
                <p>Today Income</p>
            </div>
            <div class="icon">
                <i class="fas fa-coins"></i>
            </div>
            <a href="{{ route('income.index') }}" class="small-box-footer">
                View Income <i class="fas fa-arrow-circle-right"></i>
            </a>
        </div>
    </div>

    {{-- MTD Income --}}
    <div class="col-lg-3 col-6">
        <div class="small-box bg-info">
            <div class="inner">
                <h3>{{ number_format((float)$mtdIncome, 2) }}</h3>
                <p>Month-to-date Income</p>
            </div>
            <div class="icon">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <a href="{{ route('income.index') }}" class="small-box-footer">
                View Income <i class="fas fa-arrow-circle-right"></i>
            </a>
        </div>
    </div>

    {{-- MTD Expenses (dark-mode safe) --}}
    <div class="col-lg-3 col-6">
        <div class="small-box bg-warning text-white">
            <div class="inner">
                <h3 class="text-white">{{ number_format((float)$mtdExpenses, 2) }}</h3>
                <p class="text-white">Month-to-date Expenses</p>
            </div>
            <div class="icon text-white">
                <i class="fas fa-receipt"></i>
            </div>
            <a href="{{ route('expenses.index') }}" class="small-box-footer text-white">
                View Expenses <i class="fas fa-arrow-circle-right"></i>
            </a>
        </div>
    </div>

    {{-- MTD Profit --}}
    <div class="col-lg-3 col-6">
        <div class="small-box {{ $mtdProfit >= 0 ? 'bg-primary' : 'bg-danger' }}">
            <div class="inner">
                <h3>{{ number_format((float)$mtdProfit, 2) }}</h3>
                <p>Month-to-date Profit</p>
            </div>
            <div class="icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <a href="{{ route('dashboard') }}" class="small-box-footer">
                Updated live <i class="fas fa-sync-alt"></i>
            </a>
        </div>
    </div>
</div>

<div class="row">
    {{-- Chart: Income vs Expenses by day --}}
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <strong>Income vs Expenses (Current Month)</strong>
                <div class="text-muted small">Daily totals</div>
            </div>
            <div class="card-body">
                <canvas id="incomeVsExpenseChart" height="110"></canvas>
            </div>
        </div>
    </div>

    {{-- Chart: Income by source --}}
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <strong>Income by Method</strong>
                <div class="text-muted small">Current month totals</div>
            </div>
            <div class="card-body">
                <canvas id="incomeBySourceChart" height="140"></canvas>

                @if(empty($chartIncomeSourceLabels) || count($chartIncomeSourceLabels) === 0)
                    <div class="text-muted small mt-2">No income yet this month.</div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Chart.js --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const labels = @json($chartLabels);
    const incomeSeries = @json($chartIncomeByDay);
    const expenseSeries = @json($chartExpensesByDay);

    const ctx1 = document.getElementById('incomeVsExpenseChart');
    new Chart(ctx1, {
        type: 'line',
        data: {
            labels,
            datasets: [
                { label: 'Income', data: incomeSeries, tension: 0.25 },
                { label: 'Expenses', data: expenseSeries, tension: 0.25 },
            ]
        },
        options: {
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { display: true } },
            scales: { y: { beginAtZero: true } }
        }
    });

    const sourceLabels = @json($chartIncomeSourceLabels);
    const sourceTotals = @json($chartIncomeSourceTotals);

    const ctx2 = document.getElementById('incomeBySourceChart');
    new Chart(ctx2, {
        type: 'doughnut',
        data: {
            labels: sourceLabels,
            datasets: [{ data: sourceTotals }]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom' } }
        }
    });
</script>
@endsection
