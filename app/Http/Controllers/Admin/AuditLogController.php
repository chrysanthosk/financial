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
        $q = AuditLog::query()->with('user')->orderByDesc('id');

        if ($request->filled('user_id')) {
            $q->where('user_id', (int)$request->input('user_id'));
        }

        if ($request->filled('action')) {
            $q->where('action', 'like', '%' . $request->input('action') . '%');
        }

        if ($request->filled('ip')) {
            $q->where('ip', 'like', '%' . $request->input('ip') . '%');
        }

        if ($request->filled('date_from')) {
            $q->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $q->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $logs = $q->paginate(25)->withQueryString();

        return view('admin.audit.index', [
            'logs' => $logs,
            'users' => User::query()->orderBy('name')->get(['id','name','email']),
        ]);
    }
}
