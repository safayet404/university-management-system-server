<?php

namespace App\Http\Controllers;

use App\Models\PageVisit;
use App\Traits\LogsPageVisit;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    use LogsPageVisit;

    // ── GET /api/activity-logs ────────────────────────────────
    public function index(Request $request)
    {
        self::logVisit('activity-logs', 'list', 'visited', 'Visited activity logs');

        $query = PageVisit::with('user:id,name,email,avatar')
            ->latest();

        // Filters
        if ($request->filled('user_id'))  $query->where('user_id', $request->user_id);
        if ($request->filled('module'))   $query->where('module', $request->module);
        if ($request->filled('action'))   $query->where('action', $request->action);
        if ($request->filled('ip'))       $query->where('ip_address', $request->ip);
        if ($request->filled('date_from')) $query->whereDate('created_at', '>=', $request->date_from);
        if ($request->filled('date_to'))   $query->whereDate('created_at', '<=', $request->date_to);
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q
                ->where('description', 'like', "%{$s}%")
                ->orWhere('module', 'like', "%{$s}%")
                ->orWhere('ip_address', 'like', "%{$s}%")
            );
        }

        $logs = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success'    => true,
            'data'       => $logs->items(),
            'pagination' => [
                'total'        => $logs->total(),
                'current_page' => $logs->currentPage(),
                'last_page'    => $logs->lastPage(),
            ],
        ]);
    }

    // ── GET /api/activity-logs/stats ─────────────────────────
    public function stats()
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'total_today'     => PageVisit::today()->count(),
                'total_week'      => PageVisit::thisWeek()->count(),
                'total_all'       => PageVisit::count(),
                'unique_users_today' => PageVisit::today()->distinct('user_id')->count('user_id'),
                'top_modules'     => PageVisit::selectRaw('module, count(*) as count')
                    ->groupBy('module')
                    ->orderByDesc('count')
                    ->limit(5)
                    ->get(),
                'top_users'       => PageVisit::selectRaw('user_id, count(*) as count')
                    ->with('user:id,name,email')
                    ->groupBy('user_id')
                    ->orderByDesc('count')
                    ->limit(5)
                    ->get(),
                'actions_today'   => PageVisit::today()
                    ->selectRaw('action, count(*) as count')
                    ->groupBy('action')
                    ->get(),
            ],
        ]);
    }

    // ── GET /api/activity-logs/modules ───────────────────────
    public function modules()
    {
        $modules = PageVisit::distinct('module')->pluck('module')->sort()->values();
        return response()->json(['success' => true, 'data' => $modules]);
    }

    // ── GET /api/activity-logs/user/{id} ──────────────────────
    public function userLogs(Request $request, $userId)
    {
        $logs = PageVisit::where('user_id', $userId)
            ->latest()
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success'    => true,
            'data'       => $logs->items(),
            'pagination' => [
                'total'        => $logs->total(),
                'current_page' => $logs->currentPage(),
                'last_page'    => $logs->lastPage(),
            ],
        ]);
    }
}
