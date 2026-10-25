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
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION napi_prevent_audit_log_mutation()
            RETURNS trigger AS $$
            BEGIN
                -- Preserve audit rows when FK SET NULL removes their actor or tenant.
                IF TG_OP = 'UPDATE'
                    AND pg_trigger_depth() > 1
                    AND (to_jsonb(NEW) - ARRAY['user_id', 'organization_id'])
                        = (to_jsonb(OLD) - ARRAY['user_id', 'organization_id'])
                    AND (NEW.user_id IS NOT DISTINCT FROM OLD.user_id OR NEW.user_id IS NULL)
                    AND (NEW.organization_id IS NOT DISTINCT FROM OLD.organization_id OR NEW.organization_id IS NULL)
                THEN
                    RETURN NEW;
                END IF;

                RAISE EXCEPTION 'audit_logs is append-only' USING ERRCODE = '55000';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER audit_logs_append_only
            BEFORE UPDATE OR DELETE ON audit_logs
            FOR EACH ROW EXECUTE FUNCTION napi_prevent_audit_log_mutation();
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS audit_logs_append_only ON audit_logs;
            DROP FUNCTION IF EXISTS napi_prevent_audit_log_mutation();
        SQL);
    }
};
