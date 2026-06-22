<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FineResource extends JsonResource
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

            'borrowing' => [
                'id' => $this->borrowing?->id,
                'book' => [
                    'id' => $this->borrowing?->book?->id,
                    'title' => $this->borrowing?->book?->title,
                    'isbn' => $this->borrowing?->book?->isbn,
                ],
                'borrowed_at' => $this->borrowing?->borrowed_at,
                'due_date' => $this->borrowing?->due_date,
                'returned_at' => $this->borrowing?->returned_at,
            ],

            'days_late' => $this->days_late,
            'amount' => (float) $this->amount,
            'status' => $this->status,
            'paid_at' => $this->paid_at,
            'notes' => $this->notes,

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
