<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('streamer_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('streamer_id')->constrained('streamers')->onDelete('cascade');
            $table->foreignId('subscription_plan_id')->constrained();
            $table->timestamp('start_date');
            $table->timestamp('end_date');
            $table->enum('status', ['pending','active', 'canceled', 'expired'])->default('pending');
            $table->timestamps();

            $table->index(['streamer_id', 'subscription_plan_id']);
            $table->index('end_date');
        });
    }

    public function down()
    {
        Schema::dropIfExists('streamer_subscriptions');
    }
};
