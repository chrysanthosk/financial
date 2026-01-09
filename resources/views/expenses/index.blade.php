@extends('layouts.app')

@section('title', 'Expenses')

@section('content')
<div class="container-fluid">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">Expenses</h1>
    <a href="{{ route('expenses.create') }}" class="btn btn-primary">
      <i class="fas fa-plus"></i> Add Expense
    </a>
  </div>

  <div class="row">
    {{-- Main table --}}
    <div class="col-lg-9">
      <div class="card">
        <div class="card-header">
          <form method="GET" action="{{ route('expenses.index') }}" class="row g-2 align-items-end">

            {{-- Month --}}
            <div class="col-md-3">
              <label class="form-label mb-1">Month</label>
              <input type="month" name="month" value="{{ $month }}" class="form-control">
            </div>

            {{-- Category (slightly reduced width) --}}
            <div class="col-md-3">
              <label class="form-label mb-1">Category</label>
              <select name="category_id" class="form-control">
                <option value="">All</option>
                @foreach($categories as $c)
                  <option value="{{ $c->id }}" @selected((int)$categoryId === (int)$c->id)>{{ $c->name }}</option>
                @endforeach
              </select>
            </div>

            {{-- Payment Method (slightly reduced width) --}}
            <div class="col-md-3">
              <label class="form-label mb-1">Payment Method</label>
              <select name="method_id" class="form-control">
                <option value="">All</option>
                @foreach($methods as $m)
                  <option value="{{ $m->id }}" @selected((int)$methodId === (int)$m->id)>{{ $m->name }}</option>
                @endforeach
              </select>
            </div>

            {{-- Buttons (same row, next to Payment Method) --}}
            <div class="col-md-3 d-flex gap-2">
              <button class="btn btn-sm btn-outline-secondary btn-month d-inline-flex align-items-center gap-2 text-nowrap" type="submit">
                <i class="fas fa-filter"></i> Apply
              </button>

              <a class="btn btn-sm btn-outline-dark btn-month d-inline-flex align-items-center gap-2 text-nowrap" href="{{ route('expenses.index') }}">
                Reset
              </a>
            </div>

          </form>
        </div>

        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover table-striped mb-0 align-middle">
              <thead>
                <tr>
                  <th style="width:130px;">Date</th>
                  <th>Payee</th>
                  <th style="width:180px;">Category</th>
                  <th style="width:170px;">Method</th>
                  <th style="width:140px;" class="text-end">Amount</th>
                  <th style="width:160px;">Cheque No</th>
                  <th>Reason</th>
                  <th style="width:160px;" class="text-end">Actions</th>
                </tr>
              </thead>

              <tbody>
                @forelse($expenses as $e)
                  <tr>
                    <td class="text-nowrap">{{ $e->expense_date->format('Y-m-d') }}</td>
                    <td>{{ $e->payee_name }}</td>
                    <td>{{ $e->category?->name ?? '-' }}</td>
                    <td>{{ $e->method?->name ?? '-' }}</td>
                    <td class="text-end">{{ number_format((float)$e->amount, 2) }}</td>
                    <td class="text-muted">{{ $e->cheque_no ?: '—' }}</td>
                    <td class="text-muted">{{ $e->reason ?: '—' }}</td>
                    <td class="text-end">
                      <a href="{{ route('expenses.edit', $e) }}" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-edit"></i> Edit
                      </a>

                      <form action="{{ route('expenses.destroy', $e) }}" method="POST" class="d-inline"
                            onsubmit="return confirm('Delete this expense entry? This cannot be undone.');">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger" type="submit">
                          <i class="fas fa-trash"></i> Delete
                        </button>
                      </form>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="8" class="text-center py-4 text-muted">No expenses found for this month.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>

        @if($expenses->hasPages())
          <div class="card-footer">
            {{ $expenses->links() }}
          </div>
        @endif
      </div>
    </div>

    {{-- Summary --}}
    <div class="col-lg-3">
      <div class="card">
        <div class="card-header">
          <strong>Month Summary</strong>
        </div>
        <div class="card-body">

          <div class="d-flex justify-content-between mb-2">
            <span>Month total (filtered)</span>
            <strong>{{ number_format((float)$monthTotal, 2) }}</strong>
          </div>

          <hr>

          <div class="mb-2 text-muted">Totals per payment method (month)</div>
          @foreach($methods as $m)
            @php $t = (float)($totalsPerMethod[$m->id] ?? 0); @endphp
            <div class="d-flex justify-content-between">
              <span>{{ $m->name }}</span>
              <span>{{ number_format($t, 2) }}</span>
            </div>
          @endforeach

          <hr>

          <div class="text-muted small">
            * Categories & payment methods will be configurable from <strong>Settings</strong> later (we already store them in DB).
          </div>
        </div>
      </div>
    </div>

  </div>

</div>
@endsection
