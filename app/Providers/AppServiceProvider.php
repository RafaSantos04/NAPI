<?php

namespace App\Providers;

use App\Models\Menu;
use App\Models\Profile;
use App\Models\User;
use App\Policies\MenuPolicy;
use App\Policies\ProfilePolicy;
use App\Policies\UserPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

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
        Model::shouldBeStrict(! $this->app->isProduction());
        DB::prohibitDestructiveCommands($this->app->isProduction());

        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Profile::class, ProfilePolicy::class);
        Gate::policy(Menu::class, MenuPolicy::class);

        // Single enforcement point for deactivated accounts: a token whose
        // owner is inactive does not authenticate, whether or not it was
        // revoked when the account was deactivated.
        Sanctum::authenticateAccessTokensUsing(
            fn (PersonalAccessToken $token, bool $isValid): bool => $isValid
                && $token->tokenable instanceof User
                && $token->tokenable->is_active,
        );

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinutes(30, 6)
                ->by('login:'.$request->email.':'.$request->ip())
                ->response(fn () => response()->json(
                    ['message' => 'Too many login attempts. Try again later.'],
                    429
                ));
        });

        // Audit listeners in app/Listeners are registered by Laravel's event
        // discovery. Registering them here as well ran each one twice (FIND-002).
    }
}
