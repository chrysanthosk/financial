<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'role'     => ['required', Rule::in(['admin', 'user'])],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'role'     => $validated['role'],
            'password' => Hash::make($validated['password']),
        ]);

        Audit::log(
            action: 'admin.user_created',
            category: 'admin',
            request: $request,
            userId: $request->user()?->id,
            targetType: 'User',
            targetId: (string)$user->id,
            meta: [
                'created_user_email' => $user->email,
                'role' => $user->role,
            ]
        );

        return redirect()->route('admin.users.index')->with('status', 'User created successfully.');
    }

    public function edit(User $user)
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role'     => ['required', Rule::in(['admin', 'user'])],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $before = [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
        ];

        $data = [
            'name'  => $validated['name'],
            'email' => $validated['email'],
            'role'  => $validated['role'],
        ];

        $passwordChanged = false;
        if (!empty($validated['password'])) {
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
            targetId: (string)$user->id,
            meta: [
                'changed' => [
                    'name' => $before['name'] !== $user->name,
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
        $deletedId = (string)$user->id;

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
