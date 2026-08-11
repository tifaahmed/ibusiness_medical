<?php

namespace App\Http\Middleware;

use App\Models\NewsTicker;
use App\Support\PublicMembershipUrl;
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
            // Brand logo comes from APP_LOGO so rebranding never touches components.
            'appLogo' => asset(config('app.logo')),
            'locale' => $locale,
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
            'authUserPermissions' => $request->user()
                ? $request->user()->getAllPermissions()->pluck('name')->values()->all()
                : [],
            'authUserRoles' => $request->user()
                ? $request->user()->getRoleNames()->values()->all()
                : [],
        ];
    }
}
