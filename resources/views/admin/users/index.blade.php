@extends('layouts.app')

@section('title', 'Users')

@section('content-header')
<div class="d-flex justify-content-between align-items-center">
    <h1 class="m-0">Users</h1>
    <a href="{{ route('admin.users.create') }}" class="btn btn-primary">
        <i class="fas fa-user-plus mr-1"></i> New User
    </a>
</div>
@endsection

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title mb-0">User Accounts</h3>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-hover table-striped mb-0 align-middle">
                <thead>
                    <tr>
                        <th style="width: 70px;">#</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th style="width: 140px;">Role</th>
                        <th style="width: 180px;">Created</th>
                        <th style="width: 220px;" class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($users as $u)
                    <tr>
                        <td>{{ $u->id }}</td>
                        <td>{{ $u->name }}</td>
                        <td>{{ $u->email }}</td>

                        <td>
                            {{-- Use theme-safe badge classes (CSS below will make it perfect in dark mode too) --}}
                            <span class="badge role-badge {{ $u->role === 'admin' ? 'role-badge-admin' : 'role-badge-user' }}">
                                {{ strtoupper($u->role) }}
                            </span>
                        </td>

                        <td>{{ optional($u->created_at)->format('Y-m-d H:i') }}</td>

                        <td class="text-right">
                            <a href="{{ route('admin.users.edit', $u) }}" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-edit mr-1"></i> Edit
                            </a>

                            {{-- Delete button opens modal --}}
                            <button type="button"
                                    class="btn btn-sm btn-outline-danger"
                                    data-toggle="modal"
                                    data-target="#deleteUserModal{{ $u->id }}"
                                    {{ auth()->id() === $u->id ? 'disabled' : '' }}
                                    title="{{ auth()->id() === $u->id ? 'You cannot delete your own account' : '' }}">
                                <i class="fas fa-trash mr-1"></i> Delete
                            </button>

                            {{-- Modal --}}
                            <div class="modal fade" id="deleteUserModal{{ $u->id }}" tabindex="-1" role="dialog" aria-labelledby="deleteUserModalLabel{{ $u->id }}" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered" role="document">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="deleteUserModalLabel{{ $u->id }}">Confirm deletion</h5>
                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>

                                        <div class="modal-body">
                                            <p class="mb-1">You are about to delete:</p>
                                            <div class="p-2 rounded bg-light">
                                                <strong>{{ $u->name }}</strong><br>
                                                <small class="text-muted">{{ $u->email }}</small>
                                            </div>

                                            <div class="mt-3 text-danger">
                                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                                This action cannot be undone.
                                            </div>
                                        </div>

                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>

                                            <form method="POST" action="{{ route('admin.users.destroy', $u) }}" class="m-0">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger">
                                                    <i class="fas fa-trash mr-1"></i> Delete user
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            {{-- /Modal --}}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No users found.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card-footer clearfix">
        {{ $users->onEachSide(1)->links() }}
    </div>
</div>
@endsection
