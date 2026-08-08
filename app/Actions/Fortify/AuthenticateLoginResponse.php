<?php

namespace App\Actions\Fortify;

use App\Enums\User\UserRoleEnum;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Contracts\LoginResponse;

class AuthenticateLoginResponse implements LoginResponse
{
    /**
     * Create an HTTP response that represents the object.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function toResponse($request)
    {
        Log::info('=== Fortify Login Response Started ===', [
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'session_id' => $request->session()->getId(),
        ]);

        try {
            $user = $request->user();

            Log::info('Fortify: User retrieved from request', [
                'user_id' => $user?->id,
                'user_email' => $user?->email,
                'user_exists' => $user !== null,
            ]);

            if (!$user) {
                Log::error('Fortify: User not found in request', [
                    'session_id' => $request->session()->getId(),
                ]);
                $redirectUrl = route('home');
                Log::info('Fortify: Redirecting to home (no user)', [
                    'redirect_url' => $redirectUrl,
                    'route_exists' => Route::has('home'),
                ]);
                return redirect($redirectUrl);
            }

            Log::info('Fortify: User authenticated successfully', [
                'user_id' => $user->id,
                'user_email' => $user->email,
            ]);

            // Clear any intended URL
            $request->session()->forget('url.intended');
            Log::info('Fortify: Cleared intended URL');

            // Get user roles
            try {
                $userRoles = $user->roles->pluck('name')->toArray();
                Log::info('Fortify: User roles loaded', [
                    'user_id' => $user->id,
                    'roles' => $userRoles,
                    'has_admin_role' => $user->hasRole(UserRoleEnum::ADMIN),
                    'has_member_role' => $user->hasRole(UserRoleEnum::MEMBER),
                ]);
            } catch (\Exception $e) {
                Log::error('Fortify: Error loading user roles', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                    'error_trace' => $e->getTraceAsString(),
                ]);
                throw $e;
            }

            // Redirect based on user role
            if ($user->hasAnyRole([UserRoleEnum::SUPER_ADMIN, UserRoleEnum::ADMIN, UserRoleEnum::EDITOR])) {
                $redirectUrl = route('admin.dashboard');
                Log::info('Fortify: Redirecting admin user', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'redirect_url' => $redirectUrl,
                    'route_exists' => Route::has('admin.dashboard'),
                    'app_url' => config('app.url'),
                ]);

                $response = redirect($redirectUrl);
                Log::info('Fortify: Admin redirect response created', [
                    'target_url' => $redirectUrl,
                    'response_type' => get_class($response),
                ]);
                return $response;
            }

            // For members, redirect to their active membership page if available
            if ($user->hasRole(UserRoleEnum::MEMBER)) {
                try {
                    $activeMembership = $user->membership;
                    Log::info('Fortify: Checking member membership', [
                        'user_id' => $user->id,
                        'has_membership' => $activeMembership !== null,
                        'membership_id' => $activeMembership?->id,
                        'membership_slug' => $activeMembership?->slug,
                    ]);

                    if ($activeMembership && $activeMembership->slug) {
                        $redirectUrl = route('guest.membership.show', $activeMembership->slug);
                        Log::info('Fortify: Redirecting member to membership page', [
                            'user_id' => $user->id,
                            'redirect_url' => $redirectUrl,
                            'route_exists' => Route::has('guest.membership.show'),
                            'app_url' => config('app.url'),
                        ]);

                        $response = redirect($redirectUrl);
                        Log::info('Fortify: Member redirect response created', [
                            'target_url' => $redirectUrl,
                            'response_type' => get_class($response),
                        ]);
                        return $response;
                    }
                } catch (\Exception $e) {
                    Log::error('Fortify: Error checking member membership', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                        'error_trace' => $e->getTraceAsString(),
                    ]);
                }
            }

            // Default fallback to home page
            $redirectUrl = route('home');
            Log::info('Fortify: Redirecting to home page (fallback)', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'redirect_url' => $redirectUrl,
                'route_exists' => Route::has('home'),
                'app_url' => config('app.url'),
            ]);

            $response = redirect($redirectUrl);
            Log::info('Fortify: Fallback redirect response created', [
                'target_url' => $redirectUrl,
                'response_type' => get_class($response),
            ]);
            return $response;
        } catch (\Exception $e) {
            Log::error('Fortify: Unexpected error in login response', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        } finally {
            Log::info('=== Fortify Login Response Finished ===');
        }
    }
}


