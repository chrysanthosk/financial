@extends('layouts.app')

@section('title', 'Reports - YTD Income')
@section('page-title', 'Year-to-Date Income')

@section('content')
<div class="container-fluid">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <h1 class="h3 mb-0">Year-to-Date Income ({{ $year }})</h1>
      <div class="text-muted">Total income + breakdown by method/source + chart by month</div>
    </div>

    <form method="GET" action="{{ route('reports.ytd_income') }}" class="d-flex gap-2">
      <input class="form-control" type="number" name="year" value="{{ $year }}" min="2000" max="2100" style="width:120px;">
      <button class="btn btn-primary" type="submit"><i class="fas fa-sync-alt me-1"></i> Update</button>
    </form>
  </div>

  <div class="row mb-3">
    <div class="col-lg-4 col-12 mb-2">
      <div class="small-box bg-info">
        <div class="inner">
          <h3>{{ number_format((float)$total, 2) }}</h3>
          <p>Total Income (YTD)</p>
        </div>
        <div class="icon"><i class="fas fa-coins"></i></div>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-lg-6 mb-3">
      <div class="card">
        <div class="card-header"><strong>Breakdown by Method/Source</strong></div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-striped mb-0">
              <thead>
                <tr>
                  <th>Source</th>
                  <th class="text-end">Total</th>
                </tr>
              </thead>
              <tbody>
              @forelse($bySource as $name => $amt)
                <tr>
                  <td>{{ $name }}</td>
                  <td class="text-end">{{ number_format((float)$amt, 2) }}</td>
                </tr>
              @empty
                <tr><td colspan="2" class="text-muted p-3">No income records for this year.</td></tr>
              @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-6 mb-3">
      <div class="card">
        <div class="card-header"><strong>Income by Month</strong></div>
        <div class="card-body">
          <canvas id="incomeMonthChart" height="120"></canvas>
        </div>
      </div>
    </div>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  const labels = @json($labels);
  const series = @json($byMonth);

  new Chart(document.getElementById('incomeMonthChart'), {
    type: 'bar',
    data: {
      labels,
      datasets: [{ label: 'Income', data: series }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: true } },
      scales: { y: { beginAtZero: true } }
    }
  });
</script>
@endsection
