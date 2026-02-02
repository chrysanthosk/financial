@extends('layouts.app')

@section('title', 'Reports')
@section('page-title', 'Reports')

@section('content')
<style>
  /* Scoped fixes only for Reports page */
  .reports-page .list-group-item {
    background-color: #ffffff;
  }

  body.dark-mode .reports-page .list-group-item {
    background-color: #343a40 !important;
    color: rgba(255,255,255,.92) !important;
    border-color: rgba(255,255,255,.12) !important;
  }

  body.dark-mode .reports-page .list-group-item .text-muted,
  body.dark-mode .reports-page .card .text-muted {
    color: rgba(255,255,255,.65) !important;
  }

  body.dark-mode .reports-page .list-group-item-action:hover,
  body.dark-mode .reports-page .list-group-item-action:focus {
    background-color: #3f474e !important;
    color: #fff !important;
  }

  body.dark-mode .reports-page .list-group-item-action:hover .text-muted,
  body.dark-mode .reports-page .list-group-item-action:focus .text-muted {
    color: rgba(255,255,255,.75) !important;
  }
</style>

<div class="reports-page">
@php
  $rangeLabel = ($from === ($year.'-01-01') && $to === ($year.'-12-31'))
      ? "Full year {$year}"
      : "{$from} → {$to}";
@endphp

<div class="mb-3">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div>
      <h2 class="h4 mb-1">All Reports</h2>
      <div class="text-muted">Quick filters + shortcuts. Range: <strong>{{ $rangeLabel }}</strong></div>
    </div>
    <div class="text-muted small">
      Tip: set a custom range to reuse in “Largest Transactions”, “Top Vendors”, and breakdown reports.
    </div>
  </div>
</div>

{{-- Quick filters --}}
<div class="card mb-3">
  <div class="card-header d-flex align-items-center justify-content-between">
    <strong><i class="fas fa-filter me-1"></i> Quick Filters</strong>
    <span class="text-muted small">Applies to the summary + links below</span>
  </div>
  <div class="card-body">
    <form method="GET" action="{{ route('reports.index') }}">
      <div class="row">
        <div class="col-md-3 mb-3">
          <label class="form-label">Year</label>
          <select name="year" class="form-control">
            @for($y = now()->year; $y >= now()->year - 6; $y--)
              <option value="{{ $y }}" @selected((int)$year === (int)$y)>{{ $y }}</option>
            @endfor
          </select>
          <div class="text-muted small mt-1">Default range is the full selected year.</div>
        </div>

        <div class="col-md-3 mb-3">
          <label class="form-label">From (optional)</label>
          <input type="date" name="from" class="form-control" value="{{ request('from', $from) }}">
        </div>

        <div class="col-md-3 mb-3">
          <label class="form-label">To (optional)</label>
          <input type="date" name="to" class="form-control" value="{{ request('to', $to) }}">
        </div>

        <div class="col-md-3 mb-3 d-flex align-items-end gap-2">
          <button class="btn btn-primary w-100" type="submit">
            <i class="fas fa-check me-1"></i> Apply
          </button>
          <a class="btn btn-outline-secondary w-100" href="{{ route('reports.index') }}">
            <i class="fas fa-times me-1"></i> Reset
          </a>
        </div>
      </div>
    </form>
  </div>
</div>

{{-- Summary --}}
<div class="row">
  <div class="col-lg-4 col-12">
    <div class="small-box bg-info">
      <div class="inner">
        <h3>{{ number_format((float)$incomeTotal, 2) }}</h3>
        <p>Income (selected range)</p>
      </div>
      <div class="icon">
        <i class="fas fa-coins"></i>
      </div>
      <a href="{{ route('reports.ytd_income', ['year' => $year]) }}" class="small-box-footer">
        View YTD Income <i class="fas fa-arrow-circle-right"></i>
      </a>
    </div>
  </div>

  <div class="col-lg-4 col-12">
    <div class="small-box bg-warning text-white">
      <div class="inner">
        <h3 class="text-white">{{ number_format((float)$expenseTotal, 2) }}</h3>
        <p class="text-white">Expenses (selected range)</p>
      </div>
      <div class="icon text-white">
        <i class="fas fa-receipt"></i>
      </div>
      <a href="{{ route('reports.ytd_expenses', ['year' => $year]) }}" class="small-box-footer text-white">
        View YTD Expenses <i class="fas fa-arrow-circle-right"></i>
      </a>
    </div>
  </div>

  <div class="col-lg-4 col-12">
    <div class="small-box {{ $profit >= 0 ? 'bg-success' : 'bg-danger' }}">
      <div class="inner">
        <h3>{{ number_format((float)$profit, 2) }}</h3>
        <p>Profit (Income − Expenses)</p>
      </div>
      <div class="icon">
        <i class="fas fa-chart-line"></i>
      </div>
      <a href="{{ route('reports.monthly_profit', ['year' => $year]) }}" class="small-box-footer">
        View Monthly Profit <i class="fas fa-arrow-circle-right"></i>
      </a>
    </div>
  </div>
