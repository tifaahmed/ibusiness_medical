<?php

namespace App\Http\Controllers\Admin\AdminUser\LedgerLimits;

use App\Http\Controllers\Controller;
use App\Models\AdminLedgerLimit;
use App\Models\User;
use App\Support\AdminLedgerLimits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Sets the monthly cap of an admin on each hand-written balance permission (add / take × points / wallet). Only the super admin sets
 * caps: the person being capped must not be able to lift their own. An empty value = unlimited.
 */
class AdminUserLedgerLimitsController extends Controller
{
    public function __invoke(Request $request, User $adminUser): RedirectResponse
    {
        abort_unless(AdminLedgerLimits::isSuper($request->user()), 403, 'Only the super admin can set monthly limits.');

        $data = Validator::make($request->all(), [
            'limits' => ['required', 'array'],
            'limits.*.mode' => ['required', 'in:inherit,unlimited,limit'],
            'limits.*.value' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
        ])->validate();

        foreach (AdminLedgerLimits::permissions() as $permission) {
            if (! isset($data['limits'][$permission])) {
                continue;
            }
            $mode = $data['limits'][$permission]['mode'];
            $value = $data['limits'][$permission]['value'] ?? null;
            $before = AdminLedgerLimit::query()->where('user_id', $adminUser->id)->where('permission', $permission)->first();

            if ($mode === 'inherit') {
                // No row of its own: the role's default (or no cap) applies.
                AdminLedgerLimit::query()->where('user_id', $adminUser->id)->where('permission', $permission)->delete();
            } elseif ($mode === 'limit' && ($value === null || $value === '')) {
                return back()->withErrors(['limits' => 'Enter the monthly amount, or choose Unlimited.']);
            } else {
                AdminLedgerLimit::query()->updateOrCreate(
                    ['user_id' => $adminUser->id, 'permission' => $permission],
                    ['monthly_limit' => $mode === 'unlimited' ? null : round((float) $value, 2), 'updated_by' => $request->user()->id],
                );
            }

            Log::info('Admin ledger limit set', ['by' => $request->user()->id, 'admin_id' => $adminUser->id, 'permission' => $permission, 'mode' => $mode, 'before' => $before?->monthly_limit, 'after' => $mode === 'limit' ? $value : null]);
        }

        return back()->with('success', 'Monthly limits saved.');
    }
}
