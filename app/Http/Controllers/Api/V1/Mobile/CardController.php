<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Api\V1\Mobile\Concerns\RespondsCompactly;
use App\Http\Controllers\Controller;
use App\Models\Membership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "See my card" by membership number, no sign-in — the app's second way in.
 *
 * Same trust model as the public member-card page on the web (the number is
 * the key), but the answer is cut down to what the card screen draws: no
 * email, no phone, no national id, no dates of birth. Throttled at the route
 * because the number is a guessable key.
 */
class CardController extends Controller
{
    use RespondsCompactly;

    public function show(Request $request, string $number): JsonResponse
    {
        $membership = Membership::visible()
            ->where(fn ($q) => $q->where('membership_number', $number)->orWhere('slug', $number))
            ->with('user:id,name')
            ->first();

        abort_unless($membership, 404);

        $members = $membership->familyMembers()
            ->where('is_active', true)
            ->orderBy('created_at')
            ->get(['id', 'name', 'relationship'])
            ->map(fn ($m) => ['id' => $m->id, 'name' => (string) $m->name, 'relationship' => $m->relationship->label()])
            ->values()
            ->all();

        // Never cached publicly: it is one person's card, keyed by a guessable number.
        return $this->respond($request, [
            'number' => $membership->membership_number,
            'active' => (bool) ($membership->is_active ?? false),
            'valid_from' => $membership->registration_date?->format('Y-m-d'),
            'valid_to' => $membership->expiration_date?->format('Y-m-d'),
            'holder' => (string) ($membership->user?->name ?? ''),
            'members' => $members,
        ], 0)->header('Cache-Control', 'private, no-store');
    }
}
