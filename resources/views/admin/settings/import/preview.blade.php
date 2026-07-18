@extends('layouts.app')

@section('title', $title ?? 'Preview Import')

@section('content')
<div class="container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h3 mb-0 text-gray-800">{{ $title ?? 'Preview Import' }}</h1>
        <a href="{{ route('tools.import.upload', ['type' => $type]) }}" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> Back
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    {{-- Summary cards --}}
    <div class="row mb-3">
        <div class="col-md-3 mb-2">
            <div class="card shadow">
                <div class="card-body">
                    <div class="text-muted">Total rows</div>
                    <div class="h4 mb-0">{{ $summary['total'] ?? 0 }}</div>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-2">
            <div class="card shadow">
                <div class="card-body">
                    <div class="text-muted">Valid</div>
                    <div class="h4 mb-0 text-success">{{ $summary['valid'] ?? 0 }}</div>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-2">
            <div class="card shadow">
                <div class="card-body">
                    <div class="text-muted">Invalid</div>
                    <div class="h4 mb-0 text-danger">{{ $summary['invalid'] ?? 0 }}</div>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-2">
            <div class="card shadow">
                <div class="card-body">
                    <div class="text-muted">Skipped (blank)</div>
                    <div class="h4 mb-0 text-warning">{{ $summary['skipped_blank'] ?? 0 }}</div>
                </div>
            </div>
        </div>
    </div>

    @if(!empty($hasMore))
        <div class="alert alert-warning">
            Showing first rows only. The import will process all rows.
        </div>
    @endif

    @if(($type ?? '') === 'income')
        <div class="alert alert-info">
            <strong>Note:</strong>
            For Income imports, a <em>zero amount is imported as a real 0.00 entry</em> (e.g. Revolut=0 on a day).
            Only <em>blank</em> cells are shown as <span class="badge bg-warning text-dark">SKIPPED</span>.
            Rows with real errors are <span class="badge bg-danger">INVALID</span>.
        </div>
    @else
        <div class="alert alert-info">
            <strong>Note:</strong>
            Unknown categories/methods will be set to <strong>Other</strong> automatically (so rows won’t fail the import).
        </div>
    @endif

    <div class="card shadow mb-3">
        <div class="card-header d-flex align-items-center justify-content-between">
            <strong>Preview</strong>
            <span class="text-muted small">First {{ count($rows ?? []) }} row(s)</span>
        </div>

        <div class="card-body table-responsive">
            <table class="table table-sm table-striped align-middle">
                <thead>
                <tr>
                    <th style="width:70px">Row</th>

                    @if(($type ?? '') === 'income')
                        <th style="width:140px">Source</th>
                        <th style="width:140px">Date</th>
                        <th style="width:140px">Amount</th>
                        <th>Status</th>
                    @else
                        <th style="width:140px">Date</th>
                        <th>Payee</th>
                        <th style="width:160px">Category</th>
                        <th style="width:160px">Method</th>
                        <th style="width:140px">Amount</th>
                        <th>Status</th>
                    @endif
                </tr>
                </thead>

                <tbody>
                @forelse(($rows ?? []) as $r)
                    @php
                        $errors = $r['errors'] ?? [];
                        $data = $r['data'] ?? [];
                        $isSkipped = (bool)($r['skipped'] ?? false);
                    @endphp

                    <tr class="{{ $isSkipped ? 'table-warning' : (!empty($errors) ? 'table-danger' : '') }}">
                        <td>{{ $r['row'] ?? '' }}</td>

                        @if(($type ?? '') === 'income')
                            <td>{{ $data['source_name'] ?? '' }}</td>
                            <td>{{ $data['income_date'] ?? '' }}</td>
                            <td>{{ isset($data['amount']) ? number_format((float)$data['amount'], 2, '.', '') : '' }}</td>
                        @else
                            <td>{{ $data['expense_date'] ?? '' }}</td>
                            <td>{{ $data['payee_name'] ?? '' }}</td>
                            <td>{{ $data['expense_category_name'] ?? '' }}</td>
                            <td>{{ $data['payment_method_name'] ?? '' }}</td>
                            <td>{{ isset($data['amount']) ? number_format((float)$data['amount'], 2, '.', '') : '' }}</td>
                        @endif

                        <td>
                            @if($isSkipped)
                                <span class="badge bg-warning text-dark">SKIPPED</span>
                                <div class="text-muted small mt-1">Blank amount</div>
                            @elseif(empty($errors))
                                <span class="badge bg-success">OK</span>
                            @else
                                <span class="badge bg-danger">INVALID</span>
                                <ul class="mb-0 ps-3 mt-1 text-danger">
                                    @foreach($errors as $e)
                                        <li>{{ $e }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="text-muted">No rows to preview.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <form method="POST" action="{{ route('tools.import.commit', ['type' => $type]) }}" class="d-flex gap-2">
        @csrf
        <button class="btn btn-success" type="submit">
            <i class="fas fa-save me-1"></i> Import Valid Rows
        </button>

        <a href="{{ route('tools.import.upload', ['type' => $type]) }}" class="btn btn-outline-secondary">
            Back to Upload
        </a>
    </form>
</div>
@endsection
