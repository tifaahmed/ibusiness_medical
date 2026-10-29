<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** A relative cannot add relatives: they pay for their own card first (it then becomes a primary one). */
class EnsurePrimaryMember
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user?->memberships()->latest('id')->first()?->isRelative()) {
            return response()->json([
                'success' => false,
                'reason' => 'relative_cannot_add',
                'message' => 'A relative cannot add relatives. Subscribe to your own card to add your family.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
