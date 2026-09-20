<?php

use App\Models\Menu;
use App\Models\Profile;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

describe('Menu relations', function () {
    it('belongs to parent menu', function () {
        $parent = Menu::factory()->create();
        $child = Menu::factory()->withParent($parent)->create();

        expect($child->parent->id)->toBe($parent->id);
    });

    it('has many children', function () {
        $parent = Menu::factory()->create();
        Menu::factory(3)->withParent($parent)->create();

        expect($parent->children()->count())->toBe(3);
    });

    it('has tree structure', function () {
        $root1 = Menu::factory()->create(['parent_id' => null, 'order' => 1]);
        $root2 = Menu::factory()->create(['parent_id' => null, 'order' => 2]);

        expect(Menu::roots()->count())->toBe(2);
        expect(Menu::roots()->pluck('id')->all())->toBe([$root1->id, $root2->id]);
    });

    it('force deleting a parent cascades to children and its menu_profiles', function () {
        $parent = Menu::factory()->create();
        $child = Menu::factory()->withParent($parent)->create();
        $profile = Profile::factory()->create();
        $parent->profiles()->attach($profile->id, ['can_view' => true]);

        $parent->forceDelete();

        expect(Menu::query()->whereKey($child->id)->exists())->toBeFalse();
        expect(DB::table('menu_profiles')->count())->toBe(0);
    });
});

describe('Menu validation', function () {
    it('route_name is unique', function () {
        Menu::factory()->create(['route_name' => 'users.index']);

        expect(function () {
            Menu::factory()->create(['route_name' => 'users.index']);
        })->toThrow(QueryException::class);
    });
});
