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
        Schema::table('streamers', function (Blueprint $table) {
            $table->foreign('current_stream_id')
                ->references('id')
                ->on('planned_streams')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('streamers', function (Blueprint $table) {
            $table->dropForeign(['current_stream_id']);
        });
    }
};
