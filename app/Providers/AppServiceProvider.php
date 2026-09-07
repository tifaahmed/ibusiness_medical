<?php

namespace App\Providers;

use App\Enums\User\UserRoleEnum;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        /*
         * The super admin passes every permission check, by virtue of the role
         * rather than by what happens to be in `role_has_permissions`.
         *
         * Before this, "super admin" meant "a role that was synced with every
         * permission the last time somebody ran PermissionSeeder". Adding a
         * permission to UserPermissionEnum and shipping the feature that uses
         * it therefore locked the super admin out of it until the seeder was
         * re-run — which is exactly how `manage settings` came to 403 the one
         * account that is supposed to be able to reach everything.
         *
         * `spatie/laravel-permission`'s route middleware asks `canAny()`, which
         * goes through the Gate, so this covers the `permission:` middleware,
         * `@can` in templates and every policy check alike.
         *
         * It returns null rather than false for everyone else: false here would
         * be a verdict, ending the check before the real permission lookup ever
         * ran. Null means "no opinion — carry on", which is what a non-super
         * admin needs.
         */
        Gate::before(function (User $user, string $ability) {
            return $user->hasRole(UserRoleEnum::SUPER_ADMIN) ? true : null;
        });
    }
}
