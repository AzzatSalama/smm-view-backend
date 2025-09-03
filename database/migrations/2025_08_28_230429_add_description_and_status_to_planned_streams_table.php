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
        Schema::table('planned_streams', function (Blueprint $table) {
            $table->text('description')->nullable()->after('title');
            $table->enum('status', ['scheduled', 'live', 'completed', 'cancelled'])
                ->default('scheduled')
                ->after('estimated_duration');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('planned_streams', function (Blueprint $table) {
            $table->dropColumn(['description', 'status']);
        });
    }
};
