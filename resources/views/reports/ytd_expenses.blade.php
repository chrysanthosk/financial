@extends('layouts.app')

@section('title', 'Reports - YTD Expenses')
@section('page-title', 'Year-to-Date Expenses')

@section('content')
<div class="container-fluid">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <h1 class="h3 mb-0">Year-to-Date Expenses ({{ $year }})</h1>
      <div class="text-muted">Total expenses + breakdown by payment type & category + chart by month</div>
    </div>

    <form method="GET" action="{{ route('reports.ytd_expenses') }}" class="d-flex gap-2">
      <input class="form-control" type="number" name="year" value="{{ $year }}" min="2000" max="2100" style="width:120px;">
      <button class="btn btn-primary" type="submit"><i class="fas fa-sync-alt me-1"></i> Update</button>
    </form>
  </div>

  <div class="row mb-3">
    <div class="col-lg-4 col-12 mb-2">
      <div class="small-box bg-warning text-white">
        <div class="inner">
          <h3 class="text-white">{{ number_format((float)$total, 2) }}</h3>
          <p class="text-white">Total Expenses (YTD)</p>
        </div>
        <div class="icon text-white"><i class="fas fa-receipt"></i></div>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-lg-6 mb-3">
      <div class="card">
        <div class="card-header"><strong>Breakdown by Payment Method</strong></div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-striped mb-0">
              <thead>
              <tr>
                <th>Method</th>
                <th class="text-end">Total</th>
              </tr>
              </thead>
              <tbody>
              @forelse($byMethod as $name => $amt)
                <tr>
                  <td>{{ $name }}</td>
                  <td class="text-end">{{ number_format((float)$amt, 2) }}</td>
                </tr>
              @empty
                <tr><td colspan="2" class="text-muted p-3">No expense records for this year.</td></tr>
              @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-6 mb-3">
      <div class="card">
        <div class="card-header"><strong>Breakdown by Category</strong></div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-striped mb-0">
              <thead>
              <tr>
                <th>Category</th>
                <th class="text-end">Total</th>
              </tr>
              </thead>
              <tbody>
              @forelse($byCategory as $name => $amt)
                <tr>
                  <td>{{ $name }}</td>
                  <td class="text-end">{{ number_format((float)$amt, 2) }}</td>
                </tr>
              @empty
                <tr><td colspan="2" class="text-muted p-3">No expense records for this year.</td></tr>
              @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-12 mb-3">
      <div class="card">
        <div class="card-header"><strong>Expenses by Month</strong></div>
        <div class="card-body">
          <canvas id="expenseMonthChart" height="110"></canvas>
        </div>
      </div>
    </div>
  </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', async function () {
    const Chart = await window.loadChart();
  const labels = @json($labels);
  const series = @json($byMonth);

  new Chart(document.getElementById('expenseMonthChart'), {
    type: 'bar',
    data: { labels, datasets: [{ label: 'Expenses', data: series }] },
    options: {
      responsive: true,
      plugins: { legend: { display: true } },
      scales: { y: { beginAtZero: true } }
    }
  });
});
</script>
@endsection
