<?php

use App\Models\AuditLog;
use App\Models\Menu;
use App\Models\Profile;
use App\Models\User;

describe('Audit Events', function () {
    it('logs profile assignment', function () {
        $admin = User::where('email', 'admin@napi.dev')->first();
        $user = User::factory()->create();
        $profile = Profile::factory()->create();

        $this->actingAs($admin)
            ->putJson("/api/v1/users/{$user->id}/profiles", [
                'profile_ids' => [$profile->id],
            ]);

        expect(AuditLog::where('action', 'profile_assigned')->exists())->toBeTrue();
    });

    it('logs permission change', function () {
        $admin = User::where('email', 'admin@napi.dev')->first();
        $profile = Profile::factory()->create();
        $menu = Menu::factory()->create();

        $this->actingAs($admin)
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

        expect(AuditLog::where('action', 'permission_changed')->exists())->toBeTrue();
    });
});
