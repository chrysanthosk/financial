@extends('layouts.app')

@section('title', 'Recurring Expenses')

@section('content')
<div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h3 mb-0">Recurring Expenses</h1>
        <div class="d-flex gap-2">
            <a href="{{ route('expenses.index') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Back to Expenses
            </a>
            <a href="{{ route('expenses.recurring.create') }}" class="btn btn-primary">
                <i class="fas fa-plus"></i> New Template
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            @foreach($errors->all() as $msg)
                <div>{{ $msg }}</div>
            @endforeach
        </div>
    @endif

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Payee</th>
                            <th>Category</th>
                            <th>Method</th>
                            <th class="text-end" style="width:130px;">Amount</th>
                            <th class="text-center" style="width:90px;">Day</th>
                            <th class="text-center" style="width:120px;">Auto</th>
                            <th class="text-center" style="width:100px;">Active</th>
                            <th style="width:160px;">Last generated</th>
                            <th class="text-end" style="width:280px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($templates as $t)
                        <tr>
                            <td><strong>{{ $t->name }}</strong></td>
                            <td>{{ $t->payee_name }}</td>
                            <td>{{ $t->category?->name ?? '-' }}</td>
                            <td>{{ $t->method?->name ?? '-' }}</td>
                            <td class="text-end">{{ number_format((float)$t->amount, 2) }}</td>
                            <td class="text-center">{{ $t->day_of_month }}</td>
                            <td class="text-center">
                                @if($t->auto_create)
                                    <span class="badge bg-info">Auto</span>
                                @else
                                    <span class="badge bg-secondary">Manual</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($t->is_active)
                                    <span class="badge bg-success">Yes</span>
                                @else
                                    <span class="badge bg-secondary">No</span>
                                @endif
                            </td>
                            <td class="text-muted">
                                {{ $t->last_generated_on?->format('Y-m-d') ?? '—' }}
                            </td>
                            <td class="text-end">
                                <form action="{{ route('expenses.recurring.insert', $t) }}" method="POST" class="d-inline"
                                      onsubmit="return confirm('Create an expense for today from this template?');">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-success" type="submit" @disabled(!$t->is_active)>
                                        <i class="fas fa-plus"></i> Insert
                                    </button>
                                </form>

                                <a href="{{ route('expenses.recurring.edit', $t) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-edit"></i> Edit
                                </a>

                                <form action="{{ route('expenses.recurring.destroy', $t) }}" method="POST" class="d-inline"
                                      onsubmit="return confirm('Delete this template? Already-generated expenses are kept.');">
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
                            <td colspan="10" class="text-center py-4 text-muted">
                                No recurring templates yet. Create one for rent, subscriptions, etc.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card-footer text-muted small">
            <i class="fas fa-info-circle"></i>
            Templates with <strong>Auto</strong> on are materialized once per month by the scheduler
            (daily 02:30 server time) on or after their configured day-of-month.
            Manual templates are inserted via the <strong>Insert</strong> button.
        </div>
    </div>

</div>
@endsection
