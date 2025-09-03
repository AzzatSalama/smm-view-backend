<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StreamerWordsLists extends Model
{
    use HasFactory;
    protected $table = 'streamer_wordslists';

    protected $fillable = [
        'streamer_id',
        'filename',
    ];
    
    public function streamer(): BelongsTo
    {
        return $this->belongsTo(Streamer::class);
    }
}
