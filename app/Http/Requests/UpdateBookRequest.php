<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $book = $this->route('book');
        $bookId = is_object($book) ? $book->getKey() : $book;

        return [
            'author_id' => 'sometimes|required|exists:authors,id',
            'category_id' => 'sometimes|required|exists:categories,id',
            'title' => 'sometimes|required|string|max:191',
            'isbn' => [
                'sometimes',
                'required',
                'string',
                'max:191',
                Rule::unique('books', 'isbn')->ignore($bookId),
            ],
            'description' => 'nullable|string',
            'published_year' => 'nullable|integer|min:1000|max:' . date('Y'),
            'total_copies' => 'sometimes|required|integer|min:1',
            'available_copies' => 'nullable|integer|min:0',
            'shelf_location' => 'nullable|string|max:191',
            'status' => 'nullable|string|in:active,inactive',
            'cover_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ];
    }
}
