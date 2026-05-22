<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::orderByDesc('id')->paginate(20);

        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        return view('admin.users.create');
    }

    public function store(UserRequest $request)
    {
        $validated = $request->validated();

        $fullName = trim(($validated['first_name'] ?? '').' '.($validated['last_name'] ?? ''));

        $user = User::create([
            'first_name' => $validated['first_name'] ?? null,
            'last_name' => $validated['last_name'] ?? null,

            // Keep legacy "name" populated for navbar/compatibility
            'name' => $fullName !== '' ? $fullName : 'User',

            'email' => $validated['email'],
            'role' => $validated['role'],
            'password' => Hash::make($validated['password']),
        ]);

        Audit::log(
            action: 'admin.user_created',
            category: 'admin',
            request: $request,
            userId: $request->user()?->id,
            targetType: 'User',
            targetId: (string) $user->id,
            meta: [
                'created_user_email' => $user->email,
                'role' => $user->role,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
            ]
        );

        return redirect()->route('admin.users.index')->with('status', 'User created successfully.');
    }

    public function edit(User $user)
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(UserRequest $request, User $user)
    {
        $validated = $request->validated();

        $before = [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'role' => $user->role,
        ];

        $fullName = trim(($validated['first_name'] ?? '').' '.($validated['last_name'] ?? ''));

        $data = [
            'first_name' => $validated['first_name'] ?? null,
            'last_name' => $validated['last_name'] ?? null,

            // Keep legacy "name" updated too
            'name' => $fullName !== '' ? $fullName : ($user->name ?: 'User'),

            'email' => $validated['email'],
            'role' => $validated['role'],
        ];

        $passwordChanged = false;
        if (! empty($validated['password'])) {
            $data['password'] = Hash::make($validated['password']);
            $passwordChanged = true;
        }

        $user->update($data);

        Audit::log(
            action: 'admin.user_updated',
            category: 'admin',
            request: $request,
            userId: $request->user()?->id,
            targetType: 'User',
            targetId: (string) $user->id,
            meta: [
                'changed' => [
                    'first_name' => $before['first_name'] !== $user->first_name,
                    'last_name' => $before['last_name'] !== $user->last_name,
                    'email' => $before['email'] !== $user->email,
                    'role' => $before['role'] !== $user->role,
                    'password' => $passwordChanged,
                ],
                'new_role' => $user->role,
            ]
        );

        return redirect()->route('admin.users.index')->with('status', 'User updated successfully.');
    }

    public function destroy(Request $request, User $user)
    {
        // Don't allow deleting yourself
        if (auth()->id() === $user->id) {
            return back()->withErrors([
                'delete_user' => 'You cannot delete your own account.',
            ]);
        }

        // Don't allow deleting the last admin
        if ($user->role === 'admin') {
            $adminCount = User::where('role', 'admin')->count();
            if ($adminCount <= 1) {
                return back()->withErrors([
                    'delete_user' => 'You cannot delete the last admin user.',
                ]);
            }
        }

        $deletedEmail = $user->email;
        $deletedRole = $user->role;
        $deletedId = (string) $user->id;

        $user->delete();

        Audit::log(
            action: 'admin.user_deleted',
            category: 'admin',
            request: $request,
            userId: $request->user()?->id,
            targetType: 'User',
            targetId: $deletedId,
            meta: [
                'deleted_user_email' => $deletedEmail,
                'role' => $deletedRole,
            ]
        );

        return redirect()->route('admin.users.index')->with('status', 'User deleted successfully.');
    }
}
