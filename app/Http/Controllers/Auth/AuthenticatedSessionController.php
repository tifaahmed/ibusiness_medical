<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Enums\User\UserRoleEnum;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        Log::info('=== Login Request Started ===', [
            'email' => $request->input('email'),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'has_remember' => $request->boolean('remember'),
        ]);

        try {
            Log::info('Login: Attempting authentication', [
                'email' => $request->input('email'),
            ]);

            $request->authenticate();

            Log::info('Login: Authentication successful', [
                'email' => $request->input('email'),
            ]);

            $request->session()->regenerate();

            Log::info('Login: Session regenerated');

            $user = Auth::user();

            Log::info('Login: User retrieved from Auth', [
                'user_id' => $user?->id,
                'user_email' => $user?->email,
                'user_exists' => $user !== null,
            ]);

            if (!$user) {
                Log::error('Login: User not found after authentication', [
                    'email' => $request->input('email'),
                ]);
                return redirect()->route('home');
            }

            // Clear any intended URL to avoid redirecting to wrong paths
            $request->session()->forget('url.intended');

            Log::info('Login: Clearing intended URL');

            // Get user roles
            /** @var \App\Models\User $user */
            try {
                $userRoles = $user->roles->pluck('name')->toArray();
                Log::info('Login: User roles loaded', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'roles' => $userRoles,
                    'has_admin_role' => $user->hasRole(UserRoleEnum::ADMIN),
                    'has_member_role' => $user->hasRole(UserRoleEnum::MEMBER),
                ]);
            } catch (\Exception $e) {
                Log::error('Login: Error loading user roles', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                    'error_trace' => $e->getTraceAsString(),
                ]);
                throw $e;
            }

            // Redirect based on user role
            /** @var \App\Models\User $user */
            if ($user->hasAnyRole([UserRoleEnum::SUPER_ADMIN, UserRoleEnum::ADMIN, UserRoleEnum::EDITOR])) {
                $redirectUrl = route('admin.dashboard');
                Log::info('Login: Redirecting admin user', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'redirect_url' => $redirectUrl,
                    'route_exists' => Route::has('admin.dashboard'),
                    'app_url' => config('app.url'),
                ]);

                $response = redirect($redirectUrl);
                Log::info('Login: Admin redirect response created', [
                    'target_url' => $redirectUrl,
                ]);
                return $response;
            }

            // For members, redirect to their active membership page if available
            if ($user->hasRole(UserRoleEnum::MEMBER)) {
                try {
                    $activeMembership = $user->membership;
                    Log::info('Login: Checking member membership', [
                        'user_id' => $user->id,
                        'has_membership' => $activeMembership !== null,
                        'membership_id' => $activeMembership?->id,
                        'membership_slug' => $activeMembership?->slug,
                    ]);

                    if ($activeMembership && $activeMembership->slug) {
                        $redirectUrl = route('guest.membership.show', $activeMembership->slug);
                        Log::info('Login: Redirecting member to membership page', [
                            'user_id' => $user->id,
                            'redirect_url' => $redirectUrl,
                            'route_exists' => Route::has('guest.membership.show'),
                            'app_url' => config('app.url'),
                        ]);

                        $response = redirect($redirectUrl);
                        Log::info('Login: Member redirect response created', [
                            'target_url' => $redirectUrl,
                        ]);
                        return $response;
                    }
                } catch (\Exception $e) {
                    Log::error('Login: Error checking member membership', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                        'error_trace' => $e->getTraceAsString(),
                    ]);
                }
            }

            // Default fallback to home page
            $redirectUrl = route('home');
            Log::info('Login: Redirecting to home page (fallback)', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'redirect_url' => $redirectUrl,
                'route_exists' => Route::has('home'),
                'app_url' => config('app.url'),
            ]);

            $response = redirect($redirectUrl);
            Log::info('Login: Fallback redirect response created', [
                'target_url' => $redirectUrl,
            ]);
            return $response;
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Login: Validation exception', [
                'email' => $request->input('email'),
                'errors' => $e->errors(),
                'message' => $e->getMessage(),
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::error('Login: Unexpected error during login', [
                'email' => $request->input('email'),
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        } finally {
            Log::info('=== Login Request Finished ===', [
                'email' => $request->input('email'),
            ]);
        }
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
