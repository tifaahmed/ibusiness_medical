<?php

namespace App\Http\Controllers\Admin\User\Membership\Actions\Store;

use App\Enums\Membership\PaymentTypeEnum;
use App\Models\MemberLog;
use App\Models\MemberPayment;
use App\Models\User;
use App\Models\Membership;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class StoreUserMembershipAction
{
    public function __construct() {}

    /**
     * Execute the action to store a user with membership (essential data only).
     * Avatar and family members are processed in a background job.
     *
     * @param array $validated
     * @param User|null $admin Admin performing the action (for audit log).
     * @param Request|null $request Current request (for ip/user-agent).
     * @return array{user: User, membership: Membership, plainPassword: string}
     * @throws \Exception
     */
    public function execute(array $validated, ?User $admin = null, ?Request $request = null): array
    {
        DB::beginTransaction();

        try {
            Log::info('=== StoreUserMembershipAction: Starting ===', [
                'user_email' => $validated['email'] ?? null,
            ]);

            // Use provided email or null (no automatic generation)
            $email = !empty($validated['email']) ? $validated['email'] : null;

            // Generate membership number first — it doubles as the account password
            $membershipNumber = !empty($validated['membership_number']) 
                ? $validated['membership_number'] 
                : $this->generateUniqueMembershipNumber();

            // Create the user with password = membership_number
            $user = User::create([
                'name' => $validated['name'],
                'email' => $email,
                'phone' => $validated['phone'] ?? null,
                'password' => Hash::make($membershipNumber),
            ]);

            // Get is_active value from request, default to true if not provided
            $isActive = isset($validated['is_active']) ? (bool) $validated['is_active'] : true;

            // Get is_visible value from request, default to true if not provided
            $isVisible = isset($validated['is_visible']) ? (bool) $validated['is_visible'] : true;

            // Get is_paid value from request, default to false if not provided
            $isPaid = isset($validated['is_paid']) ? (bool) $validated['is_paid'] : false;

            // Payment type: null when unpaid, from request when paid (default monthly if partner)
            $paymentType = null;
            if ($isPaid) {
                $paymentType = $validated['payment_type'] ?? null;
                if (!$paymentType && !empty($validated['partner_id'])) {
                    $paymentType = PaymentTypeEnum::MONTHLY->value;
                }
            }

            // A membership is "completed" when its owner has a real name. Members
            // imported or staged without a name stay incomplete until the name is filled in.
            $completedAt = !empty(trim((string) ($validated['name'] ?? ''))) ? now() : null;

            // If setting as active, deactivate all existing memberships
            if ($isActive) {
                $user->memberships()->update(['is_active' => false]);
            }

            // Create the membership
            $membership = $user->memberships()->create([
                'membership_number' => $membershipNumber,
                'national_id' => $validated['national_id'] ?? null,
                'registration_date' => $validated['registration_date'] ?? now(),
                'expiration_date' => $validated['expiration_date'],
                'is_active' => $isActive,
                'is_visible' => $isVisible,
                'completed_at' => $completedAt,
                'is_paid' => $isPaid,
                'payment_type' => $paymentType,
                'job_title' => $validated['job_title'] ?? null,
                'company_id' => $validated['company_id'] ?? null,
                'partner_id' => $validated['partner_id'] ?? null,
                'sales_id' => $validated['sales_id'] ?? null,
                'governorate_id' => $validated['governorate_id'] ?? null,
                'city_id' => $validated['city_id'] ?? null,
                'created_by' => $admin?->id ?? Auth::id(),
            ]);

            // A paid membership created via the admin form can carry an initial
            // payment record, filled in the same "Payment" card shown on the
            // member-payment create form — mirrored here so both stay in sync.
            if ($isPaid && !empty($validated['initial_payment_months_paid']) && !empty($validated['initial_payment_from_date']) && !empty($validated['initial_payment_to_date'])) {
                $paymentCategory = $validated['initial_payment_type'] ?? 'commission';

                MemberPayment::create([
                    'membership_id' => $membership->id,
                    'amount' => $paymentCategory === 'free' ? 0 : ($validated['initial_payment_amount'] ?? 0),
                    'type' => $paymentCategory,
                    'months_paid' => $validated['initial_payment_months_paid'],
                    'from_date' => $validated['initial_payment_from_date'],
                    'to_date' => $validated['initial_payment_to_date'],
                    'notes' => $validated['initial_payment_notes'] ?? null,
                ]);
            }

            // Note: Avatar and family members are processed in a background job
            // to improve response time. The job will be dispatched after commit.

            MemberLog::record(
                membershipId: $membership->id,
                adminId: $admin?->id,
                action: MemberLog::ACTION_CREATED,
                oldValues: null,
                newValues: $this->snapshot($user, $membership),
                request: $request,
            );

            DB::commit();

            Log::info('User with membership created successfully', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'user_name' => $user->name,
                'membership_id' => $membership->id,
                'membership_number' => $membership->membership_number,
            ]);

            return [
                'user' => $user,
                'membership' => $membership,
                'plainPassword' => $membershipNumber,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('=== StoreUserMembershipAction: Failed ===', [
                'email' => $validated['email'] ?? null,
                'membership_number' => $validated['membership_number'] ?? null,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Build the audit snapshot for a membership + its owning user.
     */
    private function snapshot(User $user, Membership $membership): array
    {
        return [
            'user_name' => $user->name,
            'user_email' => $user->email,
            'user_phone' => $user->phone,
            'membership_number' => $membership->membership_number,
            'national_id' => $membership->national_id,
            'registration_date' => optional($membership->registration_date)->format('Y-m-d'),
            'expiration_date' => optional($membership->expiration_date)->format('Y-m-d'),
            'is_active' => (bool) $membership->is_active,
            'is_visible' => (bool) $membership->is_visible,
            'is_paid' => (bool) $membership->is_paid,
            'payment_type' => $membership->payment_type,
            'job_title' => $this->normalizeTranslatable($membership->getRawOriginal('job_title')),
            'company_id' => $membership->company_id,
            'partner_id' => $membership->partner_id,
            'governorate_id' => $membership->governorate_id,
            'city_id' => $membership->city_id,
        ];
    }

    /**
     * Normalize a translatable JSON value: drop null/empty translations,
     * collapse to null when nothing meaningful remains.
     */
    private function normalizeTranslatable(mixed $raw): ?array
    {
        if (empty($raw)) {
            return null;
        }
        $decoded = is_array($raw) ? $raw : json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }
        $filtered = array_filter($decoded, fn ($v) => $v !== null && $v !== '');
        return $filtered === [] ? null : $filtered;
    }

    /**
     * Generate a unique membership number.
     *
     * @return string
     */
    private function generateUniqueMembershipNumber(): string
    {
        $maxAttempts = 10;
        $attempt = 0;

        do {
            // Generate membership number: MEM-XXXXXX-YYYY-NN
            // Where XXXXXX is a random 6-digit number, YYYY is the year, NN is a random 2-digit number
            $randomNumber = str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
            $year = Carbon::now()->format('Y');
            $sequence = str_pad(rand(1, 99), 2, '0', STR_PAD_LEFT);
            
            $membershipNumber = "MEM-{$randomNumber}-{$year}-{$sequence}";
            
            $exists = Membership::where('membership_number', $membershipNumber)->exists();
            $attempt++;
        } while ($exists && $attempt < $maxAttempts);

        // If we still have a duplicate after max attempts, add timestamp to ensure uniqueness
        if ($exists) {
            $timestamp = Carbon::now()->format('His');
            $membershipNumber = "MEM-{$randomNumber}-{$year}-{$timestamp}";
        }

        return $membershipNumber;
    }
}

