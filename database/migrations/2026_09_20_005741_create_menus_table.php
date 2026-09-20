<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('parent_id')->nullable();
            $table->string('label', 100);
            $table->string('route_name', 100)->unique();
            $table->string('icon', 50)->nullable();
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('parent_id');
            $table->index('order');
        });

        // Self-referencing FK added after table creation: Postgres adds the
        // ulid('id')->primary() constraint via a separate ALTER TABLE that can
        // otherwise run after an inline self-FK, causing "no unique constraint
        // matches" on the not-yet-existing primary key.
        Schema::table('menus', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('menus')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menus');
    }
};
