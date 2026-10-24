<?php

namespace Tests;

use App\Support\PaymentMethods;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Refuses to run against anything but a throwaway database. `RefreshDatabase` wipes and rebuilds the
     * schema, and a cached config (`bootstrap/cache/config.php`) silently ignores phpunit.xml and DB_DATABASE,
     * which once pointed a test run at the real `ibusiness_medical`. This fires before any database work.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $default = (string) $app['config']->get('database.default');
        $database = (string) $app['config']->get("database.connections.{$default}.database");

        if (! str_contains(strtolower($database), 'test') && $database !== ':memory:') {
            throw new \RuntimeException(
                "Refusing to run tests: the database is \"{$database}\", not a throwaway test database. "
                .'Run `php artisan config:clear` (a cached config ignores phpunit.xml) and make sure DB_DATABASE is a *_test database.'
            );
        }

        return $app;
    }

    /** Shows every way of paying (a fresh install shows Paymob only), for tests that exercise them. */
    protected function showAllPaymentMethods(): void
    {
        foreach ([PaymentMethods::ONLINE_CARD, PaymentMethods::ONLINE_WALLET, PaymentMethods::COD, PaymentMethods::WALLET, PaymentMethods::INSTAPAY] as $slug) {
            SiteSettings::put($slug, true, \App\Models\Setting::TYPE_BOOLEAN);
        }
    }
}
