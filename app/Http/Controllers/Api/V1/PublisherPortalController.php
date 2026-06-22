<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookResource;
use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PublisherPortalController extends Controller
{
    private function currentAuthor(Request $request)
    {
        $author = $request->user()->author;

        if (!$author) {
            abort(response()->json([
                'message' => 'No author profile linked to this user.',
            ], 422));
        }

        return $author;
    }

    public function dashboard(Request $request)
    {
        $author = $this->currentAuthor($request);

        return response()->json([
            'data' => [
                'author' => [
                    'id' => $author->id,
                    'name' => $author->name,
                    'nationality' => $author->nationality,
                    'birth_date' => $author->birth_date,
                    'bio' => $author->bio,
                    'photo' => $author->photo,
                    'photo_url' => $author->photo ? asset('storage/' . $author->photo) : null,
                ],

                'books_count' => Book::where('author_id', $author->id)->count(),

                'active_books_count' => Book::where('author_id', $author->id)
                    ->where('status', 'active')
                    ->count(),

                'inactive_books_count' => Book::where('author_id', $author->id)
                    ->where('status', 'inactive')
                    ->count(),

                'pending_books_count' => Book::where('author_id', $author->id)
                    ->where('review_status', 'pending')
                    ->count(),

                'approved_books_count' => Book::where('author_id', $author->id)
                    ->where('review_status', 'approved')
                    ->count(),

                'rejected_books_count' => Book::where('author_id', $author->id)
                    ->where('review_status', 'rejected')
                    ->count(),

                'total_copies' => (int) Book::where('author_id', $author->id)
                    ->sum('total_copies'),

                'available_copies' => (int) Book::where('author_id', $author->id)
                    ->sum('available_copies'),
            ],
        ]);
    }

    public function myBooks(Request $request)
    {
        $author = $this->currentAuthor($request);

        $books = Book::query()
            ->with(['author', 'category'])
            ->where('author_id', $author->id)
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');

                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('isbn', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->input('status'));
            })
            ->when($request->filled('review_status'), function ($query) use ($request) {
                $query->where('review_status', $request->input('review_status'));
            })
            ->latest()
            ->paginate(10);

        return BookResource::collection($books);
    }

    public function storeBook(Request $request)
    {
        $author = $this->currentAuthor($request);

        $data = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'title' => 'required|string|max:191',
            'isbn' => 'required|string|max:191|unique:books,isbn',
            'description' => 'nullable|string',
            'published_year' => 'nullable|integer|min:1000|max:' . date('Y'),
            'total_copies' => 'required|integer|min:1',
            'available_copies' => 'nullable|integer|min:0|lte:total_copies',
            'shelf_location' => 'nullable|string|max:191',
            'cover_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $request->file('cover_image')->store('books/covers', 'public');
        }

        $availableCopies = $data['available_copies'] ?? $data['total_copies'];

        $book = Book::create([
            'user_id' => $request->user()->id,
            'author_id' => $author->id,
            'category_id' => $data['category_id'],
            'title' => $data['title'],
            'isbn' => $data['isbn'],
            'description' => $data['description'] ?? null,
            'published_year' => $data['published_year'] ?? null,
            'total_copies' => $data['total_copies'],
            'available_copies' => $availableCopies,
            'shelf_location' => $data['shelf_location'] ?? null,
            'cover_image' => $data['cover_image'] ?? null,

            // Any book submitted by a publisher must be reviewed first.
            'status' => 'inactive',
            'review_status' => 'pending',
            'review_note' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);

        $book->load(['author', 'category']);

        return response()->json([
            'message' => 'Book submitted successfully and is waiting for review.',
            'data' => new BookResource($book),
        ], 201);
    }

    public function showBook(Request $request, Book $book)
    {
        $this->currentAuthor($request);

        Gate::authorize('viewOwn', $book);

        $book->load(['author', 'category']);

        return response()->json([
            'data' => new BookResource($book),
        ]);
    }

    public function updateBook(Request $request, Book $book)
    {
        $this->currentAuthor($request);

        Gate::authorize('updateOwn', $book);

        $data = $request->validate([
            'category_id' => 'sometimes|required|exists:categories,id',
            'title' => 'sometimes|required|string|max:191',
            'isbn' => [
                'sometimes',
                'required',
                'string',
                'max:191',
                Rule::unique('books', 'isbn')->ignore($book->id),
            ],
            'description' => 'nullable|string',
            'published_year' => 'nullable|integer|min:1000|max:' . date('Y'),
            'total_copies' => 'sometimes|required|integer|min:1',
            'available_copies' => 'nullable|integer|min:0',
            'shelf_location' => 'nullable|string|max:191',
            'cover_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($request->hasFile('cover_image')) {
            if ($book->cover_image && Storage::disk('public')->exists($book->cover_image)) {
                Storage::disk('public')->delete($book->cover_image);
            }

            $data['cover_image'] = $request->file('cover_image')->store('books/covers', 'public');
        }

        $newTotalCopies = $data['total_copies'] ?? $book->total_copies;
        $newAvailableCopies = $data['available_copies'] ?? $book->available_copies;

        if ($newAvailableCopies > $newTotalCopies) {
            return response()->json([
                'message' => 'Available copies cannot be greater than total copies.',
            ], 422);
        }

        unset($data['user_id'], $data['author_id']);

        $book->update(array_merge($data, [
            // When publisher updates the book, it goes back to review.
            'status' => 'inactive',
            'review_status' => 'pending',
            'review_note' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]));

        $book->load(['author', 'category']);

        return response()->json([
            'message' => 'Book updated successfully and is waiting for review.',
            'data' => new BookResource($book),
        ]);
    }

    public function deleteBook(Request $request, Book $book)
    {
        $this->currentAuthor($request);

        Gate::authorize('deleteOwn', $book);

        $hasBorrowings = $book->borrowings()->exists();
        $hasReservations = $book->reservations()->exists();

        if ($hasBorrowings || $hasReservations) {
            $book->update([
                'status' => 'inactive',
            ]);

            $book->load(['author', 'category']);

            return response()->json([
                'message' => 'Book has borrowings or reservations, so it was deactivated instead of deleted.',
                'data' => new BookResource($book),
            ]);
        }

        if ($book->cover_image && Storage::disk('public')->exists($book->cover_image)) {
            Storage::disk('public')->delete($book->cover_image);
        }

        $book->delete();

        return response()->json([
            'message' => 'Book deleted successfully by publisher.',
        ]);
    }
}
