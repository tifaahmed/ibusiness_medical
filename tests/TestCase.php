<?php

namespace Tests;

use App\Support\PaymentMethods;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Shows every way of paying (a fresh install shows Paymob only), for tests that exercise them. */
    protected function showAllPaymentMethods(): void
    {
        foreach ([PaymentMethods::ONLINE_CARD, PaymentMethods::ONLINE_WALLET, PaymentMethods::COD, PaymentMethods::WALLET, PaymentMethods::INSTAPAY] as $slug) {
            SiteSettings::put($slug, true, \App\Models\Setting::TYPE_BOOLEAN);
        }
    }
}
