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
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('creator_id')->constrained('users')->onDelete('cascade');
            $table->string('invite_code', 8)->unique();
            $table->enum('status', ['active', 'inactive', 'archived'])->default('active');
            $table->json('settings')->nullable();
            $table->json('turn_history')->nullable();
            $table->timestamp('last_turn_at')->nullable();
            $table->foreignId('current_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['invite_code']);
            $table->index(['status']);
            $table->index(['creator_id']);
            $table->index(['last_turn_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};
