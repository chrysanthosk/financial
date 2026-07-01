@extends('layouts.app')

@section('title', 'Reports - Income Source by Year')
@section('page-title', 'Income Source by Year')

@section('content')
<div class="container-fluid">

  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
      <h1 class="h3 mb-0">Income Source by Year</h1>
      <div class="text-muted">
        Revenue per income source across years
        ({{ $endYear - $span + 1 }}–{{ $endYear }})
      </div>
    </div>
    <form method="GET" action="{{ route('reports.income_source_by_year') }}" class="d-flex gap-2">
      <input class="form-control" type="number" name="year" value="{{ $endYear }}" min="2000" max="2100" style="width:110px;" title="Latest year">
      <input class="form-control" type="number" name="years" value="{{ $span }}" min="1" max="50" style="width:90px;" title="Number of years">
      <button class="btn btn-primary" type="submit"><i class="fas fa-sync-alt me-1"></i> Update</button>
    </form>
  </div>

  <div class="card mb-3">
    <div class="card-header"><strong>Revenue by Source</strong></div>
    <div class="card-body">
      <canvas id="incomeSourceChart" height="110"></canvas>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><strong>Source × Year</strong></div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-striped mb-0">
          <thead>
            <tr>
              <th>Source</th>
              @foreach($years as $y)
                <th class="text-end">{{ $y }}</th>
              @endforeach
              <th class="text-end">Total</th>
            </tr>
          </thead>
          <tbody>
            @forelse($rows as $r)
              <tr>
                <td><strong>{{ $r['source'] }}</strong></td>
                @foreach($r['series'] as $val)
                  <td class="text-end">{{ number_format((float)$val, 2) }}</td>
                @endforeach
                <td class="text-end"><strong>{{ number_format((float)$r['total'], 2) }}</strong></td>
              </tr>
            @empty
              <tr><td colspan="{{ count($years) + 2 }}" class="text-center text-muted">No data</td></tr>
            @endforelse
          </tbody>
          <tfoot>
            <tr>
              <th>Total</th>
              @foreach($totalsByYear as $t)
                <th class="text-end">{{ number_format((float)$t, 2) }}</th>
              @endforeach
              <th class="text-end">{{ number_format((float)$grandTotal, 2) }}</th>
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
  const datasets = @json($datasets);

  new Chart(document.getElementById('incomeSourceChart'), {
    type: 'line',
    data: {
      labels,
      datasets: datasets.map(d => ({ ...d, tension: 0.25 }))
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
