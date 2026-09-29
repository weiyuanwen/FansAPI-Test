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
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->string('username')->unique();
            $table->string('display_name')->nullable();
            $table->string('avatar_url')->nullable();
            $table->unsignedBigInteger('likes')->default(0);
            $table->unsignedBigInteger('revision')->default(0)->index();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('last_successful_refresh_at')->nullable();
            $table->timestamp('last_failed_at')->nullable();
            $table->string('last_failure_reason')->nullable();
            $table->timestamp('next_refresh_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};
