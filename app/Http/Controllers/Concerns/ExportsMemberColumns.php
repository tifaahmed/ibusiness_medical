<?php

namespace App\Http\Controllers\Concerns;

use App\Models\MembershipCard;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

/**
 * Shared column catalogue for member XLSX exports — the full set of
 * exportable fields, and how to resolve each one from a $user/$membership
 * pair. Used by both the admin/user/membership export and the per-company
 * members export so column definitions/values never drift apart.
 */
trait ExportsMemberColumns
{
    protected function getCardBatchMap(): array
    {
        static $map = null;
        if ($map === null) {
            $map = [];
            MembershipCard::query()
                ->whereNotNull('membership_ids')
                ->lazyById(200)
                ->each(function (MembershipCard $card) use (&$map) {
                    $batchName = $card->title ?? $card->name ?? ('#' . $card->id);
                    foreach ($card->membership_ids ?? [] as $membershipId) {
                        $map[(int) $membershipId] = $batchName;
                    }
                });
        }
        return $map;
    }

    protected function getMemberColumnDefinitions(): array
    {
        return [
            'index'            => ['label' => __('admin.member_export.col_index'), 'width' => 16, 'align' => Alignment::HORIZONTAL_CENTER],
            'name'             => ['label' => __('admin.member_export.col_name'), 'width' => 50],
            'email'            => ['label' => __('admin.member_export.col_email'), 'width' => 36],
            'phone'            => ['label' => __('admin.member_export.col_phone'), 'width' => 20],
            'membership_number'=> ['label' => __('admin.member_export.col_membership_number'), 'width' => 26],
            'national_id'      => ['label' => __('admin.member_export.col_national_id'), 'width' => 22],
            'status'           => ['label' => __('admin.member_export.col_status'), 'width' => 16, 'align' => Alignment::HORIZONTAL_CENTER, 'badge' => true],
            'payment'          => ['label' => __('admin.member_export.col_payment'), 'width' => 14, 'align' => Alignment::HORIZONTAL_CENTER, 'badge' => true],
            'visibility'       => ['label' => __('admin.member_export.col_visibility'), 'width' => 16, 'align' => Alignment::HORIZONTAL_CENTER, 'badge' => true],
            'job_title'        => ['label' => __('admin.member_export.col_job_title'), 'width' => 32],
            'company'          => ['label' => __('admin.member_export.col_company'), 'width' => 32],
            'partner'          => ['label' => __('admin.member_export.col_partner'), 'width' => 28],
            'sales'            => ['label' => __('admin.member_export.col_sales'), 'width' => 28],
            'governorate'      => ['label' => __('admin.member_export.col_governorate'), 'width' => 24],
            'city'             => ['label' => __('admin.member_export.col_city'), 'width' => 24],
            'card_patch'       => ['label' => __('admin.member_export.col_card_patch'), 'width' => 26, 'align' => Alignment::HORIZONTAL_CENTER, 'badge' => true],
            'registration_date'=> ['label' => __('admin.member_export.col_registration_date'), 'width' => 20, 'align' => Alignment::HORIZONTAL_CENTER],
            'expiration_date'  => ['label' => __('admin.member_export.col_expiration_date'), 'width' => 20, 'align' => Alignment::HORIZONTAL_CENTER],
            'created_at'       => ['label' => __('admin.member_export.col_created_at'), 'width' => 24, 'align' => Alignment::HORIZONTAL_CENTER],
            'updated_at'       => ['label' => __('admin.member_export.col_updated_at'), 'width' => 24, 'align' => Alignment::HORIZONTAL_CENTER],
            'family_members'   => ['label' => __('admin.member_export.col_family_members'), 'width' => 16, 'align' => Alignment::HORIZONTAL_CENTER],
            'creator'              => ['label' => __('admin.member_export.col_creator'), 'width' => 46, 'align' => Alignment::HORIZONTAL_CENTER],
            'last_active_history'  => ['label' => __('admin.member_export.col_last_active_history'), 'width' => 24, 'align' => Alignment::HORIZONTAL_CENTER],
            'avatar_url'           => ['label' => __('admin.member_export.col_avatar_url'), 'width' => 50, 'hidden' => true],

            // Payment stats (optional — computed from memberPayments)
            'total_amount'     => ['label' => __('admin.member_export.col_total_amount'), 'width' => 16, 'align' => Alignment::HORIZONTAL_CENTER],
            'payment_type'     => ['label' => __('admin.member_export.col_payment_type'), 'width' => 22],
            'total_months_paid'=> ['label' => __('admin.member_export.col_total_months_paid'), 'width' => 20, 'align' => Alignment::HORIZONTAL_CENTER],
            'covered_until'    => ['label' => __('admin.member_export.col_covered_until'), 'width' => 20, 'align' => Alignment::HORIZONTAL_CENTER],
            'days_since_reg'   => ['label' => __('admin.member_export.col_days_since_reg'), 'width' => 18, 'align' => Alignment::HORIZONTAL_CENTER],
            'days_covered'     => ['label' => __('admin.member_export.col_days_covered'), 'width' => 18, 'align' => Alignment::HORIZONTAL_CENTER],
            'outstanding_days' => ['label' => __('admin.member_export.col_outstanding_days'), 'width' => 20, 'align' => Alignment::HORIZONTAL_CENTER],
            'payment_status'   => ['label' => __('admin.member_export.col_payment_status'), 'width' => 18, 'align' => Alignment::HORIZONTAL_CENTER, 'badge' => true],
        ];
    }

