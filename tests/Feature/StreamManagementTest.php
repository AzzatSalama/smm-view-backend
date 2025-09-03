<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Streamer;
use App\Models\SubscriptionPlan;
use App\Models\StreamerSubscription;
use App\Models\PlannedStream;
use Laravel\Sanctum\Sanctum;
use Carbon\Carbon;

class StreamManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create subscription plans
        $this->artisan('db:seed', ['--class' => 'SubscriptionPlanSeeder']);
    }

    public function test_streamer_can_add_stream_within_daily_limit()
    {
        // Create a streamer with Pro subscription (5 hours daily)
        $user = User::factory()->create(['role' => 'streamer']);
        $streamer = Streamer::factory()->create(['user_id' => $user->id]);

        $proPlan = SubscriptionPlan::where('name', 'Pro')->first();
        StreamerSubscription::create([
            'streamer_id' => $streamer->id,
            'subscription_plan_id' => $proPlan->id,
            'amount' => $proPlan->price,
            'start_date' => now(),
            'end_date' => now()->addDays(30),
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/streamer/streams', [
            'title' => 'Test Stream',
            'description' => 'A test stream',
            'scheduled_start' => now()->addHours(2)->toISOString(),
            'estimated_duration' => 120, // 2 hours
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Stream scheduled successfully',
                'remaining_daily_hours' => 3, // 5 - 2 = 3 hours remaining
            ]);

        $this->assertDatabaseHas('planned_streams', [
            'streamer_id' => $streamer->id,
            'title' => 'Test Stream',
            'estimated_duration' => 120,
            'status' => 'scheduled',
        ]);
    }

    public function test_streamer_cannot_add_stream_exceeding_daily_limit()
    {
        // Create a streamer with Basic subscription (2 hours daily)
        $user = User::factory()->create(['role' => 'streamer']);
        $streamer = Streamer::factory()->create(['user_id' => $user->id]);

        $basicPlan = SubscriptionPlan::where('name', 'Basic')->first();
        StreamerSubscription::create([
            'streamer_id' => $streamer->id,
            'subscription_plan_id' => $basicPlan->id,
            'amount' => $basicPlan->price,
            'start_date' => now(),
            'end_date' => now()->addDays(30),
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/streamer/streams', [
            'title' => 'Long Stream',
            'description' => 'A stream that exceeds daily limit',
            'scheduled_start' => now()->addHours(2)->toISOString(),
            'estimated_duration' => 180, // 3 hours (exceeds 2-hour limit)
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Adding this stream would exceed your daily streaming limit',
                'daily_limit_hours' => 2,
                'remaining_hours' => 2,
                'requested_hours' => 3,
            ]);

        $this->assertDatabaseMissing('planned_streams', [
            'streamer_id' => $streamer->id,
            'title' => 'Long Stream',
        ]);
    }

    public function test_streamer_cannot_add_stream_without_active_subscription()
    {
        $user = User::factory()->create(['role' => 'streamer']);
        $streamer = Streamer::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/streamer/streams', [
            'title' => 'Test Stream',
            'scheduled_start' => now()->addHours(2)->toISOString(),
            'estimated_duration' => 60,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'You need an active subscription to schedule streams'
            ]);
    }

    public function test_streamer_can_add_multiple_streams_within_daily_limit()
    {
        $user = User::factory()->create(['role' => 'streamer']);
        $streamer = Streamer::factory()->create(['user_id' => $user->id]);

        $proPlan = SubscriptionPlan::where('name', 'Pro')->first();
        StreamerSubscription::create([
            'streamer_id' => $streamer->id,
            'subscription_plan_id' => $proPlan->id,
            'amount' => $proPlan->price,
            'start_date' => now(),
            'end_date' => now()->addDays(30),
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        // Add first stream (2 hours)
        $response1 = $this->postJson('/api/streamer/streams', [
            'title' => 'Morning Stream',
            'scheduled_start' => now()->addHours(2)->toISOString(),
            'estimated_duration' => 120,
        ]);

        $response1->assertStatus(201);

        // Add second stream (2 hours) - should still work (total 4 hours < 5 hour limit)
        $response2 = $this->postJson('/api/streamer/streams', [
            'title' => 'Evening Stream',
            'scheduled_start' => now()->addHours(8)->toISOString(),
            'estimated_duration' => 120,
        ]);

        $response2->assertStatus(201)
            ->assertJson([
                'remaining_daily_hours' => 1, // 5 - 4 = 1 hour remaining
            ]);

        // Try to add third stream (2 hours) - should fail (would exceed limit)
        $response3 = $this->postJson('/api/streamer/streams', [
            'title' => 'Night Stream',
            'scheduled_start' => now()->addHours(12)->toISOString(),
            'estimated_duration' => 120,
        ]);

        $response3->assertStatus(422)
            ->assertJson([
                'message' => 'Adding this stream would exceed your daily streaming limit',
            ]);
    }

    public function test_streamer_can_update_stream_within_limits()
    {
        $user = User::factory()->create(['role' => 'streamer']);
        $streamer = Streamer::factory()->create(['user_id' => $user->id]);

        $proPlan = SubscriptionPlan::where('name', 'Pro')->first();
        StreamerSubscription::create([
            'streamer_id' => $streamer->id,
            'subscription_plan_id' => $proPlan->id,
            'amount' => $proPlan->price,
            'start_date' => now(),
            'end_date' => now()->addDays(30),
            'status' => 'active',
        ]);

        $stream = PlannedStream::create([
            'streamer_id' => $streamer->id,
            'title' => 'Original Stream',
            'scheduled_start' => now()->addHours(2),
            'estimated_duration' => 60, // 1 hour
            'status' => 'scheduled',
        ]);

        Sanctum::actingAs($user);

        // Update duration to 3 hours (within 5-hour limit)
        $response = $this->putJson("/api/streamer/streams/{$stream->id}", [
            'estimated_duration' => 180,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Stream updated successfully',
            ]);

        $this->assertDatabaseHas('planned_streams', [
            'id' => $stream->id,
            'estimated_duration' => 180,
        ]);
    }

    public function test_streamer_cannot_update_stream_exceeding_limits()
    {
        $user = User::factory()->create(['role' => 'streamer']);
        $streamer = Streamer::factory()->create(['user_id' => $user->id]);

        $basicPlan = SubscriptionPlan::where('name', 'Basic')->first();
        StreamerSubscription::create([
            'streamer_id' => $streamer->id,
            'subscription_plan_id' => $basicPlan->id,
            'amount' => $basicPlan->price,
            'start_date' => now(),
            'end_date' => now()->addDays(30),
            'status' => 'active',
        ]);

        $stream = PlannedStream::create([
            'streamer_id' => $streamer->id,
            'title' => 'Original Stream',
            'scheduled_start' => now()->addHours(2),
            'estimated_duration' => 60, // 1 hour
            'status' => 'scheduled',
        ]);

        Sanctum::actingAs($user);

        // Try to update duration to 3 hours (exceeds 2-hour daily limit)
        $response = $this->putJson("/api/streamer/streams/{$stream->id}", [
            'estimated_duration' => 180,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Updating this stream would exceed your daily streaming limit',
                'daily_limit_hours' => 2,
            ]);

        // Verify stream wasn't updated
        $this->assertDatabaseHas('planned_streams', [
            'id' => $stream->id,
            'estimated_duration' => 60, // Original duration
        ]);
    }

    public function test_streamer_can_start_and_end_stream()
    {
        $user = User::factory()->create(['role' => 'streamer']);
        $streamer = Streamer::factory()->create(['user_id' => $user->id]);

        $proPlan = SubscriptionPlan::where('name', 'Pro')->first();
        StreamerSubscription::create([
            'streamer_id' => $streamer->id,
            'subscription_plan_id' => $proPlan->id,
            'amount' => $proPlan->price,
            'start_date' => now(),
            'end_date' => now()->addDays(30),
            'status' => 'active',
        ]);

        $stream = PlannedStream::create([
            'streamer_id' => $streamer->id,
            'title' => 'Live Stream',
            'scheduled_start' => now()->subMinutes(5), // Started 5 minutes ago
            'estimated_duration' => 120,
            'status' => 'scheduled',
        ]);

        Sanctum::actingAs($user);

        // Start the stream
        $response = $this->postJson("/api/streamer/streams/{$stream->id}/start");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Stream started successfully',
            ]);

        $this->assertDatabaseHas('planned_streams', [
            'id' => $stream->id,
            'status' => 'live',
        ]);

        $this->assertDatabaseHas('streamers', [
            'id' => $streamer->id,
            'current_stream_id' => $stream->id,
        ]);

        // End the stream
        $response = $this->postJson("/api/streamer/streams/{$stream->id}/end");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Stream ended successfully',
            ]);

        $this->assertDatabaseHas('planned_streams', [
            'id' => $stream->id,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('streamers', [
            'id' => $streamer->id,
            'current_stream_id' => null,
        ]);
    }

    public function test_get_streaming_stats()
    {
        $user = User::factory()->create(['role' => 'streamer']);
        $streamer = Streamer::factory()->create(['user_id' => $user->id]);

        $proPlan = SubscriptionPlan::where('name', 'Pro')->first();
        StreamerSubscription::create([
            'streamer_id' => $streamer->id,
            'subscription_plan_id' => $proPlan->id,
            'amount' => $proPlan->price,
            'start_date' => now(),
            'end_date' => now()->addDays(30),
            'status' => 'active',
        ]);

        // Create some streams for today
        PlannedStream::create([
            'streamer_id' => $streamer->id,
            'title' => 'Morning Stream',
            'scheduled_start' => now()->startOfDay()->addHours(10),
            'estimated_duration' => 120, // 2 hours
            'status' => 'completed',
        ]);

        PlannedStream::create([
            'streamer_id' => $streamer->id,
            'title' => 'Evening Stream',
            'scheduled_start' => now()->startOfDay()->addHours(20),
            'estimated_duration' => 90, // 1.5 hours
            'status' => 'scheduled',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/streamer/streaming-stats?date=' . now()->format('Y-m-d'));

        $response->assertStatus(200)
            ->assertJson([
                'date' => now()->format('Y-m-d'),
                'daily_limit_hours' => 5,
                'used_hours' => 3.5, // 2 + 1.5 hours
                'remaining_hours' => 1.5, // 5 - 3.5 hours
            ]);
    }
}
