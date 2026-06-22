<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BorrowingResource;
use App\Http\Resources\FineResource;
use App\Http\Resources\ReservationResource;
use App\Models\Book;
use App\Models\Borrowing;
use App\Models\Fine;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class MemberPortalController extends Controller
{
    private function currentMember(Request $request)
    {
        $user = $request->user();

        if ($user->hasRole('admin') || $user->hasRole('librarian') || $user->hasRole('publisher')) {
            abort(response()->json([
                'message' => 'This operation is available for regular members only.',
            ], 403));
        }

        $member = $user->member;

        if (!$member) {
            abort(response()->json([
                'message' => 'No member profile linked to this user.',
            ], 422));
        }

        return $member;
    }

    public function dashboard(Request $request)
    {
        $member = $this->currentMember($request);

        return response()->json([
            'data' => [
                'member' => [
                    'id' => $member->id,
                    'membership_number' => $member->membership_number,
                    'name' => $member->name,
                    'status' => $member->status,
                ],

                'borrowed_count' => Borrowing::where('member_id', $member->id)
                    ->where('status', 'borrowed')
                    ->count(),

                'returned_count' => Borrowing::where('member_id', $member->id)
                    ->where('status', 'returned')
                    ->count(),

                'overdue_count' => Borrowing::where('member_id', $member->id)
                    ->where('status', 'borrowed')
                    ->whereDate('due_date', '<', now()->toDateString())
                    ->count(),

                'unpaid_fines_count' => Fine::where('member_id', $member->id)
                    ->where('status', 'unpaid')
                    ->count(),
            ],
        ]);
    }

    public function myBorrowings(Request $request)
    {
        $member = $this->currentMember($request);

        $borrowings = Borrowing::query()
            ->with(['member', 'book'])
            ->where('member_id', $member->id)
            ->latest()
            ->paginate(10);

        return BorrowingResource::collection($borrowings);
    }

    public function borrowBook(Request $request, Book $book)
    {
        $member = $this->currentMember($request);

        $data = $request->validate([
            'due_date' => 'required|date|after_or_equal:today',
            'notes' => 'nullable|string',
        ]);

        $borrowing = DB::transaction(function () use ($member, $book, $data) {
            $book = Book::where('id', $book->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($member->status !== 'active') {
                abort(response()->json([
                    'message' => 'Your membership is not active.',
                ], 422));
            }

            if ($book->status !== 'active' || $book->review_status !== 'approved') {
                abort(response()->json([
                    'message' => 'This book is not available for members.',
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
                    'message' => 'You already borrowed this book.',
                ], 422));
            }

            $book->decrement('available_copies');

            return Borrowing::create([
                'member_id' => $member->id,
                'book_id' => $book->id,
                'borrowed_at' => now()->toDateString(),
                'due_date' => $data['due_date'],
                'status' => 'borrowed',
                'notes' => $data['notes'] ?? null,
            ]);
        });

        $borrowing->load(['member', 'book']);

        return response()->json([
            'message' => 'Book borrowed successfully',
            'data' => new BorrowingResource($borrowing),
        ], 201);
    }

    public function returnMyBook(Request $request, Borrowing $borrowing)
    {
        $this->currentMember($request);

        Gate::authorize('returnOwn', $borrowing);

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
        $borrowing->load(['member', 'book']);

        return response()->json([
            'message' => 'Book returned successfully',
            'data' => new BorrowingResource($borrowing),
        ]);
    }

    public function myReservations(Request $request)
    {
        $member = $this->currentMember($request);

        $reservations = Reservation::query()
            ->with(['member', 'book'])
            ->where('member_id', $member->id)
            ->latest()
            ->paginate(10);

        return ReservationResource::collection($reservations);
    }

    public function reserveBook(Request $request, Book $book)
    {
        $member = $this->currentMember($request);

        $data = $request->validate([
            'notes' => 'nullable|string',
        ]);

        if ($member->status !== 'active') {
            return response()->json([
                'message' => 'Your membership is not active.',
            ], 422);
        }

        if ($book->status !== 'active' || $book->review_status !== 'approved') {
            return response()->json([
                'message' => 'This book is not available for members.',
            ], 422);
        }

        $alreadyReserved = Reservation::where('member_id', $member->id)
            ->where('book_id', $book->id)
            ->where('status', 'pending')
            ->exists();

        if ($alreadyReserved) {
            return response()->json([
                'message' => 'You already have a pending reservation for this book.',
            ], 422);
        }

        $alreadyBorrowed = Borrowing::where('member_id', $member->id)
            ->where('book_id', $book->id)
            ->where('status', 'borrowed')
            ->exists();

        if ($alreadyBorrowed) {
            return response()->json([
                'message' => 'You already borrowed this book.',
            ], 422);
        }

        $reservation = Reservation::create([
            'member_id' => $member->id,
            'book_id' => $book->id,
            'reserved_at' => now()->toDateString(),
            'expires_at' => now()->addDays(3)->toDateString(),
            'status' => 'pending',
            'notes' => $data['notes'] ?? null,
        ]);

        $reservation->load(['member', 'book']);

        return response()->json([
            'message' => 'Book reserved successfully',
            'data' => new ReservationResource($reservation),
        ], 201);
    }

    public function cancelMyReservation(Request $request, Reservation $reservation)
    {
        $this->currentMember($request);

        Gate::authorize('cancelOwn', $reservation);

        if ($reservation->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending reservations can be cancelled.',
            ], 422);
        }

        $reservation->update([
            'status' => 'cancelled',
        ]);

        $reservation->load(['member', 'book']);

        return response()->json([
            'message' => 'Reservation cancelled successfully',
            'data' => new ReservationResource($reservation),
        ]);
    }

    public function myFines(Request $request)
    {
        $member = $this->currentMember($request);

        $fines = Fine::query()
            ->with(['member', 'borrowing.book'])
            ->where('member_id', $member->id)
            ->latest()
            ->paginate(10);

        return FineResource::collection($fines);
    }
}
