<?php

namespace App\Http\Middleware;

use App\Models\NewsTicker;
use App\Support\PublicMembershipUrl;
use App\Support\SiteSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        // Guest visitors are always served Arabic — the language switcher is
        // hidden from the guest nav, so any session-stored locale (left over
        // from a prior login or an /lang/ visit) must not leak through here.
        // Authenticated users keep their session-selected locale (the admin
        // header still has a switcher).
        if ($request->user() === null) {
            $locale = 'ar';
        } else {
            $locale = Session::get('locale', config('app.locale'));
        }
        App::setLocale($locale);

        $shared = parent::share($request);

        $newsTickers = NewsTicker::active()->ordered()->get()->map(function ($item) {
            $locale = app()->getLocale();

            return [
                'category' => $item->category,
                'categoryLabel' => $item->category,
                'title' => $item->getTranslation('title', $locale),
                'description' => $item->getTranslation('description', $locale),
                'image' => $item->mobile_image ?: ($item->image ?: $item->image_url),
            ];
        });

        return [
            ...$shared,
            'appName' => config('app.name'),
            // The logo an administrator uploaded under Settings, falling back
            // to APP_LOGO when that row is empty or was never created. Every
            // component reads this one prop, so replacing the file in the admin
            // is all a rebrand takes.
            'appLogo' => SiteSettings::get('deilar_logo', asset(config('app.logo'))),
            /* The rest of the details an administrator owns under Settings, for
               the documents that carry the shop's identity rather than the
               app's — the printed order and receipt put these in the header and
               the footer. Each falls back to its config equivalent so a site
               whose rows were never seeded still prints something sensible. */
            'siteDetails' => [
                'name' => SiteSettings::get('deilar_name', config('app.name')),
                'url' => SiteSettings::get('deilar_url', config('app.url')),
                'phone' => SiteSettings::get('deilar_phone'),
                'address' => SiteSettings::get('deilar_address'),
                // What a printed QR points at — the public site, which is not
                // necessarily the host this application runs on.
                'qrLink' => SiteSettings::get('deilar_qrcode', SiteSettings::get('deilar_url', config('app.url'))),
            ],
            'locale' => $locale,
            // Flashed one-liners from redirects ("… created.", "… updated.").
            // Pages read them as $page.props.flash.success.
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'translations' => [
                'home' => __('home'),
                'partners' => __('partners'),
                'about' => __('about'),
                'contact' => __('contact'),
                'admin' => __('admin'),
            ],
            'newsTickers' => $newsTickers,
            // Where a card's QR code points. Shared so the canvas renderers in
            // the browser encode the same address the server-rendered PNG does
            // — a preview that disagrees with the printed card is worse than no
            // preview at all.
            'publicMembershipBaseUrl' => PublicMembershipUrl::base(),
            /* What the user can actually do, not merely what their roles were
               last synced with — see User::effectivePermissionNames(). The admin
               UI hides buttons on this list, so it has to agree with the Gate or
               a super admin gets a page with nothing on it. */
            'authUserPermissions' => $request->user()
                ? $request->user()->effectivePermissionNames()
                : [],
            'authUserRoles' => $request->user()
                ? $request->user()->getRoleNames()->values()->all()
                : [],
        ];
    }
}
