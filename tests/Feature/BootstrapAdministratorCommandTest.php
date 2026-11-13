<?php

use App\Models\Profile;
use App\Models\User;
use Database\Seeders\ProfileSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(ProfileSeeder::class);
    config([
        'security.bootstrap.admin_name' => 'Production Administrator',
        'security.bootstrap.admin_email' => 'owner@example.com',
        'security.bootstrap.admin_password' => Str::password(16),
    ]);
});

it('creates the first active administrator with a hashed password', function () {
    $password = config('security.bootstrap.admin_password');

    $this->artisan('napi:bootstrap-admin')
        ->expectsOutputToContain('first administrator was created')
        ->assertSuccessful();

    $administrator = User::where('email', 'owner@example.com')->firstOrFail();

    expect($administrator->name)->toBe('Production Administrator')
        ->and($administrator->is_active)->toBeTrue()
        ->and($administrator->isAdministrator())->toBeTrue()
        ->and($administrator->password)->not->toBe($password)
        ->and(Hash::check($password, $administrator->password))->toBeTrue();
});

it('skips bootstrap without requiring secrets when an active administrator exists', function () {
    $administrator = User::factory()->create();
    $administrator->profiles()->attach(Profile::where('slug', Profile::ADMIN)->firstOrFail());
    $originalPassword = $administrator->password;
    config([
        'security.bootstrap.admin_email' => null,
        'security.bootstrap.admin_password' => null,
    ]);

    $this->artisan('napi:bootstrap-admin')
        ->expectsOutputToContain('bootstrap was skipped')
        ->assertSuccessful();

    expect($administrator->refresh()->password)->toBe($originalPassword)
        ->and(User::count())->toBe(1);
});

it('refuses missing or unsafe bootstrap credentials', function (mixed $email, mixed $password) {
    config([
        'security.bootstrap.admin_email' => $email,
        'security.bootstrap.admin_password' => $password,
    ]);

    $this->artisan('napi:bootstrap-admin')->assertExitCode(2);

    expect(User::count())->toBe(0);
})->with([
    'missing values' => [null, null],
    'invalid email' => ['not-an-email', 'Valid-password-123!'],
    'short password' => ['owner@example.com', 'Short-123!'],
    'password beyond bcrypt limit' => ['owner@example.com', str_repeat('a', 73)],
]);

it('does not elevate an existing non-administrator account', function () {
    User::factory()->create(['email' => 'owner@example.com']);

    $this->artisan('napi:bootstrap-admin')
        ->expectsOutputToContain('already belongs to a non-administrator')
        ->assertFailed();

    expect(User::where('email', 'owner@example.com')->firstOrFail()->isAdministrator())->toBeFalse()
        ->and(User::count())->toBe(1);
});
