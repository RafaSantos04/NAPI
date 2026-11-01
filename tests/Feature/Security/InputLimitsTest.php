<?php

use App\Http\Middleware\LimitRequestBodySize;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

// P2.5-T03 / ADR-0013, items 22 to 28: a default body limit for both groups,
// list and password ceilings sized to the domain (FIND-031) and a paginated
// token listing (FIND-032).

/**
 * JSON body of exactly the given size in bytes.
 */
function jsonBodyOf(int $bytes): string
{
    $envelope = strlen('{"name":""}');

    return json_encode(['name' => str_repeat('a', $bytes - $envelope)]);
}

describe('Request body limit', function () {
    it('refuses an API body over 64 KB with 413, before authentication', function () {
        $this->call('POST', '/api/v1/users', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_LENGTH' => LimitRequestBodySize::MAX_BYTES + 1,
        ], jsonBodyOf(LimitRequestBodySize::MAX_BYTES + 1))
            ->assertStatus(413)
            ->assertJsonPath('message', 'The request body is too large.');
    });

    it('lets a body of exactly 64 KB through to the application', function () {
        $this->call('POST', '/api/v1/users', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_LENGTH' => LimitRequestBodySize::MAX_BYTES,
        ], jsonBodyOf(LimitRequestBodySize::MAX_BYTES))
            ->assertUnauthorized();
    });

    it('measures a body sent without Content-Length, as in a chunked upload', function () {
        $request = fn (int $bytes) => $this->call('POST', '/api/v1/auth/login', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], jsonBodyOf($bytes));

        $request(LimitRequestBodySize::MAX_BYTES + 1)->assertStatus(413);
        $request(LimitRequestBodySize::MAX_BYTES)->assertUnprocessable();
    });

    it('refuses an oversized admin form with 413, before the session login', function () {
        $user = adminUser();
        $form = ['email' => $user->email, 'password' => str_repeat('a', LimitRequestBodySize::MAX_BYTES)];

        // A browser always declares the length of a form it posts.
        $this->withServerVariables(['CONTENT_LENGTH' => strlen(http_build_query($form))])
            ->post('/admin/login', $form)
            ->assertStatus(413);

        $this->assertGuest('web');
    });
});

describe('Domain-sized ceilings (FIND-031)', function () {
    it('refuses a password over 255 characters before checking it', function () {
        RateLimiter::spy();

        $this->postJson('/api/v1/auth/login', ['email' => 'someone@napi.dev', 'password' => Str::random(256)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        // Refused before VerifyLoginCredentials: no counter, no bcrypt.
        RateLimiter::shouldNotHaveReceived('increment');
    });

    it('refuses more profile ids than an assignment may carry', function () {
        $target = User::factory()->create();
        $ids = Profile::factory()->count(51)->create()->pluck('id')->all();

        asToken(seededUser('admin'), 'PUT', "/api/v1/users/{$target->id}/profiles", ['profile_ids' => $ids])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('profile_ids');

        expect($target->profiles()->count())->toBe(0);
    });

    it('refuses more abilities than the catalog has', function () {
        asToken(seededUser('dev'), 'POST', '/api/v1/tokens', [
            'name' => 'ci',
            'abilities' => ['read', 'write', 'delete', 'read'],
        ])->assertUnprocessable()->assertJsonValidationErrors('abilities');
    });

    it('refuses an e-mail or password over 255 characters on user creation', function (string $field) {
        $password = Str::password();
        $input = [
            'name' => 'Long',
            'email' => 'long@napi.dev',
            'password' => $password,
            'password_confirmation' => $password,
        ];
        $input[$field] = $field === 'email' ? str_repeat('a', 247).'@napi.dev' : Str::random(256);
        $input['password_confirmation'] = $input['password'];

        asToken(seededUser('admin'), 'POST', '/api/v1/users', $input)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    })->with(['email', 'password']);
});

describe('Token listing (FIND-032)', function () {
    it('paginates the caller\'s tokens, 15 per page, in the common envelope', function () {
        $dev = seededUser('dev');
        foreach (range(1, 16) as $i) {
            $dev->createToken("token-{$i}", ['read']);
        }

        asToken($dev, 'GET', '/api/v1/tokens')
            ->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.total', 17)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonStructure(['data' => [['id', 'name', 'abilities', 'created_at', 'expires_at']], 'links', 'meta'])
            ->assertJsonMissingPath('data.0.token');
    });
});
