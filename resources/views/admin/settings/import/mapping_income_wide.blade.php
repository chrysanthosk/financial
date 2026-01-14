@extends('layouts.app')

@section('title', $title ?? 'Map Income Columns')

@section('content')
<div class="container-fluid">
    <h1 class="h3 mb-3 text-gray-800">{{ $title ?? 'Map Income Columns' }}</h1>

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="alert alert-info">
        <strong>Income template (your format):</strong><br>
        One row contains a <em>Date</em> and multiple <em>amount columns</em> (Cash/Revolut/Visa/B).<br>
        The system will create <strong>one income entry per selected column</strong> where the amount is > 0.
        The <strong>Income Source</strong> is taken from the column header name.
        <hr class="my-2">
        <strong>Important:</strong> If your Date column does <u>not</u> include the year (e.g. “Wednesday, 1 January”),
        select the correct <strong>Year</strong> below.
    </div>

    <form method="POST" action="{{ route('tools.import.preview', ['type' => 'income']) }}">
        @csrf

        {{-- 0) Year (needed if dates have no year) --}}
        <div class="card shadow mb-3">
            <div class="card-header"><strong>0) Year</strong></div>
            <div class="card-body">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label"><strong>Year for this file</strong></label>
                        <input
                            type="number"
                            name="year"
                            class="form-control"
                            min="2000"
                            max="2100"
                            value="{{ $defaultYear ?? (int)date('Y') }}"
                            required
                        >
                        <div class="form-text mt-2">
                            Used only when the Date cells don’t contain a year.
                        </div>
                    </div>
                    <div class="col-md-9">
                        <div class="alert alert-light border mb-0">
                            <div class="small text-muted">
                                Example:
                                <span class="ms-1">If your file is “January 2025” and the Date is “Wednesday, 1 January”, choose <strong>2025</strong>.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- 1) Date column --}}
        <div class="card shadow mb-3">
            <div class="card-header"><strong>1) Select Date column</strong></div>
            <div class="card-body">
                <label class="form-label"><strong>Date column</strong></label>
                <select name="date_col" class="form-select" required id="date_col_select">
                    <option value="">-- choose --</option>
                    @foreach($headers as $col => $label)
                        @php
                            $labelStr = trim((string)$label);
                            $isLikelyDate = str_contains(mb_strtolower($labelStr), 'date');
                        @endphp
                        <option value="{{ $col }}" @selected($isLikelyDate)>
                            {{ strtoupper($col) }} — {{ $labelStr }}
                        </option>
                    @endforeach
                </select>
                <div class="form-text mt-2">
                    This must be the column that contains the date for each row.
                </div>
            </div>
        </div>

        {{-- 2) Source columns --}}
        <div class="card shadow mb-3">
            <div class="card-header"><strong>2) Select Income Source columns (amount columns)</strong></div>
            <div class="card-body">
                <div class="row">
                    @foreach($headers as $col => $label)
                        @php
                            $colU = strtoupper((string)$col);
                            $labelTrim = trim((string)$label);
                            $labelLower = mb_strtolower($labelTrim);

                            // Anything containing these should not be imported as a source
                            $isTotal = str_contains($labelLower, 'total') || str_contains($labelLower, 'sum');

                            // Common defaults
                            $defaultSources = ['cash', 'revolut', 'visa', 'b'];
                            $isDefaultSource = in_array($labelLower, $defaultSources, true);

                            // Try to avoid selecting Date column by default
                            $isDateHeader = str_contains($labelLower, 'date');
                        @endphp

                        <div class="col-md-4 mb-2">
                            <div class="form-check">
                                <input
                                    class="form-check-input source-col-checkbox"
                                    type="checkbox"
                                    name="source_cols[]"
                                    value="{{ $colU }}"
                                    id="src_{{ $colU }}"
                                    @checked($isDefaultSource && !$isTotal && !$isDateHeader)
                                    @disabled($isDateHeader)
                                >
                                <label class="form-check-label" for="src_{{ $colU }}">
                                    {{ $colU }} — {{ $labelTrim }}
                                    @if($isTotal)
                                        <span class="badge bg-secondary ms-2">skip</span>
                                    @endif
                                    @if($isDateHeader)
                                        <span class="badge bg-info ms-2">date</span>
                                    @endif
                                </label>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="form-text mt-2">
                    The importer matches Income Sources by column header name.
                    Make sure these exist in <strong>Settings → Configuration → Income Sources</strong>:
                    <strong>Cash</strong>, <strong>Revolut</strong>, <strong>Visa</strong>, <strong>B</strong>.
                    <br>
                    Columns containing <strong>Total</strong> / <strong>Sum</strong> are normally skipped.
                </div>
            </div>
        </div>

        {{-- Sample rows --}}
        <div class="card shadow">
            <div class="card-header"><strong>Sample rows</strong></div>
            <div class="card-body table-responsive">
                <table class="table table-sm table-striped align-middle">
                    <thead>
                    <tr>
                        @foreach($headers as $col => $label)
                            <th>{{ $label }}</th>
                        @endforeach
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($sampleRows as $r)
                        <tr>
                            @foreach($headers as $col => $label)
                                <td>{{ $r[$col] ?? '' }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td class="text-muted" colspan="{{ count($headers) }}">No sample rows.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-3">
            <button class="btn btn-primary" type="submit">
                <i class="fas fa-check me-1"></i> Validate & Preview
            </button>
            <a href="{{ route('tools.import.index') }}" class="btn btn-outline-secondary ms-2">Back</a>
        </div>
    </form>
</div>

{{-- Small helper: if user selects Date column, disable same column in source list --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    const dateSelect = document.getElementById('date_col_select');
    const checkboxes = Array.from(document.querySelectorAll('.source-col-checkbox'));

    function refreshDisable() {
        const dateCol = (dateSelect.value || '').toUpperCase();

        checkboxes.forEach(cb => {
            const col = (cb.value || '').toUpperCase();

            // If this checkbox corresponds to the chosen date col, disable + uncheck it
            if (dateCol && col === dateCol) {
                cb.checked = false;
                cb.disabled = true;
            } else {
                // keep disabled if it was disabled by server-side (date header badge)
                if (!cb.closest('.form-check').querySelector('.badge.bg-info')) {
                    cb.disabled = false;
                }
            }
        });
    }

    dateSelect.addEventListener('change', refreshDisable);
    refreshDisable();
});
</script>
@endsection
