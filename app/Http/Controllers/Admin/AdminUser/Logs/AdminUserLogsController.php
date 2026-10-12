<?php

namespace App\Http\Controllers\Admin\AdminUser\Logs;

use App\Http\Controllers\Controller;
use App\Models\AdminUserLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserLogsController extends Controller
{
    public function __invoke(Request $request, int $userId): Response
    {
        $logs = AdminUserLog::query()
            ->with('admin:id,name,email')
            ->where('user_id', $userId)
            ->when($request->filled('action'), fn ($q) => $q->where('action', (string) $request->input('action')))
            ->latest('created_at')
            ->latest('id')
            ->paginate($request->integer('per_page', 20))
            ->withQueryString();

        $first = AdminUserLog::where('user_id', $userId)->latest('id')->first();

        $logs->getCollection()->transform(fn (AdminUserLog $log) => [
            'id' => $log->id,
            'action' => $log->action,
            'old_values' => $log->old_values,
            'new_values' => $log->new_values,
            'changed_fields' => $log->changed_fields ?? [],
            'ip_address' => $log->ip_address,
            'user_agent' => $log->user_agent,
            'created_at' => $log->created_at?->toDateTimeString(),
            // The name snapshot survives the admin being deleted.
            'admin' => $log->admin_id || $log->admin_name ? [
                'id' => $log->admin_id,
                'name' => $log->admin?->name ?? $log->admin_name,
                'email' => $log->admin?->email,
            ] : null,
        ]);

        $user = \App\Models\User::find($userId);

        return Inertia::render('Admin/AdminUser/Logs', [
            'subject' => [
                'id' => $userId,
                'name' => $user?->name ?? $first?->user_name,
                'email' => $user?->email ?? $first?->user_email,
                'exists' => $user !== null,
            ],
            'logs' => $logs->toArray(),
            'filters' => ['action' => $request->input('action')],
        ]);
    }
}
