@extends('layouts.app')

@section('title', 'Reports - Largest Transactions')
@section('page-title', 'Largest Transactions')

@section('content')
<div class="container-fluid">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <h1 class="h3 mb-0">Largest Transactions</h1>
      <div class="text-muted">Top {{ $limit }} {{ $type }} from {{ $from }} to {{ $to }}</div>
    </div>

    <form method="GET" action="{{ route('reports.largest_transactions') }}" class="row g-2 align-items-end">
      <div class="col-auto">
        <label class="form-label">Type</label>
        <select class="form-control" name="type">
          <option value="expenses" @selected($type==='expenses')>Expenses</option>
          <option value="income" @selected($type==='income')>Income</option>
        </select>
      </div>
      <div class="col-auto">
        <label class="form-label">Year</label>
        <input class="form-control" type="number" name="year" value="{{ $year }}" style="width:110px;">
      </div>
      <div class="col-auto">
        <label class="form-label">From</label>
        <input class="form-control" type="date" name="from" value="{{ $from }}">
      </div>
      <div class="col-auto">
        <label class="form-label">To</label>
        <input class="form-control" type="date" name="to" value="{{ $to }}">
      </div>
      <div class="col-auto">
        <label class="form-label">Limit</label>
        <input class="form-control" type="number" name="limit" value="{{ $limit }}" min="5" max="100" style="width:110px;">
      </div>
      <div class="col-auto">
        <button class="btn btn-primary" type="submit"><i class="fas fa-filter me-1"></i> Apply</button>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="card-header"><strong>Results</strong></div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-striped mb-0">
          <thead>
          <tr>
            <th style="width:140px;">Date</th>
            @if($type==='expenses')
              <th>Payee</th>
              <th style="width:180px;">Category</th>
              <th style="width:180px;">Method</th>
            @else
              <th style="width:260px;">Source</th>
              <th>Note</th>
            @endif
            <th class="text-end" style="width:160px;">Amount</th>
          </tr>
          </thead>
          <tbody>
          @forelse($rows as $r)
            <tr>
              @if($type==='expenses')
                <td>{{ \Illuminate\Support\Carbon::parse($r->expense_date)->toDateString() }}</td>
                <td>{{ $r->payee_name }}</td>
                <td>{{ $r->category?->name ?? '—' }}</td>
                <td>{{ $r->method?->name ?? '—' }}</td>
                <td class="text-end">{{ number_format((float)$r->amount, 2) }}</td>
              @else
                <td>{{ \Illuminate\Support\Carbon::parse($r->income_date)->toDateString() }}</td>
                <td>{{ $r->source?->name ?? '—' }}</td>
                <td>{{ $r->note ?? '—' }}</td>
                <td class="text-end">{{ number_format((float)$r->amount, 2) }}</td>
              @endif
            </tr>
          @empty
            <tr><td colspan="5" class="text-muted p-3">No transactions found for the selected filters.</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>
@endsection
