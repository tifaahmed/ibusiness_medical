<?php

namespace App\Http\Resources\Admin\MembershipCard\Show;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminMembershipCardShowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $ids = $this->membership_ids ?: [];

        $memberships = $this->relationLoaded('loadedMemberships')
            ? $this->loadedMemberships
            : ($this->resource->loadedMemberships ?? collect());

        return [
            'id' => $this->id,
            'batch_name' => $this->batch_name,
            'prefix' => $this->prefix,
            'display_prefix' => $this->display_prefix,
            'layout_overrides' => $this->layout_overrides,
            'quantity' => (int) $this->quantity,
            'start_number' => (int) $this->start_number,
            'end_number' => (int) $this->start_number + max(0, (int) $this->quantity - 1),
            'membership_ids' => $ids,
            'pdf_url' => $this->getFirstMediaUrl('pdf') ?: null,
            'has_pdf' => $this->getFirstMedia('pdf') !== null,
            'partner_logo_url' => $this->getFirstMediaUrl('partner_logo') ?: null,
            'memberships' => $memberships->map(fn ($m) => [
                'id' => $m->id,
                'membership_number' => $m->membership_number,
                'slug' => $m->slug,
                'completed_at' => $m->completed_at?->format('Y-m-d H:i:s'),
                'is_active' => (bool) $m->is_active,
                'is_visible' => (bool) $m->is_visible,
                'is_deleted' => $m->deleted_at !== null,
                'job_title' => $m->job_title,
                'registration_date' => $m->registration_date?->format('Y-m-d'),
                'expiration_date' => $m->expiration_date?->format('Y-m-d'),
                'created_at' => $m->created_at?->format('Y-m-d H:i:s'),
                'public_url' => route('guest.membership.show', $m->slug),
                'user' => $m->user ? [
                    'id' => $m->user->id,
                    'name' => $m->user->name,
                    'email' => $m->user->email,
                    'phone' => $m->user->phone,
                    'slug' => $m->user->slug,
                ] : null,
                'partner' => $m->partner ? [
                    'id' => $m->partner->id,
                    'title' => $m->partner->title,
                ] : null,
                'governorate' => $m->governorate ? [
                    'id' => $m->governorate->id,
                    'name' => $m->governorate->name,
                ] : null,
                'city' => $m->city ? [
                    'id' => $m->city->id,
                    'name' => $m->city->name,
                ] : null,
                'company' => $m->company ? [
                    'id' => $m->company->id,
                    'name' => $m->company->name,
                ] : null,
                'family_members' => $m->familyMembers->map(fn ($f) => [
                    'id' => $f->id,
                    'name' => $f->name,
                    'relationship' => $f->relationship?->value ?? $f->relationship,
                    'date_of_birth' => $f->date_of_birth?->format('Y-m-d'),
                    'phone' => $f->phone,
                    'email' => $f->email,
                    'is_active' => (bool) $f->is_active,
                ])->values(),
            ])->values(),
            'creator' => $this->relationLoaded('creator') && $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
                'email' => $this->creator->email,
            ] : null,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
