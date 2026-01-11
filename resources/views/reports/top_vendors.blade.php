@extends('layouts.app')

@section('title', 'Reports - Top Vendors')
@section('page-title', 'Top Vendors')

@section('content')
<div class="container-fluid">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <h1 class="h3 mb-0">Top Vendors</h1>
      <div class="text-muted">By total spend from {{ $from }} to {{ $to }}</div>
    </div>

    <form method="GET" action="{{ route('reports.top_vendors') }}" class="row g-2 align-items-end">
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
        <input class="form-control" type="number" name="limit" value="{{ $limit }}" min="5" max="50" style="width:110px;">
      </div>
      <div class="col-auto">
        <button class="btn btn-primary" type="submit"><i class="fas fa-filter me-1"></i> Apply</button>
      </div>
    </form>
  </div>

  <div class="row">
    <div class="col-lg-6 mb-3">
      <div class="card">
        <div class="card-header"><strong>Top Vendors (Chart)</strong></div>
        <div class="card-body">
          <canvas id="vendorsChart" height="140"></canvas>
          @if(empty($chartLabels) || count($chartLabels) === 0)
            <div class="text-muted small mt-2">No expenses found for this period.</div>
          @endif
        </div>
      </div>
    </div>

    <div class="col-lg-6 mb-3">
      <div class="card">
        <div class="card-header"><strong>Top Vendors (Table)</strong></div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-striped mb-0">
              <thead>
                <tr>
                  <th>Vendor</th>
                  <th class="text-end" style="width:120px;">Tx</th>
                  <th class="text-end" style="width:180px;">Total</th>
                </tr>
              </thead>
              <tbody>
              @forelse($table as $r)
                <tr>
                  <td>{{ $r['payee'] }}</td>
                  <td class="text-end">{{ $r['count'] }}</td>
                  <td class="text-end">{{ number_format((float)$r['total'], 2) }}</td>
                </tr>
              @empty
                <tr><td colspan="3" class="text-muted p-3">No vendors found for this period.</td></tr>
              @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  const labels = @json($chartLabels);
  const totals = @json($chartTotals);

  if (labels && labels.length) {
    new Chart(document.getElementById('vendorsChart'), {
      type: 'bar',
      data: {
        labels,
        datasets: [{ label: 'Total Spend', data: totals }]
      },
      options: {
        responsive: true,
        plugins: { legend: { display: true } },
        scales: { y: { beginAtZero: true } }
      }
    });
  }
</script>
@endsection
