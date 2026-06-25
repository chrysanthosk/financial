@extends('layouts.app')

@section('title', 'Edit Income')

@section('content')
<div class="container-fluid">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">Edit Income</h1>
    <a href="{{ route('income.index') }}" class="btn btn-outline-secondary">Back</a>
  </div>

  <div class="card">
    <div class="card-body">

      @if ($errors->any())
        <div class="alert alert-danger">
          <strong>Please fix the errors below.</strong>
        </div>
      @endif

      <form method="POST" action="{{ route('income.update', $income) }}">
        @csrf
        @method('PUT')

        <div class="row">
          <div class="col-md-3 mb-3">
            <label class="form-label" for="income_date">Date</label>
            <input id="income_date" type="date" name="income_date" class="form-control"
                   value="{{ old('income_date', $income->income_date->format('Y-m-d')) }}" required>
            @error('income_date') <div class="text-danger small">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-4 mb-3">
            <label class="form-label" for="income_source_id">Source</label>
            <select id="income_source_id" name="income_source_id" class="form-control" required>
              @foreach($sources as $s)
                <option value="{{ $s->id }}"
                  @selected((int)old('income_source_id', $income->income_source_id) === (int)$s->id)>{{ $s->name }}</option>
              @endforeach
            </select>
            @error('income_source_id') <div class="text-danger small">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-3 mb-3">
            <label class="form-label" for="amount">Amount</label>
            <input id="amount" type="number" name="amount" step="0.01" min="0" class="form-control"
                   value="{{ old('amount', (string)$income->amount) }}" required>
            @error('amount') <div class="text-danger small">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-12 mb-3">
            <label class="form-label" for="note">Note (optional)</label>
            <input id="note" type="text" name="note" maxlength="255" class="form-control"
                   value="{{ old('note', $income->note) }}">
            @error('note') <div class="text-danger small">{{ $message }}</div> @enderror
          </div>
        </div>

        <button class="btn btn-primary" type="submit">
          <i class="fas fa-save"></i> Update
        </button>
      </form>

    </div>
  </div>
</div>
@endsection
