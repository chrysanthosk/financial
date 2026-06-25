@extends('layouts.app')

@section('title', 'Reports - Expense Category Breakdown')
@section('page-title', 'Expense Category Breakdown')

@section('content')
<div class="container-fluid">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <h1 class="h3 mb-0">Expense Category Breakdown</h1>
      <div class="text-muted">From {{ $from }} to {{ $to }} (Total: {{ number_format((float)$grand, 2) }})</div>
    </div>

    <form method="GET" action="{{ route('reports.expense_category_breakdown') }}" class="row g-2 align-items-end">
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
        <label class="form-label">Top</label>
        <input class="form-control" type="number" name="top" value="{{ $topN }}" min="3" max="20" style="width:110px;">
      </div>
      <div class="col-auto">
        <button class="btn btn-primary" type="submit"><i class="fas fa-filter me-1"></i> Apply</button>
      </div>
    </form>
  </div>

  <div class="row">
    <div class="col-lg-5 mb-3">
      <div class="card">
        <div class="card-header"><strong>Pie (Top {{ $topN }} + Other)</strong></div>
        <div class="card-body">
          <canvas id="catPie" height="220"></canvas>
          @if(empty($chartLabels) || count($chartLabels) === 0)
            <div class="text-muted small mt-2">No expenses found for this period.</div>
          @endif
        </div>
      </div>
    </div>

    <div class="col-lg-7 mb-3">
      <div class="card">
        <div class="card-header"><strong>All Categories</strong></div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-striped mb-0">
              <thead>
                <tr>
                  <th>Category</th>
                  <th class="text-end" style="width:200px;">Total</th>
                  <th class="text-end" style="width:140px;">%</th>
                </tr>
              </thead>
              <tbody>
              @forelse($table as $r)
                <tr>
                  <td>{{ $r['category'] }}</td>
                  <td class="text-end">{{ number_format((float)$r['total'], 2) }}</td>
                  <td class="text-end">{{ number_format((float)$r['percent'], 1) }}%</td>
                </tr>
              @empty
                <tr><td colspan="3" class="text-muted p-3">No categories found for this period.</td></tr>
              @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

  </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const labels = @json($chartLabels);
  const totals = @json($chartTotals);

  if (labels && labels.length) {
    new Chart(document.getElementById('catPie'), {
      type: 'doughnut',
      data: {
        labels,
        datasets: [{ data: totals }]
      },
      options: {
        responsive: true,
        plugins: { legend: { position: 'bottom' } }
      }
    });
  }
});
</script>
@endsection
