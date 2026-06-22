<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Member extends Model
{
    protected $fillable = [
        'membership_number',
        'name',
        'email',
        'phone',
        'address',
        'joined_at',
        'status',
        'user_id',
    ];





    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    public function borrowings(): HasMany
    {
        return $this->hasMany(Borrowing::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }
    public function fines(): HasMany
    {
        return $this->hasMany(Fine::class);
    }
}
