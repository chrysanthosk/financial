@extends('layouts.app')

@section('title', 'Edit Emp. Income')

@section('content')
<div class="container-fluid">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">Edit Emp. Income</h1>
    <a href="{{ route('admin.emp_income.index') }}" class="btn btn-outline-secondary">
      <i class="fas fa-arrow-left"></i> Back
    </a>
  </div>

  @if($errors->any())
    <div class="alert alert-danger">Please fix the errors below.</div>
  @endif

  <div class="card">
    <div class="card-body">

      <form method="POST" action="{{ route('admin.emp_income.update', $row) }}">
        @csrf
        @method('PUT')

        <div class="row">
          <div class="col-md-5 mb-3">
            <label class="form-label">Employee</label>
            <select name="employee_id" class="form-control" required>
              @foreach($employees as $e)
                <option value="{{ $e->id }}" @selected((int)old('employee_id', $row->employee_id) === (int)$e->id)>{{ $e->name }}</option>
              @endforeach
            </select>
            @error('employee_id') <div class="text-danger small">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-2 mb-3">
            <label class="form-label">Month</label>
            <select name="month" class="form-control" required>
              @for($m=1; $m<=12; $m++)
                <option value="{{ $m }}" @selected((int)old('month', $row->month) === $m)>{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}</option>
              @endfor
            </select>
            @error('month') <div class="text-danger small">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-2 mb-3">
            <label class="form-label">Year</label>
            <input type="number" name="year" class="form-control" value="{{ old('year', $row->year) }}" min="2000" max="2100" required>
            @error('year') <div class="text-danger small">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-3 mb-3">
            <label class="form-label">Total Amount</label>
            <input type="number" step="0.01" min="0" name="total_amount" class="form-control" value="{{ old('total_amount', $row->total_amount) }}" required>
            @error('total_amount') <div class="text-danger small">{{ $message }}</div> @enderror
          </div>
        </div>

        <button class="btn btn-primary" type="submit">
          <i class="fas fa-save"></i> Save Changes
        </button>

      </form>

    </div>
  </div>

</div>
@endsection
