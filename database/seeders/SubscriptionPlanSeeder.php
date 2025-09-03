<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\SubscriptionPlan;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Basic',
                'description' => 'Perfect for getting started with streaming',
                'price' => 9.99,
                'duration_days' => 30,
                'duration_hours' => 2, // 2 hours per day
                'views_delivered' => 1000,
                'chat_messages_delivered' => 500,
                'is_active' => true,
                'features' => [
                    '2 hours streaming per day',
                    'Basic analytics',
                    'Standard support'
                ],
                'is_most_popular' => false,
            ],
            [
                'name' => 'Pro',
                'description' => 'For serious streamers who need more time',
                'price' => 19.99,
                'duration_days' => 30,
                'duration_hours' => 5, // 5 hours per day
                'views_delivered' => 5000,
                'chat_messages_delivered' => 2500,
                'is_active' => true,
                'features' => [
                    '5 hours streaming per day',
                    'Advanced analytics',
                    'Priority support',
                    'Custom overlays'
                ],
                'is_most_popular' => true,
            ],
            [
                'name' => 'Premium',
                'description' => 'Unlimited streaming for professional streamers',
                'price' => 39.99,
                'duration_days' => 30,
                'duration_hours' => 12, // 12 hours per day
                'views_delivered' => 20000,
                'chat_messages_delivered' => 10000,
                'is_active' => true,
                'features' => [
                    '12 hours streaming per day',
                    'Premium analytics',
                    '24/7 support',
                    'Custom branding',
                    'Multi-platform streaming'
                ],
                'is_most_popular' => false,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::create($plan);
        }
    }
}
