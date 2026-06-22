<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Http\Resources\BookResource;
use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BookController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        $booksQuery = Book::query()
            ->with(['author', 'category']);

        /*
         * قواعد العرض:
         * - Admin / Librarian: يرى كل الكتب.
         * - Publisher / Author: يرى كتبه فقط إذا وصل لهذا المسار يدويًا.
         * - Guest / Member: يرى الكتب المعتمدة فقط.
         */
        if ($this->userCanReviewBooks()) {
            $booksQuery->when($request->filled('review_status'), function ($query) use ($request) {
                $query->where('review_status', $request->input('review_status'));
            });
        } elseif ($this->userIsPublisherOrAuthor()) {
            $booksQuery->where(function ($query) use ($user) {
                $query->where('user_id', $user->id);

                if ($user->author) {
                    $query->orWhere('author_id', $user->author->id);
                }
            });
        } else {
            $booksQuery->where('review_status', 'approved');
        }

        $books = $booksQuery
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');

                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('isbn', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('author_id'), function ($query) use ($request) {
                $query->where('author_id', $request->input('author_id'));
            })
            ->when($request->filled('category_id'), function ($query) use ($request) {
                $query->where('category_id', $request->input('category_id'));
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->input('status'));
            })
            ->when($request->input('available') === 'true', function ($query) {
                $query->where('available_copies', '>', 0);
            })
            ->latest()
            ->paginate(10);

        return BookResource::collection($books);
    }

    public function store(StoreBookRequest $request)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $data = $request->validated();
        $data['user_id'] = $user->id;

        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $request->file('cover_image')->store('books/covers', 'public');
        }

        if (!isset($data['available_copies'])) {
            $data['available_copies'] = $data['total_copies'];
        }

        if ($this->userCanReviewBooks()) {
            $data['review_status'] = 'approved';
            $data['review_note'] = null;
            $data['reviewed_by'] = $user->id;
            $data['reviewed_at'] = now();

            if (!isset($data['status'])) {
                $data['status'] = 'active';
            }
        } else {
            $data['review_status'] = 'pending';
            $data['review_note'] = null;
            $data['reviewed_by'] = null;
            $data['reviewed_at'] = null;
            $data['status'] = 'inactive';
        }

        $book = Book::create($data);

        $book->load(['author', 'category']);

        return response()->json([
            'message' => 'Book created successfully',
            'data' => new BookResource($book),
        ], 201);
    }

    public function show(Book $book)
    {
        if (
            $book->review_status !== 'approved'
            && !$this->userCanReviewBooks()
            && !$this->userOwnsBook($book)
        ) {
            return response()->json([
                'message' => 'Book not found.',
            ], 404);
        }

        $book->load(['author', 'category']);

        return response()->json([
            'data' => new BookResource($book),
        ]);
    }

    public function update(UpdateBookRequest $request, Book $book)
    {
        if (!$this->userCanManageBook($book)) {
            return response()->json([
                'message' => 'Unauthorized. You cannot update this book.',
            ], 403);
        }

        $data = $request->validated();

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

        if (!$this->userCanReviewBooks()) {
            $data['review_status'] = 'pending';
            $data['review_note'] = null;
            $data['reviewed_by'] = null;
            $data['reviewed_at'] = null;
            $data['status'] = 'inactive';
        }

        unset($data['user_id']);

        $book->update($data);

        $book->load(['author', 'category']);

        return response()->json([
            'message' => 'Book updated successfully',
            'data' => new BookResource($book),
        ]);
    }

    public function destroy(Book $book)
    {
        if (!$this->userCanManageBook($book)) {
            return response()->json([
                'message' => 'Unauthorized. You cannot delete this book.',
            ], 403);
        }

        if ($book->cover_image && Storage::disk('public')->exists($book->cover_image)) {
            Storage::disk('public')->delete($book->cover_image);
        }

        $book->delete();

        return response()->json([
            'message' => 'Book deleted successfully',
        ]);
    }

    public function pendingBooks()
    {
        if (!$this->userCanReviewBooks()) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        $books = Book::query()
            ->with(['author', 'category'])
            ->where('review_status', 'pending')
            ->latest()
            ->paginate(10);

        return BookResource::collection($books);
    }

    public function approve(Book $book)
    {
        if (!$this->userCanReviewBooks()) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        $book->update([
            'review_status' => 'approved',
            'review_note' => null,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'status' => 'active',
        ]);

        $book->load(['author', 'category']);

        return response()->json([
            'message' => 'Book approved successfully',
            'data' => new BookResource($book),
        ]);
    }

    public function reject(Request $request, Book $book)
    {
        if (!$this->userCanReviewBooks()) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        $request->validate([
            'review_note' => 'nullable|string|max:1000',
        ]);

        $book->update([
            'review_status' => 'rejected',
            'review_note' => $request->review_note,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'status' => 'inactive',
        ]);

        $book->load(['author', 'category']);

        return response()->json([
            'message' => 'Book rejected successfully',
            'data' => new BookResource($book),
        ]);
    }

    private function userCanManageBook(Book $book): bool
    {
        return $this->userCanReviewBooks() || $this->userOwnsBook($book);
    }

    private function userOwnsBook(Book $book): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        if (!empty($book->user_id) && (int) $book->user_id === (int) $user->id) {
            return true;
        }

        if ($user->author && (int) $book->author_id === (int) $user->author->id) {
            return true;
        }

        return false;
    }

    private function userCanReviewBooks(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        if (method_exists($user, 'hasRole')) {
            return $user->hasRole('admin') || $user->hasRole('librarian');
        }

        return in_array($user->role ?? null, ['admin', 'librarian']);
    }

    private function userIsPublisherOrAuthor(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        if (method_exists($user, 'hasRole')) {
            return $user->hasRole('publisher') || $user->hasRole('author');
        }

        return in_array($user->role ?? null, ['publisher', 'author']);
    }
}
