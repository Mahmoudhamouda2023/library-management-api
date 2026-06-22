<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'nationality' => $this->nationality,
            'birth_date' => $this->birth_date,
            'bio' => $this->bio,
            'photo' => $this->photo,
            'photo_url' => $this->photo ? asset('storage/' . $this->photo) : null,
            'avatar_url' => $this->photo ? asset('storage/' . $this->photo) : null,
            'books_count' => $this->whenCounted('books'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
