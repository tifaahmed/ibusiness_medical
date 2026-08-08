<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class MembershipCardDataUpdateController extends Controller
{
    public function __invoke(Request $request, string $membershipNumber): JsonResponse
    {
        $membership = Membership::with('company')->where('membership_number', $membershipNumber)->first();

        if (! $membership) {
            return response()->json(['message' => 'Membership not found.'], 404);
        }

        $data = $request->validate([
            'name'      => ['nullable', 'string', 'max:255'],
            'policy'    => ['nullable', 'string', 'max:255'],
            'member'    => ['nullable', 'string', 'max:255'],
            'member_ar' => ['nullable', 'string', 'max:255'],
            'member_en' => ['nullable', 'string', 'max:255'],
            'status'    => ['nullable', 'string', 'max:255'],
            'status_ar' => ['nullable', 'string', 'max:255'],
            'status_en' => ['nullable', 'string', 'max:255'],
            'valid'     => ['nullable', 'string', 'max:50'],
            'partner'   => ['nullable', 'integer', 'exists:partners,id'],
        ]);

        if (array_key_exists('name', $data) && $membership->user) {
            $membership->user->update(['name' => $data['name']]);
        }

        if (array_key_exists('policy', $data) && $data['policy'] !== $membership->membership_number) {
            $membership->update(['membership_number' => $data['policy']]);
        }

        $jobTitle = $membership->getTranslations('job_title');

        if (array_key_exists('member', $data)) {
            $jobTitle['ar'] = $data['member'] ?? $jobTitle['ar'] ?? '';
            $jobTitle['en'] = $data['member'] ?? $jobTitle['en'] ?? '';
        }
        if (array_key_exists('member_ar', $data)) {
            $jobTitle['ar'] = $data['member_ar'];
        }
        if (array_key_exists('member_en', $data)) {
            $jobTitle['en'] = $data['member_en'];
        }
        if (array_key_exists('member', $data) || array_key_exists('member_ar', $data) || array_key_exists('member_en', $data)) {
            $membership->update(['job_title' => $jobTitle]);
        }

        if ($membership->company) {
            $companyName = $membership->company->getTranslations('name');

            if (array_key_exists('status', $data)) {
                $companyName['ar'] = $data['status'] ?? $companyName['ar'] ?? '';
                $companyName['en'] = $data['status'] ?? $companyName['en'] ?? '';
            }
            if (array_key_exists('status_ar', $data)) {
                $companyName['ar'] = $data['status_ar'];
            }
            if (array_key_exists('status_en', $data)) {
                $companyName['en'] = $data['status_en'];
            }
            if (array_key_exists('status', $data) || array_key_exists('status_ar', $data) || array_key_exists('status_en', $data)) {
                $membership->company->update(['name' => $companyName]);
            }
        }

        if (array_key_exists('valid', $data)) {
            $parsed = $this->parseExpirationDate($data['valid']);
            if ($parsed) {
                $membership->update(['expiration_date' => $parsed]);
            }
        }

        if (array_key_exists('partner', $data)) {
            $membership->update(['partner_id' => $data['partner']]);
        }

        $membership->refresh();

        return response()->json([
            'message' => 'Updated successfully.',
            'membership' => [
                'membership_number' => $membership->membership_number,
                'expiration_date'  => $membership->expiration_date?->format('Y-m-d'),
                'partner_id'       => $membership->partner_id,
                'job_title'        => $membership->getTranslations('job_title'),
            ],
            'user' => $membership->user ? [
                'name' => $membership->user->name,
            ] : null,
        ]);
    }

    private function parseExpirationDate(string $value): ?Carbon
    {
        $trimmed = trim($value);
        if (preg_match('/^(\d{1,2})\s*\/\s*(\d{4})$/', $trimmed, $m)) {
            return Carbon::createFromDate((int) $m[2], (int) $m[1], 1)->endOfMonth();
        }
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $trimmed, $m)) {
            return Carbon::createFromDate((int) $m[1], (int) $m[2], (int) $m[3]);
        }
        try {
            return Carbon::parse($trimmed);
        } catch (\Exception) {
            return null;
        }
    }
}