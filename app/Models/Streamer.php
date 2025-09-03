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

    public function isCurrentlyStreaming(): bool
    {
        return $this->current_stream_id !== null &&
            $this->currentStream &&
            $this->currentStream->isLive();
    }
}
