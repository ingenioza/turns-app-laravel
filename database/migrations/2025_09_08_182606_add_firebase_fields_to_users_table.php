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
        Schema::table('users', function (Blueprint $table) {
            $table->string('firebase_uid')->nullable()->unique()->after('id');
            $table->string('provider')->default('email')->after('password');
            $table->json('firebase_analytics_data')->nullable()->after('provider');
            $table->timestamp('last_firebase_login')->nullable()->after('firebase_analytics_data');
            
            // Add index for Firebase UID lookups
            $table->index('firebase_uid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['firebase_uid']);
            $table->dropColumn([
                'firebase_uid',
                'provider',
                'firebase_analytics_data',
                'last_firebase_login'
            ]);
        });
    }
};
