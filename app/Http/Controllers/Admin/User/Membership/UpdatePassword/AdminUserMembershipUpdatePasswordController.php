<?php

namespace App\Http\Controllers\Admin\User\Membership\UpdatePassword;

use App\Http\Controllers\Concerns\ScopesByMembershipCreator;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\User\Membership\UpdateMemberPasswordRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AdminUserMembershipUpdatePasswordController extends BaseController
{
    use ScopesByMembershipCreator;

    public function __invoke(UpdateMemberPasswordRequest $request, string $user): RedirectResponse
    {
        $validated = $request->validated();

        $targetUser = User::where('slug', $user)->firstOrFail();

        $this->assertCanManageUser($targetUser);

        $targetUser->update([
            'password' => Hash::make($validated['password']),
        ]);

        Log::info('Member password updated by admin', [
            'user_id' => $targetUser->id,
            'admin_id' => Auth::id(),
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'Password updated successfully.');
    }
}
