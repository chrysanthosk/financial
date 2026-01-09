@extends('layouts.app')

@section('title', 'Dashboard')

@section('content-header')
<div class="row mb-2">
    <div class="col-sm-6">
        <h1 class="m-0">Dashboard</h1>
    </div>
</div>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-4 col-12">
        <div class="small-box bg-info">
            <div class="inner">
                <h3>0</h3>
                <p>Today Income</p>
            </div>
            <div class="icon">
                <i class="fas fa-coins"></i>
            </div>
            <a href="#" class="small-box-footer">Coming soon <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>

    <div class="col-lg-4 col-12">
        <div class="small-box bg-warning">
            <div class="inner">
                <h3>0</h3>
                <p>Today Expenses</p>
            </div>
            <div class="icon">
                <i class="fas fa-file-invoice-dollar"></i>
            </div>
            <a href="#" class="small-box-footer">Coming soon <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>

    <div class="col-lg-4 col-12">
        <div class="small-box bg-success">
            <div class="inner">
                <h3>0</h3>
                <p>Account Balance</p>
            </div>
            <div class="icon">
                <i class="fas fa-university"></i>
            </div>
            <a href="#" class="small-box-footer">Coming soon <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Welcome</h3>
    </div>
    <div class="card-body">
        You’re logged in. Next we’ll build Income / Expenses / Accounts screens (front-end first).
    </div>
</div>
@endsection
