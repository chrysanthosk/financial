@extends('layouts.app')

@section('title', 'Edit Expense')

@section('content')
<div class="container-fluid">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">Edit Expense</h1>
    <a href="{{ route('expenses.index') }}" class="btn btn-outline-secondary">Back</a>
  </div>

  <div class="card">
    <div class="card-body">

      @if ($errors->any())
        <div class="alert alert-danger">
          <strong>Please fix the errors below.</strong>
        </div>
      @endif

      <form method="POST" action="{{ route('expenses.update', $expense) }}">
        @csrf
        @method('PUT')

        <div class="row">
          <div class="col-md-3 mb-3">
            <label class="form-label" for="expense_date">Date</label>
            <input id="expense_date" type="date" name="expense_date" class="form-control"
                   value="{{ old('expense_date', $expense->expense_date->format('Y-m-d')) }}" required>
            @error('expense_date') <div class="text-danger small">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-5 mb-3">
            <label class="form-label" for="payee_name">Payee / Company</label>
            <input id="payee_name" type="text" name="payee_name" maxlength="120" class="form-control"
                   list="payee-suggestions" autocomplete="off"
                   value="{{ old('payee_name', $expense->payee_name) }}" required>
            <datalist id="payee-suggestions">
              @foreach($payees as $p)
                <option value="{{ $p }}"></option>
              @endforeach
            </datalist>
            @error('payee_name') <div class="text-danger small">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-4 mb-3">
            <label class="form-label" for="expense_category_id">Category</label>
            <select id="expense_category_id" name="expense_category_id" class="form-control" required>
              @foreach($categories as $c)
                <option value="{{ $c->id }}"
                  @selected((int)old('expense_category_id', $expense->expense_category_id) === (int)$c->id)>{{ $c->name }}</option>
              @endforeach
            </select>
            @error('expense_category_id') <div class="text-danger small">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-4 mb-3">
            <label class="form-label" for="payment_method_id">Payment Method</label>
            <select id="payment_method_id" name="payment_method_id" class="form-control" required>
              @foreach($methods as $m)
                <option value="{{ $m->id }}"
                  @selected((int)old('payment_method_id', $expense->payment_method_id) === (int)$m->id)>{{ $m->name }}</option>
              @endforeach
            </select>
            @error('payment_method_id') <div class="text-danger small">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-3 mb-3">
            <label class="form-label" for="amount">Amount</label>
            <input id="amount" type="number" name="amount" step="0.01" min="0" class="form-control"
                   value="{{ old('amount', (string)$expense->amount) }}" required>
            @error('amount') <div class="text-danger small">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-5 mb-3">
            <label class="form-label" for="cheque_no">Cheque No (optional)</label>
            <input id="cheque_no" type="text" name="cheque_no" maxlength="80" class="form-control"
                   value="{{ old('cheque_no', $expense->cheque_no) }}">
            @error('cheque_no') <div class="text-danger small">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-12 mb-3">
            <label class="form-label" for="reason">Reason (optional)</label>
            <input id="reason" type="text" name="reason" maxlength="255" class="form-control"
                   value="{{ old('reason', $expense->reason) }}">
            @error('reason') <div class="text-danger small">{{ $message }}</div> @enderror
          </div>

          {{-- NEW: Paid checkbox --}}
          <div class="col-md-12 mb-3">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="is_paid" name="is_paid" value="1"
                     @checked(old('is_paid', (bool)$expense->is_paid))>
              <label class="form-check-label" for="is_paid">
                Paid
              </label>
            </div>
            @error('is_paid') <div class="text-danger small">{{ $message }}</div> @enderror
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
