<?php

namespace App\Http\Controllers;

use App\Enums\User\UserRoleEnum;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HomeRedirectController extends Controller
{
    /**
     * The site has no public landing pages: guests are always sent to the
     * login screen, and authenticated users are sent to whichever area they
     * belong to.
     *
     * This route keeps the `home` name because Laravel's `guest` middleware
     * falls back to it when redirecting an already-authenticated visitor away
     * from /login. It must therefore never send an authenticated user back to
     * /login, or the two would bounce off each other.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        /** @var \App\Models\User|null $user */
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->hasAnyRole([UserRoleEnum::SUPER_ADMIN, UserRoleEnum::ADMIN, UserRoleEnum::EDITOR])) {
            return redirect()->route('admin.dashboard');
        }

        $membership = $user->membership;

        if ($membership && $membership->slug) {
            return redirect()->route('guest.membership.show', $membership->slug);
        }

        // Authenticated but with nowhere to land (e.g. a member with no
        // membership record). End the session so the request finishes on the
        // login page instead of looping through the `guest` middleware.
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', __('auth.no_landing_page'));
    }
}
