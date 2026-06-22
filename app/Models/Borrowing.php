<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Borrowing extends Model
{
    protected $fillable = [
        'member_id',
        'book_id',
        'borrowed_at',
        'due_date',
        'returned_at',
        'status',
        'notes',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
    public function fine(): HasOne
    {
        return $this->hasOne(Fine::class);
    }
}
