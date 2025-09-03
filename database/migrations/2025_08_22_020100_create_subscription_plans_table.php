<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 8, 2);
            $table->integer('duration_days')->default(30);
            $table->integer('duration_hours')->default(0);
            $table->integer('views_delivered')->default(0);
            $table->integer('chat_messages_delivered')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('features')->nullable();
            $table->boolean('is_most_popular')->default(false);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('subscription_plans');
    }
};