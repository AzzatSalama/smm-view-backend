<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Streamer;
use App\Models\SubscriptionPlan;
use App\Models\StreamerSubscription;
use Carbon\Carbon;

class StreamerSubscriptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the streamer with ID = 2
        $streamer = Streamer::find(2);

        if (!$streamer) {
            $this->command->error('Streamer with ID 2 not found!');
            return;
        }

        $this->command->info("Creating subscription for streamer: {$streamer->full_name}");

        // Create the subscription plan
        $subscriptionPlan = SubscriptionPlan::create([
            'name' => $streamer->full_name . '_1',
            'description' => 'Custom subscription plan for ' . $streamer->full_name,
            'price' => 200.00,
            'duration_days' => 30,
            'duration_hours' => 5,
            'views_delivered' => 200,
            'chat_messages_delivered' => 150,
            'is_active' => false, // Private for this streamer only
            'features' => [], // Empty features array as requested
            'is_most_popular' => false,
        ]);

        $this->command->info("Created subscription plan: {$subscriptionPlan->name} (ID: {$subscriptionPlan->id})");

        // Create the streamer subscription
        $startDate = Carbon::today();
        $endDate = $startDate->copy()->addDays(30);

        $streamerSubscription = StreamerSubscription::create([
            'streamer_id' => $streamer->id,
            'subscription_plan_id' => $subscriptionPlan->id,
            'amount' => $subscriptionPlan->price,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => 'active',
            'auto_renew' => false,
        ]);

        $this->command->info("Created streamer subscription (ID: {$streamerSubscription->id})");
        $this->command->info("Subscription period: {$startDate->format('Y-m-d')} to {$endDate->format('Y-m-d')}");
        $this->command->info("Daily streaming limit: {$subscriptionPlan->duration_hours} hours");

        $this->command->info('✅ Streamer subscription seeder completed successfully!');
    }
}
