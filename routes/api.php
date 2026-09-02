<?php

use App\Http\Controllers\Api\Auth\LoginController as ApiLoginController;
use App\Http\Controllers\Api\ContactMessageController;
use App\Http\Controllers\Api\V1\Guest\AboutController as V1AboutController;
use App\Http\Controllers\Api\V1\Guest\AuthController as V1AuthController;
use App\Http\Controllers\Api\V1\Guest\ClientErrorController as V1ClientErrorController;
use App\Http\Controllers\Api\V1\Guest\ContactController as V1ContactController;
use App\Http\Controllers\Api\V1\Guest\FacilityController as V1FacilityController;
use App\Http\Controllers\Api\V1\Guest\FacilitySearchController as V1FacilitySearchController;
use App\Http\Controllers\Api\V1\Guest\HomeController as V1HomeController;
use App\Http\Controllers\Api\V1\Guest\LocationController as V1LocationController;
use App\Http\Controllers\Api\V1\Guest\MembershipCardController as V1MembershipCardController;
use App\Http\Controllers\Api\V1\Guest\MembershipController as V1MembershipController;
use App\Http\Controllers\Api\V1\Guest\MembershipUsageController as V1MembershipUsageController;
use App\Http\Controllers\Api\V1\Guest\NewsTickerController as V1NewsTickerController;
use App\Http\Controllers\Api\V1\Guest\OfferController as V1OfferController;
use App\Http\Controllers\Api\V1\Guest\PartnerCompanyController as V1PartnerCompanyController;
use App\Http\Controllers\Api\V1\Guest\PartnerOfferController as V1PartnerOfferController;
use App\Http\Controllers\Api\V1\Guest\PartnerOfferRequestController as V1PartnerOfferRequestController;
use App\Http\Controllers\Api\V1\Guest\PartnersController as V1PartnersController;
use App\Http\Controllers\Api\V1\Guest\ProductController as V1ProductController;
use App\Http\Controllers\Api\V1\Guest\ServiceController as V1ServiceController;
use App\Http\Controllers\Api\V1\Partner\ContactMessageController as V1PartnerContactMessageController;
use App\Http\Controllers\Api\V1\Partner\MembershipController as V1PartnerMembershipController;
use App\Http\Controllers\Api\V1\Partner\OrderController as V1PartnerOrderController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', ApiLoginController::class)->name('api.auth.login');
Route::post('/v1/auth/login', [V1AuthController::class, 'login'])->name('api.v1.auth.login');

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Public Contact Form API
Route::post('/contact-messages', [ContactMessageController::class, 'store'])
    ->name('api.contact.store');

// Note: /api/membership-number/{generate,check} are registered in routes/web.php
// so they share the admin session/auth stack.

