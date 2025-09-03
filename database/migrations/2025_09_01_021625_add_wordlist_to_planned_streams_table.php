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
            $table->foreignId('wordlist_id')->nullable()->constrained('streamer_wordslists')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('planned_streams', function (Blueprint $table) {
            $table->dropForeign(['wordlist_id']);
            $table->dropColumn('wordlist_id');
        });
    }
};
