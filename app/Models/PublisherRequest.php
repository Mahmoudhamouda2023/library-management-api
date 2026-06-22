<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublisherRequest extends Model
{
    protected $fillable = [
        'user_id',
        'reviewed_by',
        'display_name',
        'nationality',
        'birth_date',
        'bio',
        'photo',
        'status',
        'rejection_reason',
        'reviewed_at',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
