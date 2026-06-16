<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $user      = auth()->user();
        $business  = $user->business;

        $logs = ActivityLog::forBusiness($business->id)
            ->with('user')
            ->when($request->action, fn($q) => $q->where('action', $request->action))
            ->when($request->user_id, fn($q) => $q->where('user_id', $request->user_id))
            ->when($request->date_from, fn($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->date_to,   fn($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->when($request->search, fn($q) => $q->where(function ($qq) use ($request) {
                $qq->where('description', 'like', '%' . $request->search . '%')
                   ->orWhere('subject_label', 'like', '%' . $request->search . '%');
            }))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        $users = \App\Models\User::forBusiness($business->id)
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        $actions = ActivityLog::forBusiness($business->id)
            ->distinct()
            ->pluck('action')
            ->sort()
            ->values();

        return view('audit.index', compact('logs', 'users', 'actions'));
    }
}
