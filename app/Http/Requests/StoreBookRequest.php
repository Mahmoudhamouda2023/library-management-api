<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'author_id' => 'required|exists:authors,id',
            'category_id' => 'required|exists:categories,id',
            'title' => 'required|string|max:191',
            'isbn' => 'required|string|max:191|unique:books,isbn',
            'description' => 'nullable|string',
            'published_year' => 'nullable|integer|min:1000|max:' . date('Y'),
            'total_copies' => 'required|integer|min:1',
            'available_copies' => 'nullable|integer|min:0|lte:total_copies',
            'shelf_location' => 'nullable|string|max:191',
            'status' => 'nullable|string|in:active,inactive',
            'cover_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ];
    }
}
