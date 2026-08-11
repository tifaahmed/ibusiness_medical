<?php

namespace App\Http\Controllers\Admin\MembershipCard\Actions\Store;

use App\Models\Membership;
use App\Models\MembershipCard;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class StoreMembershipCardAction
{
    /**
     * Create N placeholder users + memberships (code-only) and one
     * membership_card_patches row that owns the list of generated IDs.
     *
     * @return array{card: MembershipCard, memberships: array<int, array{id:int, membership_number:string, slug:string}>}
     */
    public function execute(array $validated, ?int $createdBy): array
    {
        return DB::transaction(function () use ($validated, $createdBy) {
            $quantity = (int) $validated['quantity'];
            $start = (int) $validated['start_number'];
            $prefix = (string) ($validated['prefix'] ?? '');

            $generated = [];

            for ($i = 0; $i < $quantity; $i++) {
                $number = $prefix.(string) ($start + $i);

                // Placeholder user — name/email/phone are null. completed_at
                // on the membership tracks the "still empty" state for the UI.
                $user = User::create([
                    'name' => null,
                    'email' => null,
                    'phone' => null,
                    'password' => Hash::make(Str::random(32)),
                ]);

                $membership = $user->memberships()->create([
                    'membership_number' => $number,
                    'registration_date' => null,
                    'expiration_date' => null,
                    'is_active' => false,
                    'is_visible' => false,
                    'completed_at' => null,
                    'partner_id' => $validated['partner_id'] ?? null,
                    'created_by' => $createdBy,
                ]);

                $generated[] = [
                    'id' => $membership->id,
                    'membership_number' => $membership->membership_number,
                    'slug' => $membership->slug,
                ];
            }

            $displayPrefix = (string) ($validated['display_prefix'] ?? '');
            $card = MembershipCard::create([
                'batch_name' => $validated['batch_name'] ?? null,
                'prefix' => $prefix !== '' ? $prefix : null,
                'display_prefix' => $displayPrefix !== '' ? $displayPrefix : null,
                'layout_overrides' => $validated['layout_overrides'] ?? null,
                'quantity' => $quantity,
                'start_number' => $start,
                'membership_ids' => array_column($generated, 'id'),
                'created_by' => $createdBy,
                'partner_id' => $validated['partner_id'] ?? null,
                'card_template_id' => $validated['card_template_id'] ?? null,
            ]);

            return [
                'card' => $card,
                'memberships' => $generated,
            ];
        });
    }
}
