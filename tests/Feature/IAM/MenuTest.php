<?php

use App\Models\Menu;
use App\Models\User;

describe('Menu CRUD', function () {
    it('lists menus', function () {
        $admin = User::where('email', 'admin@napi.dev')->first();

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/menus');

        $response->assertStatus(200);
    });

    it('returns menu tree for logged user', function () {
        $admin = User::where('email', 'admin@napi.dev')->first();

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/menus/tree');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    });

    it('creates menu', function () {
        $admin = User::where('email', 'admin@napi.dev')->first();

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/menus', [
                'label' => 'Settings',
                'route_name' => 'settings.index',
                'icon' => 'settings',
            ]);

        $response->assertStatus(201);
        expect(Menu::where('route_name', 'settings.index')->exists())->toBeTrue();
    });

    it('blocks deletion of menu with children', function () {
        $admin = User::where('email', 'admin@napi.dev')->first();
        $parent = Menu::factory()->create();
        Menu::factory()->create(['parent_id' => $parent->id]);

        $response = $this->actingAs($admin)
            ->deleteJson("/api/v1/menus/{$parent->id}");

        $response->assertStatus(403);
    });
});
