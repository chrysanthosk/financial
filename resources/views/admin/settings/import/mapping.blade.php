@extends('layouts.app')

@section('title', $title ?? 'Mapping')

@section('content')
<div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h3 mb-0">{{ $title ?? 'Map Columns' }}</h1>
        <a href="{{ route('tools.import.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>Please fix the errors below.</strong>
        </div>
    @endif

    <div class="card shadow mb-3">
        <div class="card-body">
            <p class="mb-3">
                Select which Excel columns match each required field. You can change mappings if the template changes.
            </p>

            <form method="POST" action="{{ route('tools.import.preview', ['type' => $type]) }}">
                @csrf

                <div class="row">
                    <div class="col-lg-6">
                        <h5 class="mb-2">Required fields</h5>

                        @foreach(($requiredFields ?? []) as $key => $label)
                            <div class="mb-3">
                                <label class="form-label"><strong>{{ $label }}</strong></label>
                                <select name="map[{{ $key }}]" class="form-select" required>
                                    <option value="">-- Select column --</option>
                                    @foreach(($headers ?? []) as $col => $header)
                                        <option value="{{ $col }}" {{ old("map.$key") == $col ? 'selected' : '' }}>
                                            {{ $col }} — {{ $header }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endforeach
                    </div>

                    <div class="col-lg-6">
                        <h5 class="mb-2">Optional fields</h5>

                        @foreach(($optionalFields ?? []) as $key => $label)
                            <div class="mb-3">
                                <label class="form-label">{{ $label }}</label>
                                <select name="map[{{ $key }}]" class="form-select">
                                    <option value="">-- Not mapped --</option>
                                    @foreach(($headers ?? []) as $col => $header)
                                        <option value="{{ $col }}" {{ old("map.$key") == $col ? 'selected' : '' }}>
                                            {{ $col }} — {{ $header }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endforeach
                    </div>
                </div>

                <hr>

                <h5 class="mb-2">Sample preview (first rows)</h5>
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle">
                        <thead>
                            <tr>
                                @foreach(($headers ?? []) as $col => $header)
                                    <th>{{ $col }}<br><small class="text-muted">{{ $header }}</small></th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse(($sampleRows ?? []) as $r)
                                <tr>
                                    @foreach(($headers ?? []) as $col => $header)
                                        <td>{{ $r[$col] ?? '' }}</td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($headers ?? []) }}" class="text-muted">No sample rows found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <button class="btn btn-primary" type="submit">
                    <i class="fas fa-search me-1"></i> Validate & Preview
                </button>
            </form>
        </div>
    </div>

</div>
@endsection
