<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
     * Is this phone (users.phone) or national ID (memberships.national_id)
     * already taken? Soft-deleted rows do not count, matching the unique
     * rules on the store/update requests. Used by the admin form to warn
     * before save; the request rules remain the real gate.
     */
    public function checkField(Request $request): JsonResponse
    {
        $data = $request->validate([
            'field' => ['required', 'in:phone,national_id'],
            'value' => ['required', 'string', 'max:20'],
            'exclude_membership_id' => ['nullable', 'integer'],
            'exclude_user_id' => ['nullable', 'integer'],
        ]);

        try {
            if ($data['field'] === 'phone') {
                $user = User::query()
                    ->where('phone', $data['value'])
                    ->when($request->filled('exclude_user_id'), fn ($q) => $q->where('id', '!=', (int) $data['exclude_user_id']))
                    ->first();
                $membership = $user?->membership;
            } else {
                $membership = Membership::query()
                    ->where('national_id', $data['value'])
                    ->when($request->filled('exclude_membership_id'), fn ($q) => $q->where('id', '!=', (int) $data['exclude_membership_id']))
                    ->when($request->filled('exclude_user_id'), fn ($q) => $q->where('user_id', '!=', (int) $data['exclude_user_id']))
                    ->with('user')
                    ->first();
                $user = $membership?->user;
            }
        } catch (\Throwable $e) {
            Log::error('Member field uniqueness check failed', [
                'route' => $request->path(),
                'field' => $data['field'],
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['message' => 'Check failed'], 500);
        }

        if (!$user) {
            return response()->json(['unique' => true]);
        }

        return response()->json([
            'unique' => false,
            'member' => [
                'name' => $user->name,
                'phone' => $user->phone,
                'slug' => $user->slug,
                'membership_number' => $membership?->membership_number,
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
