<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE organization_memberships DROP CONSTRAINT organization_memberships_role_check');
        DB::statement("ALTER TABLE organization_memberships ADD CONSTRAINT organization_memberships_role_check CHECK (role IN ('owner', 'manager', 'member'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE organization_memberships DROP CONSTRAINT organization_memberships_role_check');
        DB::statement("ALTER TABLE organization_memberships ADD CONSTRAINT organization_memberships_role_check CHECK (role IN ('owner', 'member'))");
    }
};