</div>

{{-- Cards / links --}}
<div class="row">
  <div class="col-lg-6">
    <div class="card mb-3">
      <div class="card-header">
        <strong><i class="fas fa-chart-line me-1"></i> Core Reports</strong>
        <div class="text-muted small">Overview and comparisons</div>
      </div>
      <div class="card-body">
        <div class="list-group">

          <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
             href="{{ route('reports.ytd_income', ['year' => $year]) }}">
            <span><i class="fas fa-coins me-2"></i> Year-to-Date Income</span>
            <span class="text-muted small">Totals • Method breakdown • Monthly chart</span>
          </a>

          <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
             href="{{ route('reports.ytd_expenses', ['year' => $year]) }}">
            <span><i class="fas fa-receipt me-2"></i> Year-to-Date Expenses</span>
            <span class="text-muted small">Totals • Breakdown by method/category</span>
          </a>

          <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
             href="{{ route('reports.monthly_profit', ['year' => $year]) }}">
            <span><i class="fas fa-chart-line me-2"></i> Monthly Profit</span>
            <span class="text-muted small">Income − Expenses • Profit by month</span>
          </a>

          <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
             href="{{ route('reports.prev_year_comparison', ['year' => $year]) }}">
            <span><i class="fas fa-exchange-alt me-2"></i> Previous Year Comparison</span>
            <span class="text-muted small">Current vs Previous • Month-by-month</span>
          </a>

        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card mb-3">
      <div class="card-header">
        <strong><i class="fas fa-microscope me-1"></i> Analysis & Insights</strong>
        <div class="text-muted small">Find patterns and outliers</div>
      </div>
      <div class="card-body">
        <div class="list-group">

          <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
             href="{{ route('reports.largest_transactions', ['type' => 'expenses', 'year' => $year, 'from' => request('from'), 'to' => request('to')]) }}">
            <span><i class="fas fa-sort-amount-down-alt me-2"></i> Largest Transactions</span>
            <span class="text-muted small">Top expenses/income for a period</span>
          </a>

          <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
             href="{{ route('reports.recurring_expenses', ['months' => 12]) }}">
            <span><i class="fas fa-redo-alt me-2"></i> Recurring Expenses Detector</span>
            <span class="text-muted small">Vendors appearing in 3+ months</span>
          </a>

          <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
             href="{{ route('reports.top_vendors', ['year' => $year, 'from' => request('from'), 'to' => request('to')]) }}">
            <span><i class="fas fa-store me-2"></i> Top Vendors</span>
            <span class="text-muted small">Top spend by vendor for selected period</span>
          </a>

          {{-- NEW: Employee Income report (ADMIN ONLY) --}}
          @if(auth()->check() && (auth()->user()->role ?? null) === 'admin' && \Illuminate\Support\Facades\Route::has('reports.employee_income'))
            <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
               href="{{ route('reports.employee_income', ['year' => $year]) }}">
              <span><i class="fas fa-user-tie me-2"></i> Employee Income</span>
              <span class="text-muted small">Monthly totals per employee • Year selector</span>
            </a>
          @endif

          <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
             href="{{ route('reports.expense_category_breakdown', ['year' => $year, 'from' => request('from'), 'to' => request('to')]) }}">
            <span><i class="fas fa-chart-pie me-2"></i> Expense Category Breakdown</span>
            <span class="text-muted small">Pie/bar breakdown • Top N + other</span>
          </a>

          <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
             href="{{ route('reports.category_trend', ['year' => $year]) }}">
            <span><i class="fas fa-layer-group me-2"></i> Category Trend Over Time</span>
            <span class="text-muted small">Top categories across months</span>
          </a>

          <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
             href="{{ route('reports.income_method_trend', ['year' => $year]) }}">
            <span><i class="fas fa-percentage me-2"></i> Income Method Trend</span>
            <span class="text-muted small">Method share over time (percent)</span>
          </a>

        </div>
      </div>
    </div>
  </div>
</div>
</div>
@endsection
