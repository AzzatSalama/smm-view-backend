<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class PlannedStream extends Model
{
    use HasFactory;

    protected $fillable = [
        'streamer_id',
        'title',
        'description',
        'scheduled_start',
        'estimated_duration',
        'status',
        'wordlist_id',
    ];

    protected $casts = [
        'scheduled_start' => 'datetime',
    ];

    const STATUS_SCHEDULED = 'scheduled';
    const STATUS_LIVE = 'live';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    public function streamer(): BelongsTo
    {
        return $this->belongsTo(Streamer::class);
    }

    public function wordlist(): BelongsTo
    {
        return $this->belongsTo(StreamerWordsLists::class, 'wordlist_id');
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', self::STATUS_SCHEDULED);
    }

    public function scopeLive($query)
    {
        return $query->where('status', self::STATUS_LIVE);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeForDate($query, $date)
    {
        $startOfDay = Carbon::parse($date)->startOfDay();
        $endOfDay = Carbon::parse($date)->endOfDay();

        return $query->whereBetween('scheduled_start', [$startOfDay, $endOfDay]);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('scheduled_start', '>', now())
            ->where('status', self::STATUS_SCHEDULED);
    }

    public function getDurationInHours(): float
    {
        return $this->estimated_duration / 60;
    }

    public function isLive(): bool
    {
        return $this->status === self::STATUS_LIVE;
    }

    public function isScheduled(): bool
    {
        return $this->status === self::STATUS_SCHEDULED;
    }

    public function canBeStarted(): bool
    {
        return $this->isScheduled() &&
            $this->scheduled_start <= now()->addMinutes(15); // Allow starting 15 minutes early
    }
}
