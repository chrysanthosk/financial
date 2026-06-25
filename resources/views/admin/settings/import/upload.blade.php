@extends('layouts.app')

@section('title', $title ?? 'Import')

@section('content')
<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">{{ $title ?? 'Import' }}</h1>

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="card shadow">
        <div class="card-body">
            <form method="POST" action="{{ route('tools.import.handle_upload', ['type' => $type]) }}" enctype="multipart/form-data">
                @csrf

                <div class="mb-3">
                    <label for="file" class="form-label"><strong>Excel/CSV File</strong></label>
                    <input type="file" class="form-control" id="file" name="file" accept=".xlsx,.xls,.csv" required>
                    <div class="form-text">Supported: .xlsx, .xls, .csv</div>
                </div>

                <div class="form-check mb-3">
                    <input type="checkbox" class="form-check-input" id="has_header" name="has_header" value="1" checked>
                    <label class="form-check-label" for="has_header">First row contains column names (header row)</label>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-upload me-1"></i> Upload & Map Columns
                </button>

                <a href="{{ route('tools.import.index') }}" class="btn btn-outline-secondary ms-2">
                    Cancel
                </a>
            </form>
        </div>
    </div>
</div>
@endsection
