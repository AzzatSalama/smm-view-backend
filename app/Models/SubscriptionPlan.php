<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price',
        'duration_days',
        'duration_hours',
        'views_delivered',
        'chat_messages_delivered',
        'is_active',
        'features',
        'is_most_popular',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        'is_most_popular' => 'boolean',
        'features' => 'array',
    ];

    public function streamerSubscriptions(): HasMany
    {
        return $this->hasMany(StreamerSubscription::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeMostPopular($query)
    {
        return $query->where('is_most_popular', true);
    }
}
