<?php

use App\Models\Menu;
use App\Models\Profile;
use App\Models\User;

describe('Profile CRUD', function () {
    it('lists profiles', function () {
        $admin = User::where('email', 'admin@napi.dev')->first();

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/profiles');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'meta']);
    });

    it('creates profile', function () {
        $admin = User::where('email', 'admin@napi.dev')->first();

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/profiles', [
                'name' => 'Editor',
                'slug' => 'editor',
                'description' => 'Can edit posts',
            ]);

        $response->assertStatus(201);
        expect(Profile::where('slug', 'editor')->exists())->toBeTrue();
    });

    it('blocks viewer from creating profile', function () {
        $viewer = User::where('email', 'viewer@napi.dev')->first();

        $response = $this->actingAs($viewer)
            ->postJson('/api/v1/profiles', [
                'name' => 'Test',
                'slug' => 'test',
            ]);

        $response->assertStatus(404);
    });

    it('blocks deletion of system profile', function () {
        $admin = User::where('email', 'admin@napi.dev')->first();
        $admin_profile = Profile::where('slug', 'admin')->first();

        $response = $this->actingAs($admin)
            ->deleteJson("/api/v1/profiles/{$admin_profile->id}");

        $response->assertStatus(403);
    });

    it('blocks deletion of profile with users', function () {
        $admin = User::where('email', 'admin@napi.dev')->first();
        $profile = Profile::factory()->create();
        $profile->users()->attach($admin->id);

        $response = $this->actingAs($admin)
            ->deleteJson("/api/v1/profiles/{$profile->id}");

        $response->assertStatus(403);
    });

    it('syncs menu permissions', function () {
        $admin = User::where('email', 'admin@napi.dev')->first();
        $profile = Profile::factory()->create();
        $menu = Menu::factory()->create();

        $response = $this->actingAs($admin)
            ->putJson("/api/v1/profiles/{$profile->id}/menus", [
                'permissions' => [
                    $menu->id => [
                        'can_view' => true,
                        'can_create' => true,
                        'can_update' => false,
                        'can_delete' => false,
                    ],
                ],
            ]);

        $response->assertStatus(200);
        expect($profile->menus()->where('can_view', true)->count())->toBe(1);
    });
});
