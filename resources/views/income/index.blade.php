@extends('layouts.app')

@section('title', 'Income')

@section('content')
<div class="container-fluid">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">Income</h1>
    <a href="{{ route('income.create') }}" class="btn btn-primary">
      <i class="fas fa-plus"></i> Add Income
    </a>
  </div>

  <div class="card">
    <div class="card-header">
      <form method="GET" action="{{ route('income.index') }}" class="row g-2 align-items-end">
        <div class="col-md-3">
          <label class="form-label mb-1">Month</label>
          <input type="month" name="month" value="{{ $month }}" class="form-control">
        </div>

        <div class="col-md-9 d-flex gap-2">
          <button class="btn btn-outline-secondary" type="submit">
            <i class="fas fa-filter"></i> Apply
          </button>
          <a class="btn btn-outline-dark" href="{{ route('income.index') }}">
            Reset
          </a>

          <div class="ms-auto text-muted small d-flex align-items-center">
            Month total: <strong class="ms-2">{{ number_format((float)$monthTotal, 2) }}</strong>
          </div>
        </div>
      </form>
    </div>

    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover table-striped mb-0 align-middle">
          <thead>
            <tr>
              <th style="width:130px;">Date</th>

              @foreach($sources as $s)
                @php
                  $sid = (int)$s->id;
                  $total = (float)($colTotals[$sid] ?? 0);
                @endphp

                <th class="text-end" style="min-width:160px;">
                  {{ $s->name }} <span class="text-muted">({{ number_format($total, 2) }})</span>
                </th>
              @endforeach

              <th class="text-end" style="width:140px;">Total</th>
            </tr>
          </thead>

          <tbody>
            @if((float)$monthTotal <= 0)
              <tr>
                <td colspan="{{ count($sources) + 2 }}" class="text-center py-4 text-muted">No income found for this month.</td>
              </tr>
            @else
            @foreach($days as $d)
              <tr>
                <td class="text-nowrap">{{ $d }}</td>

                @foreach($sources as $s)
                  @php
                    $sid = (int)$s->id;
                    $amt = (float)($pivot[$d][$sid] ?? 0.0);
                    $id  = $cellId[$d][$sid] ?? null;
                    $note = $cellNote[$d][$sid] ?? '';
                  @endphp

                  <td class="text-end">
                    @if($amt > 0)
                      <div class="d-inline-flex align-items-center justify-content-end gap-2">
                        <span title="{{ $note !== '' ? e($note) : '' }}">
                          {{ number_format($amt, 2) }}
                        </span>

                        {{-- Edit --}}
                        @if($id)
                          <a href="{{ route('income.edit', $id) }}" class="btn btn-xs btn-outline-primary" title="Edit">
                            <i class="fas fa-edit"></i>
                          </a>

                          {{-- Delete --}}
                          <form action="{{ route('income.destroy', $id) }}" method="POST" class="d-inline"
                                onsubmit="return confirm('Delete this income entry? This cannot be undone.');">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-xs btn-outline-danger" type="submit" title="Delete">
                              <i class="fas fa-trash"></i>
                            </button>
                          </form>
                        @endif
                      </div>
                    @else
                      <span class="text-muted">—</span>
                    @endif
                  </td>
                @endforeach

                <td class="text-end fw-bold">
                  {{ number_format((float)($rowTotals[$d] ?? 0), 2) }}
                </td>
              </tr>
            @endforeach
            @endif
          </tbody>

          <tfoot>
            <tr>
              <th class="text-nowrap">Month total</th>

              @foreach($sources as $s)
                @php $sid = (int)$s->id; @endphp
                <th class="text-end">
                  {{ number_format((float)($colTotals[$sid] ?? 0), 2) }}
                </th>
              @endforeach

              <th class="text-end">
                {{ number_format((float)$monthTotal, 2) }}
              </th>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>

</div>
@endsection