// v1: Public JSON API consumed by the mobile app (membership-app-mobile).
// Same data as the Inertia guest pages, returned as JSON.
Route::prefix('v1')
    ->middleware(\App\Http\Middleware\SetApiLocale::class)
    ->name('api.v1.')
    ->group(function () {
        Route::get('/home', V1HomeController::class)->name('home');
        Route::get('/services', [V1ServiceController::class, 'index'])->name('services.index');
        Route::get('/services/{service:slug}', [V1ServiceController::class, 'show'])->name('services.show');

        Route::get('/offers', V1OfferController::class)->name('offers.index');
        Route::get('/news-tickers', V1NewsTickerController::class)->name('news-tickers');
        Route::get('/about', V1AboutController::class)->name('about');
        Route::get('/contact', V1ContactController::class)->name('contact.show');

        /*
         * Governorates and the cities inside them, for an address picker on a
         * partner storefront. Public and key-less: place names identify
         * nobody, and the whole list is a few kilobytes shipped in one call.
         */
        Route::get('/locations', V1LocationController::class)->name('locations.index');

        Route::get('/facilities', V1PartnersController::class)->name('facilities.index');

        /*
         * The suggestion box behind a storefront's search input: one phrase
         * matched across facilities, branches, phone numbers, addresses,
         * cities, governorates and facility types, grouped by kind.
         *
         * Registered BEFORE `/facilities/{facility:slug}`, which would
         * otherwise swallow it — routes match in declaration order, and
         * "search" is a perfectly good slug.
         */
        Route::get('/facilities/search', V1FacilitySearchController::class)->name('facilities.search');

        Route::get('/facilities/{facility:slug}', [V1FacilityController::class, 'show'])->name('facilities.show');

        /*
         * The product catalogue behind the Deilar storefront. Public and
         * key-less like the facilities above it: a shop window carries nothing
         * about a member, and the listing endpoint ships the sidebar's filter
         * options with the grid so a page paints in one call.
         */
        Route::get('/products', [V1ProductController::class, 'index'])->name('products.index');
        Route::get('/products/{product:slug}', [V1ProductController::class, 'show'])->name('products.show');

        Route::post('/membership/lookup', [V1MembershipController::class, 'lookup'])->name('membership.lookup');
        Route::get('/memberships/{membership}', [V1MembershipController::class, 'show'])->name('membership.show');
        Route::get('/memberships/{membership}/card', [V1MembershipCardController::class, 'show'])->name('membership.card.show');
        Route::get('/memberships/{membership}/card/back', [V1MembershipCardController::class, 'showBack'])->name('membership.card.showBack');
        Route::get('/memberships/{membership}/card/url', [V1MembershipCardController::class, 'url'])->name('membership.card.url');

        Route::get('/memberships/{membership}/usage/options', [V1MembershipUsageController::class, 'options'])->name('membership.usage.options');
        Route::post('/memberships/{membership}/usage', [V1MembershipUsageController::class, 'store'])->name('membership.usage.store');

        Route::post('/contact-messages', [ContactMessageController::class, 'store'])->name('contact-messages.store');

        Route::post('/client-errors', [V1ClientErrorController::class, 'store'])->name('client-errors.store');

        Route::get('/partner-companies', [V1PartnerCompanyController::class, 'index'])->name('partner-companies.index');
        Route::get('/partner-companies/{partner}', [V1PartnerCompanyController::class, 'show'])->name('partner-companies.show');
        Route::get('/partner-companies/{partner}/offers', [V1PartnerCompanyController::class, 'offers'])->name('partner-companies.offers');

        Route::get('/partner-offers', [V1PartnerOfferController::class, 'index'])->name('partner-offers.index');
        Route::get('/partner-offers/{id}', [V1PartnerOfferController::class, 'show'])->name('partner-offers.show');
        Route::post('/partner-offer-requests', [V1PartnerOfferRequestController::class, 'store'])->name('partner-offer-requests.store');

        /*
         * Server-to-server lookups for partner properties (the Deilar
         * marketing site). Key-gated because, unlike everything above, the
         * response carries member and family names.
         */
        Route::middleware(\App\Http\Middleware\VerifyPartnerApiKey::class)
            ->prefix('partner')
            ->name('partner.')
            ->group(function () {
                Route::get('/memberships/{membershipNumber}', [V1PartnerMembershipController::class, 'show'])
                    ->name('memberships.show');

                /*
                 * Enquiries from a partner storefront's public forms. Key-gated
                 * for the same reason orders are: they WRITE, and the caller
                 * speaks for its visitor — the visitor's own IP and user agent
                 * come in the body, since `$request->ip()` here is the
                 * storefront's server.
                 *
                 * Throttled generously rather than tightly: the storefront
                 * queues and retries these, so a burst is a backlog draining
                 * after an outage, not an attack.
                 */
                Route::post('/contact-messages', [V1PartnerContactMessageController::class, 'store'])
                    ->middleware('throttle:60,1')
                    ->name('contact-messages.store');

                /*
                 * The card artwork itself, as a PNG: the admin's generated
                 * image when there is one, freshly rendered from the member's
                 * layout when there is not. Same handler as the public
                 * /memberships/{membership}/card above — it is repeated here so
                 * partner properties have one key-gated contract to read
                 * everything about a card through, rather than mixing an
                 * authenticated lookup with an anonymous image fetch.
                 */
                Route::get('/memberships/{membership}/card', [V1MembershipCardController::class, 'show'])
                    ->name('memberships.card');

                /*
                 * Orders placed from a partner storefront. Key-gated because
                 * they WRITE, and because the caller speaks for its visitor —
                 * the buyer's own IP arrives in the body, since
                 * `$request->ip()` here is the storefront's server.
                 *
                 * Throttled on top of the key: the order code is the only
                 * thing that opens an order, so `show` must not be a rate an
                 * attacker can enumerate at.
                 */
                Route::post('/orders', [V1PartnerOrderController::class, 'store'])
                    ->middleware('throttle:30,1')
                    ->name('orders.store');

                Route::get('/orders/{orderCode}', [V1PartnerOrderController::class, 'show'])
                    ->middleware('throttle:60,1')
                    ->name('orders.show');

                Route::post('/orders/{orderCode}/receipt', [V1PartnerOrderController::class, 'receipt'])
                    ->middleware('throttle:20,1')
                    ->name('orders.receipt');

                Route::get('/memberships/{membership}/card/back', [V1MembershipCardController::class, 'showBack'])
                    ->name('memberships.card.back');
            });

        Route::post('/auth/login', [V1AuthController::class, 'login'])->name('auth.login');
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/auth/logout', [V1AuthController::class, 'logout'])->name('auth.logout');
            Route::get('/profile', [V1AuthController::class, 'profile'])->name('profile.show');
            Route::put('/profile', [V1AuthController::class, 'updateProfile'])->name('profile.update');
            Route::put('/profile/password', [V1AuthController::class, 'changePassword'])->name('profile.password');
            Route::post('/profile/avatar', [V1AuthController::class, 'updateAvatar'])->name('profile.avatar');
        });
    });
