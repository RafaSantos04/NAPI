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
        Schema::create('api_specification_versions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('organization_id');
            $table->ulid('api_specification_id');
            $table->ulid('imported_by_membership_id');
            $table->integer('number');
            $table->string('format', 10);
            $table->string('openapi_version', 20);
            $table->string('info_title', 255);
            $table->string('info_version', 100);
            $table->char('content_sha256', 64);
            $table->integer('size_bytes');
            // The bytes received, kept as the original (ADR-0014, decision 5).
            $table->text('raw_document');
            // No updated_at: a version never changes.
            $table->timestamp('created_at')->useCurrent();

            // Specification and importer belong to this same organization,
            // even when Eloquent is bypassed (ADR-0012). Names are explicit:
            // the generated ones pass PostgreSQL's 63-character limit and
            // would be silently truncated.
            $table->foreign(['organization_id', 'api_specification_id'], 'api_specification_versions_specification_foreign')
                ->references(['organization_id', 'id'])
                ->on('api_specifications')
                ->restrictOnDelete();
            $table->foreign(['organization_id', 'imported_by_membership_id'], 'api_specification_versions_importer_foreign')
                ->references(['organization_id', 'id'])
                ->on('organization_memberships')
                ->restrictOnDelete();
            // Sequential numbering; also indexes the foreign key.
            $table->unique(['api_specification_id', 'number'], 'api_specification_versions_number_unique');
            // Idempotency by content (ADR-0013, item 33).
            $table->unique(['api_specification_id', 'content_sha256'], 'api_specification_versions_content_unique');
        });

        // The Schema Builder has no CHECK constraints.
        DB::statement("ALTER TABLE api_specification_versions ADD CONSTRAINT api_specification_versions_format_check CHECK (format IN ('json', 'yaml'))");
        DB::statement('ALTER TABLE api_specification_versions ADD CONSTRAINT api_specification_versions_number_check CHECK (number > 0)');
        DB::statement('ALTER TABLE api_specification_versions ADD CONSTRAINT api_specification_versions_size_check CHECK (size_bytes > 0)');
        DB::statement("ALTER TABLE api_specification_versions ADD CONSTRAINT api_specification_versions_sha256_check CHECK (content_sha256 ~ '^[0-9a-f]{64}$')");

        // Assessments point at a version, so what they evaluated can never
        // change, not even by direct SQL (ADR-0014, decision 3). Unlike the
        // audit_logs function there is no SET NULL exception: every foreign
        // key here restricts.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION napi_prevent_row_mutation()
            RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION '% is append-only', TG_TABLE_NAME USING ERRCODE = '55000';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER api_specification_versions_append_only
            BEFORE UPDATE OR DELETE ON api_specification_versions
            FOR EACH ROW EXECUTE FUNCTION napi_prevent_row_mutation();
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_specification_versions');
        DB::unprepared('DROP FUNCTION IF EXISTS napi_prevent_row_mutation()');
    }
};
