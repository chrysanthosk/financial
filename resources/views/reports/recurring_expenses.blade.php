@extends('layouts.app')

@section('title', 'Reports - Recurring Expenses')
@section('page-title', 'Recurring Expenses Detector')

@section('content')
<div class="container-fluid">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <h1 class="h3 mb-0">Recurring Expenses Detector</h1>
      <div class="text-muted">Period: {{ $from }} → {{ $to }}</div>
    </div>

    <form method="GET" action="{{ route('reports.recurring_expenses') }}" class="d-flex gap-2 align-items-end">
      <div>
        <label class="form-label">Months Back</label>
        <input class="form-control" type="number" name="months" value="{{ $monthsBack }}" min="3" max="36" style="width:140px;">
      </div>
      <button class="btn btn-primary" type="submit"><i class="fas fa-sync-alt me-1"></i> Update</button>
    </form>
  </div>

  <div class="card">
    <div class="card-header"><strong>Vendors that appear in 3+ distinct months</strong></div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-striped mb-0">
          <thead>
            <tr>
              <th>Payee</th>
              <th class="text-end" style="width:160px;">Distinct Months</th>
              <th class="text-end" style="width:160px;">Transactions</th>
              <th class="text-end" style="width:180px;">Total Spend</th>
            </tr>
          </thead>
          <tbody>
          @forelse($results as $r)
            <tr>
              <td>{{ $r['payee'] }}</td>
              <td class="text-end">{{ $r['distinct_months'] }}</td>
              <td class="text-end">{{ $r['tx_count'] }}</td>
              <td class="text-end">{{ number_format((float)$r['total'], 2) }}</td>
            </tr>
          @empty
            <tr><td colspan="4" class="text-muted p-3">No recurring vendors detected for this period.</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>
@endsection
