<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Membership;

/** Locates a relative through the PRIMARY card's user slug and membership slug, as the admin routes spell it. */
trait FindsRelatives
{
    protected function findRelative(string $user, string $membership, string $relativeId): Membership
    {
        $relative = Membership::query()->relatives()
            ->with(['user', 'parent.user'])
            ->whereHas('parent', fn ($q) => $q->where('slug', $membership))
            ->whereHas('parent.user', fn ($q) => $q->where('slug', $user))
            ->whereKey($relativeId)
            ->firstOrFail();

        $this->assertCanManageMembership($relative->parent);

        return $relative;
    }

    /** @return array<string, mixed> */
    protected function relativeSnapshot(Membership $relative): array
    {
        return [
            'family_member_id' => $relative->id,
            'name' => $relative->user?->name,
            'relationship' => $relative->relationship,
            'date_of_birth' => $relative->user?->date_of_birth?->toDateString(),
            'phone' => $relative->user?->phone,
            'email' => $relative->user?->email,
            'national_id' => $relative->national_id,
            'is_active' => (bool) $relative->is_active,
            'approval_status' => $relative->approval_status,
        ];
    }
}
