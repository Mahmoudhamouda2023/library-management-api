<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBorrowingRequest;
use App\Http\Resources\BorrowingResource;
use App\Models\Book;
use App\Models\Borrowing;
use App\Models\Fine;
use App\Models\Member;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BorrowingController extends Controller
{
    public function index(Request $request)
    {
        $borrowings = Borrowing::query()
            ->with(['member', 'book.author', 'book.category'])
            ->when($request->filled('member_id'), function ($query) use ($request) {
                $query->where('member_id', $request->input('member_id'));
            })
            ->when($request->filled('book_id'), function ($query) use ($request) {
                $query->where('book_id', $request->input('book_id'));
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->input('status'));
            })
            ->latest()
            ->paginate(10);

        return BorrowingResource::collection($borrowings);
    }

    public function store(StoreBorrowingRequest $request)
    {
        $data = $request->validated();

        $borrowing = DB::transaction(function () use ($data) {
            $member = Member::findOrFail($data['member_id']);

            if ($member->status !== 'active') {
                abort(response()->json([
                    'message' => 'Only active members can borrow books.',
                ], 422));
            }

            $book = Book::where('id', $data['book_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($book->status !== 'active') {
                abort(response()->json([
                    'message' => 'This book is not active.',
                ], 422));
            }

            if ($book->review_status !== 'approved') {
                abort(response()->json([
                    'message' => 'This book is not approved yet.',
                ], 422));
            }

            if ($book->available_copies <= 0) {
                abort(response()->json([
                    'message' => 'No available copies for this book.',
                ], 422));
            }

            $alreadyBorrowed = Borrowing::where('member_id', $member->id)
                ->where('book_id', $book->id)
                ->where('status', 'borrowed')
                ->exists();

            if ($alreadyBorrowed) {
                abort(response()->json([
                    'message' => 'This member already borrowed this book.',
                ], 422));
            }

            $borrowedAt = $data['borrowed_at'] ?? now()->toDateString();
            $dueDate = $data['due_date'] ?? now()->addDays(14)->toDateString();

            $book->decrement('available_copies');

            return Borrowing::create([
                'member_id' => $member->id,
                'book_id' => $book->id,
                'borrowed_at' => $borrowedAt,
                'due_date' => $dueDate,
                'status' => 'borrowed',
                'notes' => $data['notes'] ?? null,
            ]);
        });

        $borrowing->load(['member', 'book.author', 'book.category']);

        return response()->json([
            'message' => 'Book borrowed successfully',
            'data' => new BorrowingResource($borrowing),
        ], 201);
    }

    public function show(Borrowing $borrowing)
    {
        $borrowing->load(['member', 'book.author', 'book.category']);

        return response()->json([
            'data' => new BorrowingResource($borrowing),
        ]);
    }

    public function returnBook(Borrowing $borrowing)
    {
        if ($borrowing->status === 'returned') {
            return response()->json([
                'message' => 'This book has already been returned.',
            ], 422);
        }

        DB::transaction(function () use ($borrowing) {
            $book = Book::where('id', $borrowing->book_id)
                ->lockForUpdate()
                ->firstOrFail();

            $returnedAt = now()->toDateString();

            $borrowing->update([
                'returned_at' => $returnedAt,
                'status' => 'returned',
            ]);

            if ($book->available_copies < $book->total_copies) {
                $book->increment('available_copies');
            }

            $dueDate = Carbon::parse($borrowing->due_date);
            $returnedDate = Carbon::parse($returnedAt);

            if ($returnedDate->greaterThan($dueDate)) {
                $daysLate = (int) $dueDate->diffInDays($returnedDate);
                $finePerDay = 1;

                Fine::firstOrCreate(
                    [
                        'borrowing_id' => $borrowing->id,
                    ],
                    [
                        'member_id' => $borrowing->member_id,
                        'days_late' => $daysLate,
                        'amount' => $daysLate * $finePerDay,
                        'status' => 'unpaid',
                        'notes' => 'Auto generated for late return.',
                    ]
                );
            }
        });

        $borrowing->refresh();
        $borrowing->load(['member', 'book.author', 'book.category']);

        return response()->json([
            'message' => 'Book returned successfully',
            'data' => new BorrowingResource($borrowing),
        ]);
    }

    public function overdue()
    {
        $borrowings = Borrowing::query()
            ->with(['member', 'book.author', 'book.category'])
            ->where('status', 'borrowed')
            ->whereDate('due_date', '<', now()->toDateString())
            ->latest()
            ->paginate(10);

        return BorrowingResource::collection($borrowings);
    }
}
