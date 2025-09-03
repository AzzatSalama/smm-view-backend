<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function streamer(): HasOne
    {
        return $this->hasOne(Streamer::class);
    }

    public function paymentsAsPayer(): HasMany
    {
        return $this->hasMany(Payment::class, 'payer_id');
    }

    public function isStreamer(): bool
    {
        return $this->role === 'streamer';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}
