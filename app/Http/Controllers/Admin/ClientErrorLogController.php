<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClientErrorLogResource;
use App\Models\ClientErrorLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClientErrorLogController extends Controller
{
    public function index(Request $request): Response
    {
        $query = ClientErrorLog::query();

        if ($request->filled('platform') && $request->platform !== 'all') {
            $query->where('platform', $request->platform);
        }

        if ($request->filled('fatal') && $request->fatal !== 'all') {
            $query->where('fatal', $request->fatal === '1');
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('message', 'like', "%{$search}%")
                  ->orWhere('route', 'like', "%{$search}%");
            });
        }

        $logs = $query->latest()->paginate(20)->withQueryString();

        $stats = [
            'total' => ClientErrorLog::count(),
            'fatal' => ClientErrorLog::where('fatal', true)->count(),
            'today' => ClientErrorLog::whereDate('created_at', today())->count(),
        ];

        return Inertia::render('Admin/ClientErrorLogs/Index', [
            'logs' => ClientErrorLogResource::collection($logs),
            'stats' => $stats,
            'filters' => [
                'platform' => $request->platform ?? 'all',
                'fatal' => $request->fatal ?? 'all',
                'search' => $request->search ?? '',
            ],
        ]);
    }

    public function destroy(ClientErrorLog $clientErrorLog)
    {
        $clientErrorLog->delete();

        return redirect()->back()->with('success', 'Log entry deleted.');
    }
}
