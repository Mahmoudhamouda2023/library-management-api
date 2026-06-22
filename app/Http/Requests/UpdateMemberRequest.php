<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'membership_number' => [
                'sometimes',
                'required',
                'string',
                'max:191',
                Rule::unique('members', 'membership_number')->ignore($this->member),
            ],
            'name' => 'sometimes|required|string|max:191',
            'email' => [
                'nullable',
                'email',
                'max:191',
                Rule::unique('members', 'email')->ignore($this->member),
            ],
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'joined_at' => 'nullable|date',
            'status' => 'nullable|string|in:active,inactive,suspended',
        ];
    }
}
