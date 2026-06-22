<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class StoreBorrowingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'member_id' => 'required|exists:members,id',
            'book_id' => 'required|exists:books,id',

            'borrowed_at' => 'nullable|date|before_or_equal:today',
            'due_date' => 'nullable|date',

            'notes' => 'nullable|string|max:1000',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->filled('due_date')) {
                $borrowedAt = $this->filled('borrowed_at')
                    ? Carbon::parse($this->input('borrowed_at'))
                    : Carbon::today();

                $dueDate = Carbon::parse($this->input('due_date'));

                if ($dueDate->lt($borrowedAt)) {
                    $validator->errors()->add(
                        'due_date',
                        'The due date must be after or equal to the borrowed date.'
                    );
                }
            }
        });
    }
}
