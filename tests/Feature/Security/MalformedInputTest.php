<?php

use App\Models\Profile;

// P2.5-T04 / ADR-0013, item 26 (FIND-026): text that is not valid UTF-8 is a
// malformed request (400) in both groups, before validation, route binding
// or PostgreSQL, which would answer it with a 500.

// "França" encoded as Latin-1: the ç is the lone byte 0xE7.
const LATIN1_TEXT = "Fran\xE7a";

describe('Invalid UTF-8 is refused with 400', function () {
    it('refuses an invalid query string value on the API', function () {
        $owner = seededUser('dev');
        $organization = organizationFor($owner);

        asToken($owner, 'GET', "/api/v1/organizations/{$organization->id}/projects?search=%FF")
            ->assertBadRequest()
            ->assertJsonPath('message', 'The request contains text that is not valid UTF-8.');
    });

    // Laravel's global ValidatePathEncoding covers the path; this keeps it on.
    it('refuses an invalid path segment before route binding', function () {
        asToken(seededUser('dev'), 'GET', '/api/v1/organizations/%FF/projects')
            ->assertBadRequest();
    });

    it('refuses a raw JSON body that is not UTF-8', function () {
        $this->call('POST', '/api/v1/profiles', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.tokenFor(seededUser('admin')),
        ], '{"name":"'.LATIN1_TEXT.'","slug":"franca"}')
            ->assertBadRequest();

        expect(Profile::where('slug', 'franca')->exists())->toBeFalse();
    });

    it('refuses an invalid admin form value, key or nested value', function (array $form) {
        $this->actingAs(seededUser('admin'))
            ->post('/admin/profiles', ['slug' => 'franca', ...$form])
            ->assertBadRequest();

        expect(Profile::where('slug', 'franca')->exists())->toBeFalse();
    })->with([
        'Latin-1 value' => [['name' => LATIN1_TEXT]],
        'Latin-1 key' => [['name' => 'França', LATIN1_TEXT => 'x']],
        'nested value' => [['name' => 'França', 'extra' => ['deep' => [LATIN1_TEXT]]]],
    ]);
});

describe('Valid UTF-8 keeps working', function () {
    it('accepts accents and emoji in a query, a JSON body and a form', function () {
        $owner = seededUser('dev');
        $organization = organizationFor($owner);

        asToken($owner, 'GET', "/api/v1/organizations/{$organization->id}/projects?search=".rawurlencode('ação 🚀'))
            ->assertOk();

        asToken(seededUser('admin'), 'POST', '/api/v1/profiles', ['name' => 'Ação 🚀', 'slug' => 'acao'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Ação 🚀');

        $this->actingAs(seededUser('admin'))
            ->post('/admin/profiles', ['name' => 'França ✅', 'slug' => 'franca'])
            ->assertRedirect();

        expect(Profile::where('slug', 'franca')->value('name'))->toBe('França ✅');
    });
});
