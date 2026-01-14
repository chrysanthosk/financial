@extends('layouts.app')

@section('title', 'Import')

@section('content')
<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Tools / Import</h1>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="row">
        <div class="col-lg-6 mb-3">
            <div class="card shadow">
                <div class="card-header">
                    <strong>Import Income from Excel</strong>
                </div>
                <div class="card-body">
                    <p class="mb-3">Upload an Excel/CSV file, map columns, preview validation, then import.</p>
                    <a href="{{ route('tools.import.upload', ['type' => 'income']) }}" class="btn btn-primary">
                        Import Income
                    </a>
                </div>
            </div>
        </div>

        <div class="col-lg-6 mb-3">
            <div class="card shadow">
                <div class="card-header">
                    <strong>Import Expenses from Excel</strong>
                </div>
                <div class="card-body">
                    <p class="mb-3">Upload an Excel/CSV file, map columns, preview validation, then import.</p>
                    <a href="{{ route('tools.import.upload', ['type' => 'expenses']) }}" class="btn btn-primary">
                        Import Expenses
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-info mt-3">
        <strong>Tip:</strong> Make sure Income Sources, Expense Categories, and Payment Methods exist in Settings before importing.
        The importer matches by <em>name</em> (case-insensitive) or by numeric <em>ID</em> (expenses only).
    </div>
</div>
@endsection
