<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Carbon\Carbon;

class Streamer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'username',
        'full_name',
        'current_stream_id',
    ];

    protected $with = ['activeSubscription.subscriptionPlan'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(StreamerSubscription::class);
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(StreamerSubscription::class)
            ->where('status', 'active')
            ->where('end_date', '>', now())
            ->latest('end_date');
    }

    public function plannedStreams(): HasMany
    {
        return $this->hasMany(PlannedStream::class);
    }

    public function currentStream(): BelongsTo
    {
        return $this->belongsTo(PlannedStream::class, 'current_stream_id');
    }

    public function paymentsAsPayee(): HasMany
    {
        return $this->hasMany(Payment::class, 'payee_id');
    }

    public function hasActiveSubscription(): bool
    {
        return $this->activeSubscription()->exists();
    }

    public function getActiveSubscription(): ?StreamerSubscription
    {
        return $this->activeSubscription;
    }

    public function getDailyStreamingLimit(): float
    {
        $activeSubscription = $this->getActiveSubscription();

        if (!$activeSubscription || !$activeSubscription->subscriptionPlan) {
            return 0;
        }

        return $activeSubscription->subscriptionPlan->duration_hours;
    }

    public function getTotalStreamDurationForDate(string $date): float
    {
        $streams = $this->plannedStreams()
            ->forDate($date)
            ->whereIn('status', [PlannedStream::STATUS_SCHEDULED, PlannedStream::STATUS_LIVE, PlannedStream::STATUS_COMPLETED])
            ->get();

        return $streams->sum(function ($stream) {
            return $stream->getDurationInHours();
        });
    }

    public function canAddStreamForDate(string $date, int $durationMinutes): bool
    {
        $dailyLimit = $this->getDailyStreamingLimit();

        if ($dailyLimit <= 0) {
            return false; // No active subscription
        }

        $currentDuration = $this->getTotalStreamDurationForDate($date);
        $newStreamDuration = $durationMinutes / 60; // Convert to hours

        return ($currentDuration + $newStreamDuration) <= $dailyLimit;
    }

    public function getRemainingStreamTimeForDate(string $date): float
    {
        $dailyLimit = $this->getDailyStreamingLimit();
        $currentDuration = $this->getTotalStreamDurationForDate($date);

        return max(0, $dailyLimit - $currentDuration);
    }

    /**
     * Get total available hours for the entire subscription period
     */
    public function getTotalAvailableHours(): float
    {
        $activeSubscription = $this->getActiveSubscription();

        if (!$activeSubscription || !$activeSubscription->subscriptionPlan) {
            return 0;
        }

        // Use the subscription plan's duration_days and duration_hours directly
        $totalDays = $activeSubscription->subscriptionPlan->duration_days;
        $dailyHours = $activeSubscription->subscriptionPlan->duration_hours;

        return $totalDays * $dailyHours;
    }

    /**
     * Get total hours used across the entire subscription period
     */
    public function getTotalUsedHours(): float
    {
        $activeSubscription = $this->getActiveSubscription();

        if (!$activeSubscription) {
            return 0;
        }

        $startDate = \Carbon\Carbon::parse($activeSubscription->start_date);
        $endDate = \Carbon\Carbon::parse($activeSubscription->end_date);

        $streams = $this->plannedStreams()
            ->where('scheduled_start', '>=', $startDate)
            ->where('scheduled_start', '<=', $endDate)
            ->whereIn('status', [PlannedStream::STATUS_SCHEDULED, PlannedStream::STATUS_LIVE, PlannedStream::STATUS_COMPLETED])
            ->get();

        $totalHours = $streams->sum(function ($stream) {
            return $stream->getDurationInHours();
        });

        \Log::info("Streamer {$this->id} - Found {$streams->count()} streams, Total hours: {$totalHours}");
        foreach ($streams as $stream) {
            \Log::info("Stream {$stream->id}: {$stream->scheduled_start}, Duration: {$stream->estimated_duration} min, Hours: " . $stream->getDurationInHours());
        }

        return $totalHours;
    }

    /**
     * Get remaining hours for the entire subscription period
     */
    public function getRemainingTotalHours(): float
    {
        $totalAvailable = $this->getTotalAvailableHours();
        $totalUsed = $this->getTotalUsedHours();

        return max(0, $totalAvailable - $totalUsed);
    }

    /**
     * Check if streamer can add a stream with given duration (using total hours system)
     */
    public function canAddStreamWithTotalHours(int $durationMinutes): bool
    {
        $remainingHours = $this->getRemainingTotalHours();
        $requestedHours = $durationMinutes / 60;

        return $requestedHours <= $remainingHours;
    }

    /**
     * Check if subscription has expired and has unused hours
     */
    public function hasExpiredWithUnusedHours(): bool
    {
        $activeSubscription = $this->getActiveSubscription();

        if (!$activeSubscription) {
            return false;
        }

        $endDate = \Carbon\Carbon::parse($activeSubscription->end_date);
        $now = \Carbon\Carbon::now();

        // Check if subscription has expired
        if ($endDate->isFuture()) {
            return false;
        }

        // Check if there are unused hours
        $remainingHours = $this->getRemainingTotalHours();
        return $remainingHours > 0;
    }

    /**
     * Get unused hours when subscription expires
     */
    public function getUnusedHoursOnExpiration(): float
    {
        if (!$this->hasExpiredWithUnusedHours()) {
            return 0;
        }

        return $this->getRemainingTotalHours();
    }

    public function isCurrentlyStreaming(): bool
    {
        return $this->current_stream_id !== null &&
            $this->currentStream &&
            $this->currentStream->isLive();
    }
}
