<?php

namespace App\Http\Controllers\Admin\User\Membership\Actions\Update;

use App\Enums\Membership\PaymentTypeEnum;
use App\Models\MemberLog;
use App\Models\MemberPayment;
use App\Models\User;
use App\Models\Membership;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UpdateUserMembershipAction
{
    public function __construct(
        private MediaService $mediaService
    ) {}

    /**
     * Execute the action to update a user with membership.
     *
     * @param User $user
     * @param array $validated
     * @param UploadedFile|null $avatar
     * @param User|null $admin Admin performing the action (for audit log).
     * @param Request|null $request Current request (for ip/user-agent).
     * @return array{user: User, membership: Membership, membershipWasCreated: bool, passwordUpdated: bool}
     * @throws \Exception
     */
    public function execute(
        User $user,
        array $validated,
        ?UploadedFile $avatar = null,
        ?User $admin = null,
        ?Request $request = null,
    ): array {
        DB::beginTransaction();

        try {
            $oldEmail = $user->email;
            $oldUserName = $user->name;
            $activeMembership = $user->memberships()->where('is_active', true)->first();
            $oldMembershipNumber = $activeMembership?->membership_number;
            // Match the edit resource ordering so the form and the action operate
            // on the same membership when the user has no active one.
            $existingMembershipForSnapshot = $activeMembership
                ?? $user->memberships()
                    ->orderBy('is_active', 'desc')
                    ->orderBy('created_at', 'desc')
                    ->first();
            $oldSnapshot = $existingMembershipForSnapshot
                ? $this->snapshot($oldUserName, $oldEmail, $existingMembershipForSnapshot)
                : null;
            $passwordUpdated = !empty($validated['password']);

            // Update the user
            $user->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
            ]);

            // Update password if provided
            if ($passwordUpdated) {
                $user->update([
                    'password' => Hash::make($validated['password']),
                ]);
            }

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

            // Mirror the create logic: a membership is "completed" when the owner's
            // name has a value. Filling in a previously empty name flips it to the current time.
            $completedAt = !empty(trim((string) ($validated['name'] ?? ''))) ? now() : null;

            // Debug logging
            Log::info('UpdateUserMembershipAction - Values received', [
                'is_active' => $isActive,
                'is_visible' => $isVisible,
                'validated_is_visible' => $validated['is_visible'] ?? 'NOT SET',
                'validated_is_active' => $validated['is_active'] ?? 'NOT SET',
            ]);

            // Update or create the membership
            $membershipWasCreated = false;
            $updatedMembership = null;

            // First, try to find the membership being edited (active or inactive)
            // This prevents duplicate key errors when updating an inactive membership.
            // Ordering must match the edit resource so we update the row the form was showing.
            $existingMembership = $activeMembership
                ?? $user->memberships()
                    ->orderBy('is_active', 'desc')
                    ->orderBy('created_at', 'desc')
                    ->first();

            if ($existingMembership) {
                // Update existing membership (whether active or not)
                $existingMembership->update([
                    'membership_number' => $validated['membership_number'],
                    'national_id' => $validated['national_id'] ?? null,
                    'registration_date' => $validated['registration_date'] ?? $existingMembership->registration_date,
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
                ]);

                $updatedMembership = $existingMembership;
                $membershipWasCreated = false;
            } else {
                // If creating new membership and it should be active, deactivate all existing
                if ($isActive) {
                    $user->memberships()->update(['is_active' => false]);
                }

                // Create new membership
                $membership = $user->memberships()->create([
                    'membership_number' => $validated['membership_number'],
                    'national_id' => $validated['national_id'] ?? null,
                    'registration_date' => $validated['registration_date'] ?? now(),
                    'expiration_date' => $validated['expiration_date'],
                    'is_active' => $isActive,
                    'is_visible' => $isVisible,
                    'completed_at' => $completedAt,
                    'is_paid' => $isPaid,
                    'job_title' => $validated['job_title'] ?? null,
                    'company_id' => $validated['company_id'] ?? null,
                    'partner_id' => $validated['partner_id'] ?? null,
                    'sales_id' => $validated['sales_id'] ?? null,
                    'governorate_id' => $validated['governorate_id'] ?? null,
                    'city_id' => $validated['city_id'] ?? null,
                ]);

                $updatedMembership = $membership;
                $membershipWasCreated = true;
            }

            // First member-payment row — same "Payment" card shown on the
            // member-payment create form, offered here only while the
            // membership is still incomplete and has never had a payment.
            if ($isPaid
                && $updatedMembership->memberPayments()->count() === 0
                && !empty($validated['initial_payment_months_paid'])
                && !empty($validated['initial_payment_from_date'])
                && !empty($validated['initial_payment_to_date'])
            ) {
                $paymentCategory = $validated['initial_payment_type'] ?? 'commission';

                MemberPayment::create([
                    'membership_id' => $updatedMembership->id,
                    'amount' => $paymentCategory === 'free' ? 0 : ($validated['initial_payment_amount'] ?? 0),
                    'type' => $paymentCategory,
                    'months_paid' => $validated['initial_payment_months_paid'],
                    'from_date' => $validated['initial_payment_from_date'],
                    'to_date' => $validated['initial_payment_to_date'],
                    'notes' => $validated['initial_payment_notes'] ?? null,
                ]);
            }

            // Handle avatar upload if provided
            if ($avatar) {
                $this->mediaService->uploadImage($user, $avatar, 'avatar');
            }

            $user->refresh();
            // Load the updated membership (whether active or not)
            $user->load('memberships');
            $activeMembership = $user->memberships->where('is_active', true)->first();

            $newSnapshot = $this->snapshot($user->name, $user->email, $updatedMembership->fresh());
            if ($passwordUpdated) {
                $oldSnapshot['password_changed'] = false;
                $newSnapshot['password_changed'] = true;
            }

            MemberLog::record(
                membershipId: $updatedMembership->id,
                adminId: $admin?->id,
                action: $membershipWasCreated ? MemberLog::ACTION_CREATED : MemberLog::ACTION_UPDATED,
                oldValues: $membershipWasCreated ? null : $oldSnapshot,
                newValues: $newSnapshot,
                request: $request,
            );

            DB::commit();

            Log::info('User and membership updated successfully', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'old_email' => $oldEmail,
                'membership_id' => $updatedMembership?->id,
                'membership_number' => $updatedMembership?->membership_number,
                'old_membership_number' => $oldMembershipNumber,
                'membership_created' => $membershipWasCreated,
                'password_updated' => $passwordUpdated,
                'is_active' => $isActive,
            ]);

            return [
                'user' => $user,
                'membership' => $activeMembership,
                'updatedMembership' => $updatedMembership?->fresh(),
                'membershipWasCreated' => $membershipWasCreated,
                'passwordUpdated' => $passwordUpdated,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to update user and membership', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'membership_number' => $validated['membership_number'] ?? null,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Build the audit snapshot for a membership + its owning user.
     */
    private function snapshot(?string $userName, ?string $userEmail, Membership $membership): array
    {
        return [
            'user_name' => $userName,
            'user_email' => $userEmail,
            'user_phone' => $membership->user?->phone,
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
            'sales_id' => $membership->sales_id,
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
     * Ensure only one active membership per user.
     * This mirrors the logic in the Membership model's boot method.
     *
     * @param User $user
     * @param int $activeMembershipId
     * @return void
     */
    private function ensureOnlyOneActiveMembership(User $user, int $activeMembershipId): void
    {
        // Deactivate all other memberships for this user
        Membership::where('user_id', $user->id)
            ->where('id', '!=', $activeMembershipId)
            ->update(['is_active' => false]);
    }
}

