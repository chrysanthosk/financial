@extends('layouts.app')

@section('title', 'Reports - ' . ($title ?? 'Report'))
@section('page-title', $title ?? 'Report')

@section('content')
<div class="container-fluid">
  <div class="card">
    <div class="card-body">
      <h5 class="mb-1">{{ $title ?? 'Report' }}</h5>
      <div class="text-muted">This report is scaffolded and ready — next step is to implement the specific breakdowns/charts.</div>

      <div class="mt-3">
        <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary">
          <i class="fas fa-arrow-left me-1"></i> Back to Reports
        </a>
      </div>
    </div>
  </div>
</div>
@endsection
