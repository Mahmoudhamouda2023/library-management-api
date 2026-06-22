<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,

            'title' => $this->title,
            'isbn' => $this->isbn,
            'description' => $this->description,
            'published_year' => $this->published_year,

            'author' => [
                'id' => $this->author?->id,
                'name' => $this->author?->name,
                'photo' => $this->author?->photo,
                'photo_url' => $this->author?->photo ? asset('storage/' . $this->author->photo) : null,
            ],

            'category' => [
                'id' => $this->category?->id,
                'name' => $this->category?->name,
            ],

            'total_copies' => $this->total_copies,
            'available_copies' => $this->available_copies,
            'is_available' => $this->available_copies > 0,

            'shelf_location' => $this->shelf_location,
            'status' => $this->status,

            /*
            |--------------------------------------------------------------------------
            | Book Review Fields
            |--------------------------------------------------------------------------
            */

            'review_status' => $this->review_status,
            'review_note' => $this->review_note,
            'reviewed_by' => $this->reviewed_by,
            'reviewed_at' => $this->reviewed_at?->format('Y-m-d H:i:s'),

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'cover_image' => $this->cover_image,
            'cover_url' => $this->cover_image ? asset('storage/' . $this->cover_image) : null,
        ];
    }
}
