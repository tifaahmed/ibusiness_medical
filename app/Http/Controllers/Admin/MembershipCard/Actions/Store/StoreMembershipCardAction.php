<?php

namespace App\Http\Controllers\Admin\MembershipCard\Actions\Store;

use App\Models\MembershipCard;
use Illuminate\Support\Facades\DB;

class StoreMembershipCardAction
{
    /**
     * Create one membership_card_patches row that reserves N numbers. It creates NO users or memberships.
     *
     * @return array{card: MembershipCard, memberships: array<int, array{id:int, membership_number:string, slug:string}>}
     */
    public function execute(array $validated, ?int $createdBy): array
    {
        return DB::transaction(function () use ($validated, $createdBy) {
            $quantity = (int) $validated['quantity'];
            $start = (int) $validated['start_number'];
            $prefix = (string) ($validated['prefix'] ?? '');

            // No users, no memberships: the batch only reserves the numbers. A membership that later takes one of
            // them is filed under the batch ({@see \App\Support\BatchNumbers::linkMembership()}).
            $generated = [];
            for ($i = 0; $i < $quantity; $i++) {
                $number = $prefix.(string) ($start + $i);
                $generated[] = [
                    'id' => null,
                    'membership_number' => $number,
                    'slug' => trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($number)), '-'),
                ];
            }

            $displayPrefix = (string) ($validated['display_prefix'] ?? '');
            $displayGroups = trim((string) ($validated['display_groups'] ?? ''));
            $card = MembershipCard::create([
                'batch_name' => $validated['batch_name'] ?? null,
                'prefix' => $prefix !== '' ? $prefix : null,
                'display_prefix' => $displayPrefix !== '' ? $displayPrefix : null,
                'display_groups' => $displayGroups !== '' ? $displayGroups : null,
                'layout_overrides' => $validated['layout_overrides'] ?? null,
                'quantity' => $quantity,
                'start_number' => $start,
                'membership_ids' => [],
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
