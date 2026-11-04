<?php

use App\Models\Setting;
use App\Support\MembershipPlan;
use App\Support\SiteSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Individuals vs companies, and a price list for each. ADD-ONLY, plus the one copy the owner asked for:
 *  - `memberships.member_kind` (individual | company). Every existing card on the MONTHLY system becomes a company;
 *    every other card (yearly, or no system recorded) becomes an individual. Editable afterwards from the admin.
 *  - three NEW price settings (company monthly / company yearly / individual monthly) — created only when absent.
 *  - the two EXISTING individual prices (yearly, half-year) are set to the owner's figures (600 / 400); they were 1200 / 350
 *    at the time of writing. Everything stays editable at /admin/setting.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('memberships', 'member_kind')) {
            Schema::table('memberships', function (Blueprint $table) {
                $table->string('member_kind', 12)->default('individual')->after('payment_type');
                $table->index('member_kind');
            });

            DB::table('memberships')->where('payment_type', 'monthly')->update(['member_kind' => 'company']);
        }

        foreach ([MembershipPlan::INDIVIDUAL_MONTH_PRICE, MembershipPlan::COMPANY_MONTH_PRICE, MembershipPlan::COMPANY_YEAR_PRICE] as $slug) {
            $row = collect(MembershipPlan::seedRows())->firstWhere('slug', $slug);

            if (! Setting::query()->where('slug', $slug)->exists()) {
                SiteSettings::put($row['slug'], $row['value'], $row['value_type'], $row['name']);
            }
        }

        SiteSettings::put(MembershipPlan::PRICE, '600', Setting::TYPE_NUMBER);
        SiteSettings::put(MembershipPlan::HALF_PRICE, '400', Setting::TYPE_NUMBER);
    }

    public function down(): void
    {
        // Left in place: the column and the prices are data the admin may have edited since.
    }
};