    protected function getColumnValue(string $key, $user, $membership, int $rowIndex): string
    {
        return match ($key) {
            'index' => (string) $rowIndex,
            'name' => (string) $user->name,
            'email' => (string) $user->email,
            'phone' => (string) ($user->phone ?? ''),
            'membership_number' => (string) ($membership?->membership_number ?? ''),
            'national_id' => (string) ($membership?->national_id ?? ''),
            'status' => $membership === null ? '' : ($membership->is_active ? __('admin.member_export.badge_active') : __('admin.member_export.badge_inactive')),
            'payment' => $membership === null ? '' : ($membership->is_paid ? __('admin.member_export.badge_paid') : __('admin.member_export.badge_unpaid')),
            'visibility' => $membership === null ? '' : ($membership->is_visible ? __('admin.member_export.badge_visible') : __('admin.member_export.badge_hidden')),
            'job_title' => (string) ($membership?->getTranslation('job_title', app()->getLocale())
                ?: ($membership?->getTranslation('job_title', 'ar')
                ?: ($membership?->getTranslation('job_title', 'en') ?: ''))),
            'company' => (string) ($membership?->company?->getTranslation('name', app()->getLocale())
                ?: ($membership?->company?->getTranslation('name', 'ar')
                ?: ($membership?->company?->getTranslation('name', 'en') ?: ''))),
            'partner' => (string) ($membership?->partner?->title ?? ''),
            'sales' => (string) ($membership?->sales
                ? ($membership->sales->getTranslation('name', app()->getLocale())
                    ?: $membership->sales->getTranslation('name', 'ar')
                    ?: $membership->sales->getTranslation('name', 'en'))
                : ''),
            'governorate' => (string) ($membership?->governorate
                ? ($membership->governorate->getTranslation('name', app()->getLocale())
                    ?: $membership->governorate->getTranslation('name', 'ar')
                    ?: $membership->governorate->getTranslation('name', 'en'))
                : ''),
            'city' => (string) ($membership?->city
                ? ($membership->city->getTranslation('name', app()->getLocale())
                    ?: $membership->city->getTranslation('name', 'ar')
                    ?: $membership->city->getTranslation('name', 'en'))
                : ''),
            'registration_date' => $membership?->registration_date?->translatedFormat('d M Y') ?? '',
            'expiration_date' => $membership?->expiration_date?->translatedFormat('d M Y') ?? '',
            'created_at' => $user->created_at?->translatedFormat('d M Y H:i') ?? '',
            'updated_at' => $membership?->updated_at?->translatedFormat('d M Y H:i') ?? '',
            'family_members' => (string) ($membership?->family_members_count ?? 0),
            'card_patch'       => $membership !== null && isset($this->getCardBatchMap()[$membership->id])
                ? (string) $this->getCardBatchMap()[$membership->id]
                : '',
            'creator'              => (function () use ($membership) {
                $creator = $membership?->creator;
                return $creator
                    ? trim($creator->name . ($creator->email ? " <{$creator->email}>" : ''))
                    : '';
            })(),
            'last_active_history'  => (function () use ($membership) {
                if ($membership === null) return '';
                $history = $membership->latestActiveHistory;
                if (!$history) return '';
                $date = $history->created_at?->translatedFormat('d M Y H:i') ?? '';
                $name = $history->changer?->name ?? '';
                return $date . ($name ? " / {$name}" : '');
            })(),
            'avatar_url' => get_image_url($user, 'avatar'),

            // Payment stats
            'total_amount'     => (function () use ($membership) {
                if ($membership === null) return '';
                return number_format((float) $membership->memberPayments->sum('amount'), 2);
            })(),
            'payment_type'     => (string) ($membership?->payment_type ?? ''),
            'total_months_paid'=> (function () use ($membership) {
                if ($membership === null) return '';
                return (string) $membership->memberPayments->sum('months_paid');
            })(),
            'covered_until'    => (function () use ($membership) {
                if ($membership === null) return '';
                $max = $membership->memberPayments->pluck('to_date')->max();
                return $max?->translatedFormat('d M Y') ?? '';
            })(),
            'days_since_reg'   => (function () use ($membership) {
                if ($membership === null || !$membership->registration_date) return '';
                return (string) (int) $membership->registration_date->diffInDays(now());
            })(),
            'days_covered'     => (function () use ($membership) {
                if ($membership === null) return '';
                $total = 0;
                foreach ($membership->memberPayments as $p) {
                    if ($p->from_date && $p->to_date) {
                        $total += (int) $p->from_date->diffInDays($p->to_date) + 1;
                    }
                }
                return (string) $total;
            })(),
            'outstanding_days' => (function () use ($membership) {
                if ($membership === null || !$membership->registration_date) return '';
                $daysSinceReg = (int) $membership->registration_date->diffInDays(now());
                $daysCovered = 0;
                foreach ($membership->memberPayments as $p) {
                    if ($p->from_date && $p->to_date) {
                        $daysCovered += (int) $p->from_date->diffInDays($p->to_date) + 1;
                    }
                }
                return (string) ($daysSinceReg - $daysCovered);
            })(),
            'payment_status'   => $membership === null ? '' : ($membership->is_paid ? __('admin.member_export.badge_paid') : __('admin.member_export.badge_unpaid')),
            default => '',
        };
    }
}
