@extends('layouts.app')

@section('title', 'Edit User')

@section('content-header')
<div class="d-flex justify-content-between align-items-center">
    <h1 class="m-0">Edit User</h1>
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

    <form method="POST" action="{{ route('admin.users.update', $user) }}">
        @csrf
        @method('PUT')

        <div class="card-body">

            <div class="form-group">
                <label>Name</label>
                <input name="name" value="{{ old('name', $user->name) }}" class="form-control" required>
                @error('name') <small class="text-danger">{{ $message }}</small> @enderror
            </div>

            <div class="form-group">
                <label>Email</label>
                <input name="email" type="email" value="{{ old('email', $user->email) }}" class="form-control" required>
                @error('email') <small class="text-danger">{{ $message }}</small> @enderror
            </div>

            <div class="form-group">
                <label>Role</label>
                <select name="role" class="form-control" required>
                    <option value="user" {{ old('role', $user->role) === 'user' ? 'selected' : '' }}>User</option>
                    <option value="admin" {{ old('role', $user->role) === 'admin' ? 'selected' : '' }}>Admin</option>
                </select>
                @error('role') <small class="text-danger">{{ $message }}</small> @enderror
            </div>

            <hr>

            <div class="form-group">
                <label>New password (optional)</label>
                <input name="password" type="password" class="form-control" placeholder="Leave blank to keep current password">
                @error('password') <small class="text-danger">{{ $message }}</small> @enderror
            </div>

            <div class="form-group">
                <label>Confirm new password</label>
                <input name="password_confirmation" type="password" class="form-control">
            </div>

        </div>

        <div class="card-footer d-flex justify-content-end">
            <button class="btn btn-primary">
                <i class="fas fa-save mr-1"></i> Save
            </button>
        </div>
    </form>
</div>
@endsection
