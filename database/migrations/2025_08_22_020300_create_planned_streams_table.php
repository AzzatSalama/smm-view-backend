<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('planned_streams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('streamer_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->timestamp('scheduled_start');
            $table->integer('estimated_duration')->nullable(); // in minutes
            $table->timestamps();

            $table->index(['streamer_id', 'scheduled_start']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('planned_streams');
    }
};