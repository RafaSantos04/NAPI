<?php

use App\Models\Menu;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// IAM tests rely on the demo users/profiles/menu permissions from
// DatabaseSeeder. Scoped to this directory (not globally) so it doesn't
// collide with other Feature tests that create their own 'admin'-slug
// profiles via factories.
uses()->beforeEach(fn () => $this->seed())->in('Feature/IAM', 'Feature/Security', 'Feature/Admin');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Issues a real Sanctum personal access token, so the request goes through
 * auth:sanctum, token resolution and ability checks. actingAs() skips all of
 * them by attaching a TransientToken that can do anything.
 *
 * @param  array<int, string>  $abilities
 */
function tokenFor(User $user, array $abilities = ['read', 'write', 'delete']): string
{
    return $user->createToken('test', $abilities)->plainTextToken;
}

function seededUser(string $slug): User
{
    return User::where('email', "{$slug}@napi.dev")->firstOrFail();
}

/**
 * Creates a non-system profile holding exactly the given functional
 * permissions, e.g. ['users' => ['view', 'update']].
 *
 * @param  array<string, array<int, string>>  $permissions
 */
function profileWithPermissions(array $permissions, ?User $holder = null): Profile
{
    $profile = Profile::factory()->create();

    foreach ($permissions as $key => $actions) {
        $menu = Menu::where('key', $key)->firstOrFail();
        $profile->menus()->attach($menu->id, [
            'can_view' => in_array('view', $actions, true),
            'can_create' => in_array('create', $actions, true),
            'can_update' => in_array('update', $actions, true),
            'can_delete' => in_array('delete', $actions, true),
        ]);
    }

    $holder?->profiles()->attach($profile->id);

    return $profile;
}

/**
 * @param  array<string, array<int, string>>  $permissions
 */
function userWithPermissions(array $permissions): User
{
    $user = User::factory()->create();
    profileWithPermissions($permissions, $user);

    return $user;
}

/**
 * Sends a request authenticated by a real bearer token. Guards are reset
 * first because the test application keeps the resolved user between
 * requests of the same test.
 *
 * @param  array<string, mixed>  $data
 * @param  array<int, string>  $abilities
 */
function asToken(User $user, string $method, string $uri, array $data = [], array $abilities = ['read', 'write', 'delete']): TestResponse
{
    app('auth')->forgetGuards();

    return test()->withToken(tokenFor($user, $abilities))->json($method, $uri, $data);
}

/**
 * Active user holding the seeded admin profile (every functional permission,
 * so every admin area section). Factory password: "password".
 *
 * @param  array<string, mixed>  $attributes
 */
function adminUser(array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    $user->profiles()->attach(Profile::where('slug', Profile::ADMIN)->firstOrFail()->id);

    return $user;
}

/**
 * Logs in through the real admin form, so the session holds the login (unlike
 * actingAs(), which only sets the guard in memory). Guards are reset first so
 * the next request reads the user back from the session.
 */
function adminLogin(User $user, string $password = 'password'): TestResponse
{
    $response = test()->post('/admin/login', ['email' => $user->email, 'password' => $password]);
    app('auth')->forgetGuards();

    return $response;
}
