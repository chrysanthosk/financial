@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="container-fluid">

    <div class="card mb-3">
        <div class="card-body">
            <h1 class="h3 mb-1">
                {{ $greeting }}, {{ $displayName }} 👋
            </h1>
            <p class="text-muted mb-0">
                You’re logged in. Next we’ll build Income / Expenses / Accounts screens (front-end first).
            </p>
        </div>
    </div>

    <div class="row">

        {{-- Today Income --}}
        <div class="col-lg-3 col-6">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3>{{ number_format((float)$todayIncome, 2) }}</h3>
                    <p>Today Income</p>
                </div>
                <div class="icon">
                    <i class="fas fa-coins"></i>
                </div>
                <a href="{{ route('income.index') }}" class="small-box-footer">
                    View Income <i class="fas fa-arrow-circle-right"></i>
                </a>
            </div>
        </div>

        {{-- Today Expenses --}}
        <div class="col-lg-3 col-6">
            <div class="small-box bg-danger">
                <div class="inner">
                    <h3>{{ number_format((float)$todayExpenses, 2) }}</h3>
                    <p>Today Expenses</p>
                </div>
                <div class="icon">
                    <i class="fas fa-file-invoice-dollar"></i>
                </div>
                <a href="{{ route('expenses.index') }}" class="small-box-footer">
                    View Expenses <i class="fas fa-arrow-circle-right"></i>
                </a>
            </div>
        </div>

        {{-- Month Income --}}
        <div class="col-lg-3 col-6">
            <div class="small-box bg-primary">
                <div class="inner">
                    <h3>{{ number_format((float)$monthIncome, 2) }}</h3>
                    <p>Total Month Income</p>
                </div>
                <div class="icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <a href="{{ route('income.index') }}" class="small-box-footer">
                    View Income <i class="fas fa-arrow-circle-right"></i>
                </a>
            </div>
        </div>

        {{-- Month Expenses --}}
        <div class="col-lg-3 col-6">
            <div class="small-box bg-warning text-white">
                <div class="inner">
                    <h3 class="text-white">{{ number_format((float)$monthExpenses, 2) }}</h3>
                    <p class="text-white">Total Month Expenses</p>
                </div>
                <div class="icon text-white">
                    <i class="fas fa-receipt"></i>
                </div>
                <a href="{{ route('expenses.index') }}" class="small-box-footer text-white">
                    View Expenses <i class="fas fa-arrow-circle-right"></i>
                </a>
            </div>
        </div>

    </div>
</div>
@endsection
