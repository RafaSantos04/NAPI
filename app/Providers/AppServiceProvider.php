<?php

namespace App\Providers;

use App\Domain\Tenancy\OrganizationContext;
use App\Http\Admin\AdminNavigation;
use App\Models\Application;
use App\Models\Environment;
use App\Models\Menu;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Profile;
use App\Models\Project;
use App\Models\SecurityLabResource;
use App\Models\SecurityTestRun;
use App\Models\User;
use App\Policies\ApplicationPolicy;
use App\Policies\EnvironmentPolicy;
use App\Policies\MenuPolicy;
use App\Policies\OrganizationMembershipPolicy;
use App\Policies\OrganizationPolicy;
use App\Policies\ProfilePolicy;
use App\Policies\ProjectPolicy;
use App\Policies\SecurityLabResourcePolicy;
use App\Policies\SecurityTestRunPolicy;
use App\Policies\UserPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
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
        // One context per request/job execution; it identifies a tenant but
        // never grants access by itself (ADR-0011).
        $this->app->scoped(OrganizationContext::class, fn () => new OrganizationContext);
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
        Gate::policy(Organization::class, OrganizationPolicy::class);
        Gate::policy(OrganizationMembership::class, OrganizationMembershipPolicy::class);
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(Application::class, ApplicationPolicy::class);
        Gate::policy(Environment::class, EnvironmentPolicy::class);
        Gate::policy(SecurityTestRun::class, SecurityTestRunPolicy::class);
        Gate::policy(SecurityLabResource::class, SecurityLabResourcePolicy::class);

        // Single enforcement point for deactivated accounts: a token whose
        // owner is inactive does not authenticate, whether or not it was
        // revoked when the account was deactivated.
        Sanctum::authenticateAccessTokensUsing(
            fn (PersonalAccessToken $token, bool $isValid): bool => $isValid
                && $token->tokenable instanceof User
                && $token->tokenable->is_active,
        );

        // Logins are limited inside VerifyLoginCredentials, not here: their
        // counters must be atomic and shared by both adapters (ADR-0013).
        // Authenticated routes are keyed by user, not token, since a user can
        // mint tokens. The framework's read-then-increment race is accepted:
        // it only lets an authenticated user overshoot by their own
        // concurrent requests (ADR-0013, item 20).
        RateLimiter::for('authenticated', function (Request $request) {
            return Limit::perMinute(120)->by('user:'.$request->user()?->getAuthIdentifier());
        });

        // Sidebar of the signed-in admin area: only the sections the user's
        // Policies allow (AdminNavigation). Hiding a link is not authorization.
        View::composer('layouts.admin-shell', function ($view) {
            $actor = auth()->user();

            $view->with('adminNavigation', $actor instanceof User ? AdminNavigation::for($actor) : []);
        });

        // Audit listeners in app/Listeners are registered by Laravel's event
        // discovery. Registering them here as well ran each one twice (FIND-002).
    }
}
