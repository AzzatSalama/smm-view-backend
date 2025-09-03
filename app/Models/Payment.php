<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_id',
        // 'payer_id',
        'payee_id',
        'amount',
        'currency',
        'payment_method',
        'transaction_id',
        'status',
        'description',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_REFUNDED = 'refunded';

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(StreamerSubscription::class, 'subscription_id');
    }

    // public function payer(): BelongsTo
    // {
    //     return $this->belongsTo(User::class, 'payer_id');
    // }

    public function streamer(): BelongsTo
    {
        return $this->belongsTo(Streamer::class, 'payee_id');
    }

    public function payee(): BelongsTo
    {
        return $this->belongsTo(Streamer::class, 'payee_id');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }
}
