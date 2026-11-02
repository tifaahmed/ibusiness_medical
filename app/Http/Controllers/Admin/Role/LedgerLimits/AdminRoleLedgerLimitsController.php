<?php

namespace App\Http\Controllers\Admin\Role\LedgerLimits;

use App\Http\Controllers\Controller;
use App\Models\RoleLedgerLimit;
use App\Support\AdminLedgerLimits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

/** Sets a role's monthly cap on each hand-written balance permission — the default for everyone holding the role. Super admin only. Empty = unlimited. */
class AdminRoleLedgerLimitsController extends Controller
{
    public function __invoke(Request $request, Role $role): RedirectResponse
    {
        abort_unless(AdminLedgerLimits::isSuper($request->user()), 403, 'Only the super admin can set monthly limits.');

        $data = Validator::make($request->all(), [
            'limits' => ['required', 'array'],
            'limits.*' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
        ])->validate();

        foreach (AdminLedgerLimits::permissions() as $permission) {
            if (! array_key_exists($permission, $data['limits'])) {
                continue;
            }
            $value = $data['limits'][$permission];
            $before = RoleLedgerLimit::query()->where('role_id', $role->id)->where('permission', $permission)->value('monthly_limit');

            RoleLedgerLimit::query()->updateOrCreate(
                ['role_id' => $role->id, 'permission' => $permission],
                ['monthly_limit' => $value === null || $value === '' ? null : round((float) $value, 2), 'updated_by' => $request->user()->id],
            );

            Log::info('Role ledger limit set', ['by' => $request->user()->id, 'role_id' => $role->id, 'permission' => $permission, 'before' => $before, 'after' => $value === '' ? null : $value]);
        }

        return back()->with('success', 'Monthly limits saved.');
    }
}
