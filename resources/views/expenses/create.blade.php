@extends('layouts.app')

@section('title', 'Add Expense')

@section('content')
<div class="container-fluid">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">Add Expense</h1>
    <a href="{{ route('expenses.index') }}" class="btn btn-outline-secondary">Back</a>
  </div>

  <div class="card">
    <div class="card-body">

      @if ($errors->any())
        <div class="alert alert-danger">
          <strong>Please fix the errors below.</strong>
        </div>
      @endif

      <form method="POST" action="{{ route('expenses.store') }}">
        @csrf

        <div class="row">
          <div class="col-md-3 mb-3">
            <label class="form-label">Date</label>
            <input type="date" name="expense_date" class="form-control"
                   value="{{ old('expense_date', $defaultDate) }}" required>
            @error('expense_date') <div class="text-danger small">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-5 mb-3">
            <label class="form-label">Payee / Company</label>
            <input type="text" name="payee_name" maxlength="120" class="form-control"
                   value="{{ old('payee_name') }}" required>
            @error('payee_name') <div class="text-danger small">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-4 mb-3">
            <label class="form-label">Category</label>
            <select name="expense_category_id" class="form-control" required>
              <option value="" disabled @selected(old('expense_category_id')===null)>Select category</option>
              @foreach($categories as $c)
                <option value="{{ $c->id }}" @selected((int)old('expense_category_id') === (int)$c->id)>{{ $c->name }}</option>
              @endforeach
            </select>
            @error('expense_category_id') <div class="text-danger small">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-4 mb-3">
            <label class="form-label">Payment Method</label>
            <select name="payment_method_id" class="form-control" required>
              <option value="" disabled @selected(old('payment_method_id')===null)>Select method</option>
              @foreach($methods as $m)
                <option value="{{ $m->id }}" @selected((int)old('payment_method_id') === (int)$m->id)>{{ $m->name }}</option>
              @endforeach
            </select>
            @error('payment_method_id') <div class="text-danger small">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-3 mb-3">
            <label class="form-label">Amount</label>
            <input type="number" name="amount" step="0.01" min="0" class="form-control"
                   value="{{ old('amount') }}" required>
            @error('amount') <div class="text-danger small">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-5 mb-3">
            <label class="form-label">Cheque No (optional)</label>
            <input type="text" name="cheque_no" maxlength="80" class="form-control"
                   value="{{ old('cheque_no') }}">
            @error('cheque_no') <div class="text-danger small">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-12 mb-3">
            <label class="form-label">Reason (optional)</label>
            <input type="text" name="reason" maxlength="255" class="form-control"
                   value="{{ old('reason') }}">
            @error('reason') <div class="text-danger small">{{ $message }}</div> @enderror
          </div>

          {{-- NEW: Paid checkbox --}}
          <div class="col-md-12 mb-3">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="is_paid" name="is_paid" value="1"
                     @checked(old('is_paid', true))>
              <label class="form-check-label" for="is_paid">
                Paid
              </label>
            </div>
            @error('is_paid') <div class="text-danger small">{{ $message }}</div> @enderror
          </div>
        </div>

        <button class="btn btn-primary" type="submit">
          <i class="fas fa-save"></i> Save
        </button>
      </form>

    </div>
  </div>
</div>
@endsection
