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
        // Target of the endpoints' composite foreign key (deferred from T01).
        Schema::table('api_specification_versions', function (Blueprint $table) {
            $table->unique(['organization_id', 'id'], 'api_specification_versions_organization_id_id_unique');
        });

        Schema::create('api_endpoints', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('organization_id');
            $table->ulid('api_specification_version_id');
            $table->string('method', 7);
            // Text: the 2 MB document already bounds them, and a long value
            // must not turn a valid import into a 500.
            $table->text('path');
            $table->text('operation_id')->nullable();
            $table->text('summary')->nullable();
            // No timestamps: the projection is written once, with its version.

            // The version belongs to this same organization, even when
            // Eloquent is bypassed (ADR-0012). The inventory belongs to the
            // version, not to the specification (ADR-0014, decision 6).
            $table->foreign(['organization_id', 'api_specification_version_id'], 'api_endpoints_version_foreign')
                ->references(['organization_id', 'id'])
                ->on('api_specification_versions')
                ->restrictOnDelete();
        });

        DB::statement("ALTER TABLE api_endpoints ADD CONSTRAINT api_endpoints_method_check CHECK (method IN ('GET', 'PUT', 'POST', 'DELETE', 'OPTIONS', 'HEAD', 'PATCH', 'TRACE'))");
        DB::statement("ALTER TABLE api_endpoints ADD CONSTRAINT api_endpoints_path_check CHECK (path LIKE '/%')");

        // Deterministic identity: OpenAPI treats /pets/{id} and /pets/{petId}
        // as the same path, so one operation per method and path template
        // in each version. Also indexes the foreign key. The parser applies
        // the same rule, so an import gets a 422, not this constraint.
        DB::statement("CREATE UNIQUE INDEX api_endpoints_identity_unique ON api_endpoints (api_specification_version_id, method, regexp_replace(path, '\\{[^}]*\\}', '{}', 'g'))");

        // A projection of an immutable version is immutable too (ADR-0014,
        // decisions 3 and 6); the function comes from the versions table.
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER api_endpoints_append_only
            BEFORE UPDATE OR DELETE ON api_endpoints
            FOR EACH ROW EXECUTE FUNCTION napi_prevent_row_mutation();
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_endpoints');

        Schema::table('api_specification_versions', function (Blueprint $table) {
            $table->dropUnique('api_specification_versions_organization_id_id_unique');
        });
    }
};
