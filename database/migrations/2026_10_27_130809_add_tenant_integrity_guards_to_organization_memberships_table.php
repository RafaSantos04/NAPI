<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Target of the composite foreign keys that bind a record to a
        // membership of its own organization (ADR-0012).
        Schema::table('organization_memberships', function (Blueprint $table) {
            $table->unique(['organization_id', 'id']);
        });

        // Composite foreign keys only check INSERTs and parent changes; this
        // keeps a row from being moved to another tenant or parent afterwards.
        // Each trigger passes the columns it freezes as arguments.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION napi_prevent_tenant_reassignment()
            RETURNS trigger AS $$
            DECLARE
                frozen_column text;
            BEGIN
                FOREACH frozen_column IN ARRAY TG_ARGV LOOP
                    IF (to_jsonb(NEW) -> frozen_column) IS DISTINCT FROM (to_jsonb(OLD) -> frozen_column) THEN
                        RAISE EXCEPTION '%.% is immutable', TG_TABLE_NAME, frozen_column USING ERRCODE = '55000';
                    END IF;
                END LOOP;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER organization_memberships_tenant_immutable
            BEFORE UPDATE ON organization_memberships
            FOR EACH ROW EXECUTE FUNCTION napi_prevent_tenant_reassignment('organization_id', 'user_id');
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS organization_memberships_tenant_immutable ON organization_memberships;
            DROP FUNCTION IF EXISTS napi_prevent_tenant_reassignment();
        SQL);

        Schema::table('organization_memberships', function (Blueprint $table) {
            $table->dropUnique(['organization_id', 'id']);
        });
    }
};
