<?php

namespace Tests\Feature;

use App\Enums\Membership\PaymentTypeEnum;
use App\Models\Membership;
use App\Support\MembershipPlan;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A company subscribes monthly only; an individual may pick monthly, half-yearly or yearly (owner decision 2026-11-17). */
class MemberKindPlansTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_is_sold_the_monthly_plan_only(): void
    {
        SiteSettings::put(MembershipPlan::COMPANY_YEAR_PRICE, 600, \App\Models\Setting::TYPE_NUMBER);
        SiteSettings::put(MembershipPlan::COMPANY_MONTH_PRICE, 50, \App\Models\Setting::TYPE_NUMBER);

        $keys = array_column(MembershipPlan::plans(Membership::KIND_COMPANY), 'key');

        $this->assertSame([MembershipPlan::PLAN_MONTH], $keys);
        $this->assertNull(MembershipPlan::find('year', Membership::KIND_COMPANY));
    }

    public function test_individual_keeps_year_half_and_month(): void
    {
        SiteSettings::put(MembershipPlan::PRICE, 1000, \App\Models\Setting::TYPE_NUMBER);
        SiteSettings::put(MembershipPlan::HALF_PRICE, 600, \App\Models\Setting::TYPE_NUMBER);
        SiteSettings::put(MembershipPlan::INDIVIDUAL_MONTH_PRICE, 100, \App\Models\Setting::TYPE_NUMBER);

        $keys = array_column(MembershipPlan::plans(Membership::KIND_INDIVIDUAL), 'key');

        $this->assertSame([MembershipPlan::PLAN_YEAR, MembershipPlan::PLAN_HALF, MembershipPlan::PLAN_MONTH], $keys);
    }

    public function test_payment_type_enum_knows_half_yearly(): void
    {
        $this->assertContains('half_yearly', PaymentTypeEnum::values());
    }
}
