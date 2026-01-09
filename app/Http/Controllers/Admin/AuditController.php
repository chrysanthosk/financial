<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $q = AuditLog::query()->with('user');

        // Filters
        $category = $request->string('category')->toString();
        $action   = $request->string('action')->toString();
        $userId   = $request->input('user_id');
        $ip       = $request->string('ip')->toString();
        $from     = $request->string('from')->toString(); // YYYY-MM-DD
        $to       = $request->string('to')->toString();   // YYYY-MM-DD
        $search   = $request->string('search')->toString();

        if ($category !== '') {
            $q->where('category', $category);
        }

        if ($action !== '') {
            // allow partial match (smtp., user., auth.)
            $q->where('action', 'like', '%' . $action . '%');
        }

        if (!empty($userId)) {
            $q->where('user_id', $userId);
        }

        if ($ip !== '') {
            $q->where('ip', 'like', '%' . $ip . '%');
        }

        if ($from !== '') {
            $q->whereDate('created_at', '>=', $from);
        }

        if ($to !== '') {
            $q->whereDate('created_at', '<=', $to);
        }

        if ($search !== '') {
            $q->where(function ($sub) use ($search) {
                $sub->where('action', 'like', '%' . $search . '%')
                    ->orWhere('category', 'like', '%' . $search . '%')
                    ->orWhere('target_type', 'like', '%' . $search . '%')
                    ->orWhere('target_id', 'like', '%' . $search . '%')
                    ->orWhere('ip', 'like', '%' . $search . '%');
            });
        }

        $logs = $q->orderByDesc('id')->paginate(30)->withQueryString();

        // Dropdown data
        $users = User::select('id', 'email', 'name')->orderBy('email')->get();

        // Common categories/actions (quick pick)
        $categories = AuditLog::select('category')->distinct()->orderBy('category')->pluck('category')->values();
        $actions    = AuditLog::select('action')->distinct()->orderBy('action')->pluck('action')->values();

        return view('admin.audit.index', [
            'logs' => $logs,
            'users' => $users,
            'categories' => $categories,
            'actions' => $actions,
        ]);
    }
}
