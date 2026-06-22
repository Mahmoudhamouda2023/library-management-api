<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BorrowingResource extends JsonResource
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
            ],

            'borrowed_at' => $this->borrowed_at,
            'due_date' => $this->due_date,
            'returned_at' => $this->returned_at,
            'status' => $this->status,
            'notes' => $this->notes,

            'is_overdue' => $this->status === 'borrowed' && $this->due_date < now()->toDateString(),

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
