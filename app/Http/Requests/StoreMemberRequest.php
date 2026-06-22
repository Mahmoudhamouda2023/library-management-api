<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'membership_number' => 'required|string|max:191|unique:members,membership_number',
            'name' => 'required|string|max:191',
            'email' => 'nullable|email|max:191|unique:members,email',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'joined_at' => 'nullable|date',
            'status' => 'nullable|string|in:active,inactive,suspended',
        ];
    }
}
