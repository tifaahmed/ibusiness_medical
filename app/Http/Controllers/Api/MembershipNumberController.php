<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MembershipNumberController extends Controller
{
    /**
     * Look up a membership by number and return the owning member's data
     * if it conflicts with another record (used by the edit form to warn
     * the admin before save).
     */
    public function check(Request $request): JsonResponse
    {
        $request->validate([
            'membership_number' => ['required', 'string'],
            'exclude_membership_id' => ['nullable', 'integer'],
            'exclude_user_id' => ['nullable', 'integer'],
        ]);

        $number = trim((string) $request->input('membership_number'));

        if ($number === '') {
            return response()->json(['unique' => true]);
        }

        $query = Membership::query()
            ->where('membership_number', $number)
            ->with(['user', 'company', 'partner']);

        if ($request->filled('exclude_membership_id')) {
            $query->where('id', '!=', (int) $request->input('exclude_membership_id'));
        }

        if ($request->filled('exclude_user_id')) {
            $query->where('user_id', '!=', (int) $request->input('exclude_user_id'));
        }

        $membership = $query->first();

        if (!$membership) {
            return response()->json(['unique' => true]);
        }

        $user = $membership->user;

        return response()->json([
            'unique' => false,
            'member' => [
                'id' => $user?->id,
                'name' => $user?->name,
                'email' => $user?->email,
                'phone' => $user?->phone,
                'slug' => $user?->slug,
                'avatar_url' => $user ? get_image_url($user, 'avatar') : null,
                'membership' => [
                    'id' => $membership->id,
                    'membership_number' => $membership->membership_number,
                    'slug' => $membership->slug,
                    'is_active' => (bool) $membership->is_active,
                    'is_visible' => (bool) $membership->is_visible,
                    'registration_date' => $membership->registration_date?->format('Y-m-d'),
                    'expiration_date' => $membership->expiration_date?->format('Y-m-d'),
                    'company_name' => $membership->company?->getTranslation('name', app()->getLocale())
                        ?: $membership->company?->getTranslation('name', 'en'),
                    'partner_name' => $membership->partner?->title,
                ],
            ],
        ]);
    }

    /**
     * Generate a unique 4-digit membership number.
     *
     * @return JsonResponse
     */
    public function generateUnique(): JsonResponse
    {
        $maxAttempts = 50;
        $attempt = 0;

        do {
            // Generate a 4-digit number (1000-9999)
            $membershipNumber = (string) rand(1000, 9999);

            // Check if it exists in the database
            $exists = Membership::where('membership_number', $membershipNumber)->exists();
            $attempt++;
        } while ($exists && $attempt < $maxAttempts);

        // If we still have a duplicate after max attempts, add current timestamp suffix
        if ($exists) {
            $timestamp = now()->format('His'); // Format: HHMMSS
            $membershipNumber = substr($timestamp, -4); // Take last 4 digits
        }

        return response()->json([
            'membership_number' => $membershipNumber
        ]);
    }
}
