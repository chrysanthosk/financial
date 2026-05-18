@extends('layouts.app')

@section('title', 'New Recurring Template')

@section('content')
<div class="container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h3 mb-0">New Recurring Template</h1>
        <a href="{{ route('expenses.recurring.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>

    <div class="card">
        <div class="card-body">

            @if ($errors->any())
                <div class="alert alert-danger">
                    <strong>Please fix the errors below.</strong>
                </div>
            @endif

            <form method="POST" action="{{ route('expenses.recurring.store') }}">
                @csrf

                <div class="row">
                    <div class="col-md-5 mb-3">
                        <label class="form-label">Template Name</label>
                        <input type="text" name="name" maxlength="120" class="form-control"
                               value="{{ old('name') }}" placeholder="e.g. Rent, Netflix" required>
                        @error('name') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-5 mb-3">
                        <label class="form-label">Payee / Company</label>
                        <input type="text" name="payee_name" maxlength="120" class="form-control"
                               list="payee-suggestions" autocomplete="off"
                               value="{{ old('payee_name') }}" required>
                        <datalist id="payee-suggestions">
                            @foreach($payees as $p)
                                <option value="{{ $p }}"></option>
                            @endforeach
                        </datalist>
                        @error('payee_name') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-2 mb-3">
                        <label class="form-label">Amount</label>
                        <input type="number" name="amount" step="0.01" min="0" class="form-control"
                               value="{{ old('amount') }}" required>
                        @error('amount') <div class="text-danger small">{{ $message }}</div> @enderror
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

                    <div class="col-md-2 mb-3">
                        <label class="form-label">Day of Month</label>
                        <input type="number" name="day_of_month" min="1" max="28" class="form-control"
                               value="{{ old('day_of_month', 1) }}" required>
                        @error('day_of_month') <div class="text-danger small">{{ $message }}</div> @enderror
                        <small class="text-muted">1–28</small>
                    </div>

                    <div class="col-md-2 mb-3 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="auto_create" name="auto_create" value="1"
                                   @checked(old('auto_create'))>
                            <label class="form-check-label" for="auto_create">Auto-create</label>
                        </div>
                    </div>

                    <div class="col-md-5 mb-3">
                        <label class="form-label">Cheque No (optional)</label>
                        <input type="text" name="cheque_no" maxlength="80" class="form-control"
                               value="{{ old('cheque_no') }}">
                    </div>

                    <div class="col-md-7 mb-3">
                        <label class="form-label">Reason (optional)</label>
                        <input type="text" name="reason" maxlength="255" class="form-control"
                               value="{{ old('reason') }}">
                    </div>

                    <div class="col-md-3 mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_paid_default" name="is_paid_default" value="1"
                                   @checked(old('is_paid_default', true))>
                            <label class="form-check-label" for="is_paid_default">Mark as Paid when created</label>
                        </div>
                    </div>

                    <div class="col-md-3 mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                                   @checked(old('is_active', true))>
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>
                    </div>
                </div>

                <button class="btn btn-primary" type="submit">
                    <i class="fas fa-save"></i> Save Template
                </button>
            </form>

        </div>
    </div>
</div>
@endsection
