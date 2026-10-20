<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A menu cannot be its own parent (FIND-019). Longer cycles are refused
     * by MenuUpdateRequest, which walks the hierarchy; a CHECK cannot see
     * other rows, so only this direct case is guaranteed by the database.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE menus ADD CONSTRAINT menus_parent_not_self_check CHECK (parent_id IS NULL OR parent_id <> id)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE menus DROP CONSTRAINT menus_parent_not_self_check');
    }
};
