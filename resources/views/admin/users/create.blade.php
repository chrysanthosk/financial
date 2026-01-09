@extends('layouts.app')

@section('title', 'Create User')

@section('content-header')
<div class="d-flex justify-content-between align-items-center">
    <h1 class="m-0">Create User</h1>
    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left mr-1"></i> Back
    </a>
</div>
@endsection

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">User Details</h3>
    </div>

    <form method="POST" action="{{ route('admin.users.store') }}">
        @csrf
        <div class="card-body">

            <div class="form-group">
                <label>Name</label>
                <input name="name" value="{{ old('name') }}" class="form-control" required>
                @error('name') <small class="text-danger">{{ $message }}</small> @enderror
            </div>

            <div class="form-group">
                <label>Email</label>
                <input name="email" type="email" value="{{ old('email') }}" class="form-control" required>
                @error('email') <small class="text-danger">{{ $message }}</small> @enderror
            </div>

            <div class="form-group">
                <label>Role</label>
                <select name="role" class="form-control" required>
                    <option value="user" {{ old('role', 'user') === 'user' ? 'selected' : '' }}>User</option>
                    <option value="admin" {{ old('role') === 'admin' ? 'selected' : '' }}>Admin</option>
                </select>
                @error('role') <small class="text-danger">{{ $message }}</small> @enderror
            </div>

            <div class="form-group">
                <label>Password</label>
                <input name="password" type="password" class="form-control" required>
                @error('password') <small class="text-danger">{{ $message }}</small> @enderror
            </div>

            <div class="form-group">
                <label>Confirm password</label>
                <input name="password_confirmation" type="password" class="form-control" required>
            </div>

        </div>

        <div class="card-footer d-flex justify-content-end">
            <button class="btn btn-primary">
                <i class="fas fa-save mr-1"></i> Create
            </button>
        </div>
    </form>
</div>
@endsection
