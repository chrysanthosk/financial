@extends('layouts.app')

@section('title', $isEdit ? 'Edit Income' : 'Add Income')
@section('page-title', $isEdit ? 'Edit Income' : 'Add Income')

@section('content')
<div class="container-fluid">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">{{ $isEdit ? 'Edit Income' : 'Add Income' }}</h1>
    <a href="{{ route('income.index', ['month' => \Carbon\Carbon::parse($date)->format('Y-m')]) }}"
       class="btn btn-outline-secondary btn-month">
      <i class="fas fa-arrow-left"></i> Back
    </a>
  </div>

  @if ($errors->any())
    <div class="alert alert-danger">
      <strong>Please fix the errors below.</strong>
    </div>
  @endif

  @if(($sources ?? collect())->count() === 0)
    <div class="alert alert-warning">
      <strong>No income sources found.</strong><br>
      Please run the seeder (or add sources from Settings later). You cannot create an income entry until at least one source exists.
    </div>
  @endif

  <form method="POST" action="{{ route('income.store') }}">
    @csrf

    <div class="card mb-3">
      <div class="card-body">
        <div class="row">
          <div class="col-md-3">
            <label class="form-label" for="income_date">Date</label>
            <input
              id="income_date"
              type="date"
              name="income_date"
              class="form-control @error('income_date') is-invalid @enderror"
              value="{{ old('income_date', $date) }}"
              required
            >
            @error('income_date') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            <div class="form-text">
              Change the date and press Reload to load that day's amounts.
            </div>
          </div>

          <div class="col-md-3 d-flex align-items-start" style="padding-top:2rem;">
            <button type="button" class="btn btn-outline-secondary" id="reload-date">
              <i class="fas fa-rotate"></i> Reload
            </button>
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header d-flex align-items-center justify-content-between">
        <span>Amount per source</span>
        <span class="text-muted small">
          Day total: <strong id="day-total">0.00</strong>
        </span>
      </div>

      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-striped mb-0 align-middle">
            <thead>
              <tr>
                <th>Source</th>
                <th class="text-end" style="width:220px;">Amount</th>
              </tr>
            </thead>
            <tbody>
              @forelse($sources as $s)
                @php
                  $sid = (int) $s->id;
                  $value = old('amounts.'.$sid, $amounts[$sid] ?? '0.00');
                @endphp
                <tr>
                  <td>{{ $s->name }}</td>
                  <td class="text-end">
                    <input
                      type="number"
                      name="amounts[{{ $sid }}]"
                      id="amount_{{ $sid }}"
                      step="0.01"
                      min="0"
                      class="form-control text-end amount-input @error('amounts.'.$sid) is-invalid @enderror"
                      value="{{ $value }}"
                      required
                    >
                    @error('amounts.'.$sid) <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="2" class="text-center py-4 text-muted">No active income sources.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>

      <div class="card-body border-top">
        @error('amounts') <div class="alert alert-danger py-2">{{ $message }}</div> @enderror

        <label class="form-label" for="note">Note for this date (optional)</label>
        <input
          id="note"
          type="text"
          name="note"
          maxlength="255"
          class="form-control @error('note') is-invalid @enderror"
          value="{{ old('note', $note) }}"
          placeholder="Applies to every source saved for this date..."
        >
        @error('note') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
      </div>

      <div class="card-footer d-flex gap-2">
        <button class="btn btn-primary" type="submit" @disabled(($sources ?? collect())->count() === 0)>
          <i class="fas fa-save"></i> Save
        </button>

        <a href="{{ route('income.index', ['month' => \Carbon\Carbon::parse($date)->format('Y-m')]) }}"
           class="btn btn-outline-secondary btn-month">
          Cancel
        </a>
      </div>
    </div>
  </form>
</div>

<script>
  (function () {
    var dateInput = document.getElementById('income_date');
    var reloadBtn = document.getElementById('reload-date');
    var totalEl   = document.getElementById('day-total');

    function recalcTotal() {
      var sum = 0;
      document.querySelectorAll('.amount-input').forEach(function (el) {
        var v = parseFloat(el.value);
        if (!isNaN(v)) { sum += v; }
      });
      totalEl.textContent = sum.toFixed(2);
    }

    function reload() {
      if (!dateInput.value) { return; }
      window.location = '{{ route('income.create') }}?date=' + encodeURIComponent(dateInput.value);
    }

    if (reloadBtn) { reloadBtn.addEventListener('click', reload); }
    if (dateInput) { dateInput.addEventListener('change', reload); }

    document.querySelectorAll('.amount-input').forEach(function (el) {
      el.addEventListener('input', recalcTotal);
      // Select the pre-filled zero on focus so typing replaces it.
      el.addEventListener('focus', function () { el.select(); });
    });

    recalcTotal();
  })();
</script>
@endsection
