@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
<style>
  /* Mobile-first tweaks for AdminLTE small-box + page spacing */
  @media (max-width: 575.98px) {
    .content .container { padding-left: 12px; padding-right: 12px; }
    .content-header { padding-bottom: .25rem; }

    .small-box { margin-bottom: 1rem; }
    .small-box .inner { padding: 12px; }
    .small-box .inner h3 { font-size: 1.9rem; margin: 0; }
    .small-box .inner p { font-size: 0.95rem; margin-bottom: .35rem; }
    .small-box .icon { display: none; } /* keep things clean on phones */
    .small-box-footer { padding: .5rem .75rem; font-size: .95rem; }

    /* Charts: avoid squashed canvases */
    .chart-wrap { height: 260px; }
  }

  /* Desktop/tablet chart height */
  @media (min-width: 576px) {
    .chart-wrap { height: 320px; }
  }

  /* Unpaid table dark-mode safe */
  body.dark-mode .table-unpaid {
    color: rgba(255,255,255,.92);
  }
  body.dark-mode .table-unpaid thead th {
    color: rgba(255,255,255,.92);
  }
  body.dark-mode .table-unpaid tbody td {
    color: rgba(255,255,255,.90);
  }
</style>

<div class="mb-3">
    <h2 class="h4 mb-1">{{ $greeting }}, {{ $displayName }} 👋</h2>
    <div class="text-muted">Here’s a quick overview of this month’s performance.</div>
</div>

<div class="row">
    {{-- Today Income --}}
    <div class="col-12 col-sm-6 col-lg-3">
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
    <div class="col-12 col-sm-6 col-lg-3">
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
    <div class="col-12 col-sm-6 col-lg-3">
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
    <div class="col-12 col-sm-6 col-lg-3">
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
    <div class="col-12 col-lg-8">
        <div class="card">
            <div class="card-header">
                <strong>Income vs Expenses (Current Month)</strong>
                <div class="text-muted small">Daily totals</div>
            </div>
            <div class="card-body">
                <div class="chart-wrap">
                    <canvas id="incomeVsExpenseChart"></canvas>
                </div>

                {{-- Unpaid Expenses table --}}
                <hr class="my-3">

                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div>
                        <strong><i class="fas fa-exclamation-circle me-1"></i> Unpaid Expenses</strong>
                        <div class="text-muted small">Latest unpaid items</div>
                    </div>

                    <a href="{{ route('expenses.index', ['paid' => 0]) }}" class="btn btn-sm btn-outline-secondary">
                        View all
                    </a>
                </div>

                @php
                  $unpaid = $unpaidExpenses ?? collect();
                @endphp

                @if($unpaid->isEmpty())
                    <div class="text-muted small">✅ No unpaid expenses found.</div>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm table-striped table-hover mb-0 align-middle table-unpaid">
                            <thead>
                            <tr>
                                <th style="width:110px;">Date</th>
                                <th>Payee</th>
                                <th style="width:140px;" class="text-end">Amount</th>
                                <th style="width:110px;" class="text-end">Action</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($unpaid as $e)
                                <tr>
                                    <td class="text-nowrap">{{ $e->expense_date?->format('Y-m-d') ?? '-' }}</td>
                                    <td>{{ $e->payee_name }}</td>
                                    <td class="text-end">{{ number_format((float)$e->amount, 2) }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('expenses.edit', $e) }}" class="btn btn-xs btn-outline-primary">
                                            Edit
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

            </div>
        </div>
    </div>

    {{-- Chart: Income by source --}}
    <div class="col-12 col-lg-4">
        <div class="card">
            <div class="card-header">
                <strong>Income by Method</strong>
                <div class="text-muted small">Current month totals</div>
            </div>
            <div class="card-body">
                <div class="chart-wrap">
                    <canvas id="incomeBySourceChart"></canvas>
                </div>

                @if(empty($chartIncomeSourceLabels) || count($chartIncomeSourceLabels) === 0)
                    <div class="text-muted small mt-2">No income yet this month.</div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Chart.js --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    const isMobile = window.matchMedia("(max-width: 575.98px)").matches;

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
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: isMobile ? 'bottom' : 'top' }
            },
            scales: {
                x: {
                    ticks: {
                        autoSkip: true,
                        maxTicksLimit: isMobile ? 6 : 12,
                        maxRotation: 0,
                        minRotation: 0
                    }
                },
                y: { beginAtZero: true }
            }
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
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } }
        }
    });
});
</script>
@endsection
