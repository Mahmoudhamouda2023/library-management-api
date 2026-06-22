<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'member' => [
                'id' => $this->member?->id,
                'membership_number' => $this->member?->membership_number,
                'name' => $this->member?->name,
                'email' => $this->member?->email,
            ],

            'book' => [
                'id' => $this->book?->id,
                'title' => $this->book?->title,
                'isbn' => $this->book?->isbn,
                'available_copies' => $this->book?->available_copies,
            ],

            'reserved_at' => $this->reserved_at,
            'expires_at' => $this->expires_at,
            'status' => $this->status,
            'notes' => $this->notes,

            'is_expired' => $this->status === 'pending'
                && $this->expires_at
                && $this->expires_at < now()->toDateString(),

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
