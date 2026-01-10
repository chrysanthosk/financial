@extends('layouts.app')

@section('title', 'Bonus Calculator')

@section('content')
<div class="container-fluid">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">Bonus</h1>
  </div>

  @if ($errors->any())
    <div class="alert alert-danger">
      <strong>Please fix the errors below.</strong>
    </div>
  @endif

  <div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
      <strong>Monthly Bonus Calculator</strong>
      <span class="text-muted small">Flat rate based on band</span>
    </div>

    <div class="card-body">

      <form method="POST" action="{{ route('bonus.calculate') }}" class="mb-3">
        @csrf

        <div class="row align-items-end">
          <div class="col-md-6 mb-3">
            <label class="form-label">Monthly Amount</label>
            <input
              type="number"
              step="0.01"
              min="0"
              name="amount"
              class="form-control"
              value="{{ old('amount', $amount ?? '') }}"
              placeholder="e.g. 3500"
              required
            >
            @error('amount') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-3 mb-3">
            <button type="submit" class="btn btn-primary w-100">
              <i class="fas fa-calculator"></i> Calculate
            </button>
          </div>
        </div>
      </form>

      <div class="alert alert-secondary mb-0">
        <div class="fw-bold mb-2">Bonus Bands</div>
        <ul class="mb-0">
          <li>0 – 1600 → 0%</li>
          <li>1600 – 2800 → 3%</li>
          <li>2800 – 4000 → 4%</li>
          <li>4000 – 5200 → 5%</li>
          <li>5200+ → 6%</li>
        </ul>
      </div>

      @isset($bonus)
        <hr>
        <div class="row">
          <div class="col-md-4 mb-2">
            <div class="text-muted small">Band</div>
            <div class="fw-bold">{{ $band }}</div>
          </div>
          <div class="col-md-4 mb-2">
            <div class="text-muted small">Rate</div>
            <div class="fw-bold">{{ number_format(($rate ?? 0) * 100, 2) }}%</div>
          </div>
          <div class="col-md-4 mb-2">
            <div class="text-muted small">Bonus Amount</div>
            <div class="fw-bold">{{ number_format($bonus, 2) }}</div>
          </div>
        </div>
      @endisset

    </div>
  </div>

</div>
@endsection
