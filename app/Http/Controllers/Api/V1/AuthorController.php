<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAuthorRequest;
use App\Http\Requests\UpdateAuthorRequest;
use App\Http\Resources\AuthorResource;
use App\Models\Author;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AuthorController extends Controller
{
    public function index(Request $request)
    {
        $authors = Author::query()
            ->withCount('books')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');

                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('nationality', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(10);

        return AuthorResource::collection($authors);
    }

    public function store(StoreAuthorRequest $request)
    {
        $data = $request->validated();

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('authors/photos', 'public');
        }

        $author = Author::create($data);

        return response()->json([
            'message' => 'Author created successfully',
            'data' => new AuthorResource($author),
        ], 201);
    }

    public function show(Author $author)
    {
        $author->loadCount('books');

        return response()->json([
            'data' => new AuthorResource($author),
        ]);
    }

    public function update(UpdateAuthorRequest $request, Author $author)
    {
        $data = $request->validated();

        if ($request->hasFile('photo')) {
            if ($author->photo && Storage::disk('public')->exists($author->photo)) {
                Storage::disk('public')->delete($author->photo);
            }

            $data['photo'] = $request->file('photo')->store('authors/photos', 'public');
        }

        $author->update($data);

        return response()->json([
            'message' => 'Author updated successfully',
            'data' => new AuthorResource($author),
        ]);
    }

    public function destroy(Author $author)
    {
        if ($author->photo && Storage::disk('public')->exists($author->photo)) {
            Storage::disk('public')->delete($author->photo);
        }

        $author->delete();

        return response()->json([
            'message' => 'Author deleted successfully',
        ]);
    }
}
