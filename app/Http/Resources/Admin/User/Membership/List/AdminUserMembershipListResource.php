<?php

namespace App\Http\Resources\Admin\User\Membership\List;

use App\Models\MembershipCard;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminUserMembershipListResource extends JsonResource
{
    private static ?array $cardPatchBatchMap = null;

    private static function getCardPatchBatchMap(): array
    {
        if (self::$cardPatchBatchMap === null) {
            $map = [];
            MembershipCard::query()
                ->select('batch_name', 'membership_ids')
                ->lazyById()
                ->each(function (MembershipCard $card) use (&$map) {
                    $name = $card->batch_name;
                    if ($name === null) return;
                    foreach ($card->membership_ids ?? [] as $mId) {
                        $map[(int) $mId][] = $name;
                    }
                });
            self::$cardPatchBatchMap = $map;
        }
        return self::$cardPatchBatchMap;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'slug' => $this->slug,
            'avatar_url' => get_image_url($this->resource, 'avatar'),
            'membership' => $this->whenLoaded('memberships', function () use ($request) {
                // Get the first membership from loaded collection
                // The controller filters memberships based on is_active, so first() will be the correct one
                // If no memberships are loaded (shouldn't happen if filter is working), return null
                if ($this->memberships->isEmpty()) {
                    return null;
                }
                
                $membership = $this->memberships->first();
                
                if (!$membership) {
                    return null;
                }
                
                return [
                    'id' => $membership->id,
                    'card_patch_batch_names' => self::getCardPatchBatchMap()[(int) $membership->id] ?? [],
                    'membership_number' => $membership->membership_number,
                    'slug' => $membership->slug,
                    'registration_date' => $membership->registration_date?->format('Y-m-d H:i:s'),
                    'expiration_date' => $membership->expiration_date?->format('Y-m-d H:i:s'),
                    'registration_date_formatted' => $membership->registration_date?->format('Y-m-d\TH:i'),
                    'expiration_date_formatted' => $membership->expiration_date?->format('Y-m-d\TH:i'),
                    'is_active' => (bool) $membership->is_active,
                    'is_visible' => (bool) $membership->is_visible,
                    'completed_at' => $membership->completed_at?->format('Y-m-d H:i:s'),
                    'is_paid' => (bool) $membership->is_paid,
                    'job_title' => $membership->getTranslation('job_title', app()->getLocale()) ?: $membership->getTranslation('job_title', 'ar') ?: $membership->getTranslation('job_title', 'en'),
                    'job_title_ar' => $membership->getTranslation('job_title', 'ar'),
                    'job_title_en' => $membership->getTranslation('job_title', 'en'),
                    'company_name' => $membership->relationLoaded('company')
                        ? ($membership->company?->getTranslation('name', app()->getLocale()) ?: $membership->company?->getTranslation('name', 'ar') ?: $membership->company?->getTranslation('name', 'en'))
                        : null,
                    'company_name_ar' => $membership->relationLoaded('company') ? $membership->company?->getTranslation('name', 'ar') : null,
                    'company_name_en' => $membership->relationLoaded('company') ? $membership->company?->getTranslation('name', 'en') : null,
                    'partner_id' => $membership->partner_id,
                    'partner_name' => $membership->relationLoaded('partner') ? $membership->partner?->title : null,
                    'sales_id' => $membership->sales_id,
                    'sale_name' => $membership->relationLoaded('sales')
                        ? ($membership->sales?->getTranslation('name', app()->getLocale())
                            ?: $membership->sales?->getTranslation('name', 'ar')
                            ?: $membership->sales?->getTranslation('name', 'en'))
                        : null,
                    'partner_image' => $membership->relationLoaded('partner') ? $membership->partner?->image : null,
                    'created_at' => $membership->created_at?->toIso8601String(),
                    'updated_at' => $membership->updated_at?->toIso8601String(),
                    'created_by' => $membership->created_by,
                    'last_active_history' => $membership->relationLoaded('latestActiveHistory') && $membership->latestActiveHistory ? [
                        'created_at' => $membership->latestActiveHistory->created_at?->toIso8601String(),
                        'changer_name' => $membership->latestActiveHistory->changer?->name,
                    ] : null,
                    'total_amount' => $membership->relationLoaded('memberPayments')
                        ? (float) $membership->memberPayments->sum('amount')
                        : 0,
                    'days_covered' => $membership->relationLoaded('memberPayments')
                        ? (function () use ($membership) {
                            $total = 0;
                            foreach ($membership->memberPayments as $p) {
                                if ($p->from_date && $p->to_date) {
                                    $total += (int) $p->from_date->diffInDays($p->to_date) + 1;
                                }
                            }
                            return $total;
                        })()
                        : 0,
                    'outstanding_days' => $membership->relationLoaded('memberPayments')
                        ? (function () use ($membership) {
                            $daysSinceReg = $membership->registration_date
                                ? (int) $membership->registration_date->diffInDays(now())
                                : 0;
                            $daysCovered = 0;
                            foreach ($membership->memberPayments as $p) {
                                if ($p->from_date && $p->to_date) {
                                    $daysCovered += (int) $p->from_date->diffInDays($p->to_date) + 1;
                                }
                            }
                            return $daysSinceReg - $daysCovered;
                        })()
                        : 0,
                    'creator' => $membership->relationLoaded('creator') && $membership->creator ? [
                        'id' => $membership->creator->id,
                        'name' => $membership->creator->name,
                        'email' => $membership->creator->email,
                    ] : null,
                ];
            }),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}

