<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\StaffActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $query = StaffActivityLog::with('user')
            ->orderByDesc('created_at');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('description', 'like', "%{$search}%");
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $logs = $query->paginate(50)->withQueryString();

        $managers = User::orderBy('name')->get(['id', 'name']);

        $actions = StaffActivityLog::select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        return view('staff.activity-logs.index', [
            'logs' => $logs,
            'managers' => $managers,
            'actions' => $actions,
            'filters' => $request->only(['user_id', 'action', 'search', 'date_from', 'date_to']),
        ]);
    }
}
