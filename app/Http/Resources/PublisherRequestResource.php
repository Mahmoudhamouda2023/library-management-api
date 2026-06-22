<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublisherRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'user' => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
                'photo' => $this->user?->photo,
                'photo_url' => $this->user?->photo ? asset('storage/' . $this->user->photo) : null,
            ],

            'display_name' => $this->display_name,
            'nationality' => $this->nationality,
            'birth_date' => $this->birth_date,
            'bio' => $this->bio,
            'photo' => $this->photo,
            'photo_url' => $this->photo ? asset('storage/' . $this->photo) : null,
            'avatar_url' => $this->photo ? asset('storage/' . $this->photo) : null,
            'status' => $this->status,

            'reviewed_by' => [
                'id' => $this->reviewer?->id,
                'name' => $this->reviewer?->name,
                'email' => $this->reviewer?->email,
            ],

            'reviewed_at' => $this->reviewed_at,
            'rejection_reason' => $this->rejection_reason,

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
