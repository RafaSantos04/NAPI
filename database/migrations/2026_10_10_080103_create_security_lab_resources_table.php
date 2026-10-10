<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Synthetic resources the Security Lab reads. They exist so that no real
     * table (users, profiles, tokens, audit_logs) is ever the target of a
     * deliberately vulnerable scenario.
     */
    public function up(): void
    {
        Schema::create('security_lab_resources', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('owner_user_id');
            $table->string('name', 100);
            $table->text('content');
            $table->timestamps();

            $table->foreign('owner_user_id')->references('id')->on('users')->onDelete('cascade');

            // Also the index for "resources of this owner".
            $table->unique(['owner_user_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('security_lab_resources');
    }
};
