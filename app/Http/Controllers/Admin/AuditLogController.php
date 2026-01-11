<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        // Dropdown: distinct categories
        $categories = AuditLog::query()
            ->whereNotNull('category')
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->values()
            ->all();

        // Dropdown: users (for filter)
        $users = User::query()
            ->select('id', 'email', 'first_name', 'last_name')
            ->orderBy('email')
            ->get();

        $query = AuditLog::query()
            ->with(['user:id,email,first_name,last_name'])
            ->orderByDesc('id');

        // Filters
        $category = (string) $request->query('category', '');
        if ($category !== '') {
            $query->where('category', $category);
        }

        $action = trim((string) $request->query('action', ''));
        if ($action !== '') {
            $query->where('action', 'like', '%' . $action . '%');
        }

        $userId = $request->query('user_id');
        if ($userId !== null && $userId !== '') {
            $query->where('user_id', (int) $userId);
        }

        $ip = trim((string) $request->query('ip', ''));
        if ($ip !== '') {
            $query->where('ip', 'like', '%' . $ip . '%');
        }

        // Date range (created_at)
        $from = (string) $request->query('from', '');
        if ($from !== '') {
            $query->whereDate('created_at', '>=', $from);
        }

        $to = (string) $request->query('to', '');
        if ($to !== '') {
            $query->whereDate('created_at', '<=', $to);
        }

        // Search in multiple fields
        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%")
                    ->orWhere('target_type', 'like', "%{$search}%")
                    ->orWhere('target_id', 'like', "%{$search}%")
                    ->orWhere('ip', 'like', "%{$search}%");
            });
        }

        $logs = $query->paginate(25)->withQueryString();

        return view('admin.audit.index', [
            'logs' => $logs,
            'categories' => $categories,
            'users' => $users,
        ]);
    }
}
