@extends('layouts.app')

@section('title', 'Add Income')
@section('page-title', 'Add Income')

@section('content')
<div class="container-fluid">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">Add Income</h1>
    <a href="{{ route('income.index') }}" class="btn btn-outline-secondary btn-month">
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

  <div class="card">
    <div class="card-body">

      <form method="POST" action="{{ route('income.store') }}">
        @csrf

        <div class="row">
          <div class="col-md-3 mb-3">
            <label class="form-label" for="income_date">Date</label>
            <input
              id="income_date"
              type="date"
              name="income_date"
              class="form-control @error('income_date') is-invalid @enderror"
              value="{{ old('income_date', $defaultDate ?? now()->toDateString()) }}"
              required
            >
            @error('income_date') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-4 mb-3">
            <label class="form-label" for="income_source_id">Source</label>

            <select
              id="income_source_id"
              name="income_source_id"
              class="form-control @error('income_source_id') is-invalid @enderror"
              required
              @disabled(($sources ?? collect())->count() === 0)
            >
              <option value="" disabled @selected(old('income_source_id')===null)>
                {{ (($sources ?? collect())->count() === 0) ? 'No sources available' : 'Select source' }}
              </option>

              @foreach($sources as $s)
                <option value="{{ $s->id }}" @selected((int)old('income_source_id') === (int)$s->id)>
                  {{ $s->name }}
                </option>
              @endforeach
            </select>

            @error('income_source_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-3 mb-3">
            <label class="form-label" for="amount">Amount</label>
            <input
              id="amount"
              type="number"
              name="amount"
              step="0.01"
              min="0"
              class="form-control @error('amount') is-invalid @enderror"
              value="{{ old('amount') }}"
              required
            >
            @error('amount') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-12 mb-3">
            <label class="form-label" for="note">Note (optional)</label>
            <input
              id="note"
              type="text"
              name="note"
              maxlength="255"
              class="form-control @error('note') is-invalid @enderror"
              value="{{ old('note') }}"
              placeholder="Optional note..."
            >
            @error('note') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
          </div>
        </div>

        <div class="d-flex gap-2">
          <button class="btn btn-primary" type="submit" @disabled(($sources ?? collect())->count() === 0)>
            <i class="fas fa-save"></i> Save
          </button>

          <a href="{{ route('income.index') }}" class="btn btn-outline-secondary btn-month">
            Cancel
          </a>
        </div>
      </form>

    </div>
  </div>
</div>
@endsection
