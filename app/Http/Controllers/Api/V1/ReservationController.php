<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BorrowingResource;
use App\Http\Resources\ReservationResource;
use App\Models\Book;
use App\Models\Borrowing;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReservationController extends Controller
{
    public function index(Request $request)
    {
        $reservations = Reservation::query()
            ->with(['member', 'book'])
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

        return ReservationResource::collection($reservations);
    }

    public function show(Reservation $reservation)
    {
        $reservation->load(['member', 'book']);

        return response()->json([
            'data' => new ReservationResource($reservation),
        ]);
    }

    public function cancel(Reservation $reservation)
    {
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

    public function fulfill(Reservation $reservation)
    {
        if ($reservation->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending reservations can be fulfilled.',
            ], 422);
        }

        if ($reservation->expires_at && $reservation->expires_at < now()->toDateString()) {
            $reservation->update([
                'status' => 'expired',
            ]);

            return response()->json([
                'message' => 'This reservation has expired.',
            ], 422);
        }

        $borrowing = DB::transaction(function () use ($reservation) {
            $book = Book::where('id', $reservation->book_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($book->status !== 'active') {
                abort(response()->json([
                    'message' => 'This book is not active.',
                ], 422));
            }

            if ($book->available_copies <= 0) {
                abort(response()->json([
                    'message' => 'No available copies for this book.',
                ], 422));
            }

            $book->decrement('available_copies');

            $borrowing = Borrowing::create([
                'member_id' => $reservation->member_id,
                'book_id' => $reservation->book_id,
                'borrowed_at' => now()->toDateString(),
                'due_date' => now()->addDays(14)->toDateString(),
                'status' => 'borrowed',
                'notes' => 'Created from reservation #' . $reservation->id,
            ]);

            $reservation->update([
                'status' => 'fulfilled',
            ]);

            return $borrowing;
        });

        $borrowing->load(['member', 'book']);

        return response()->json([
            'message' => 'Reservation fulfilled and book borrowed successfully',
            'data' => new BorrowingResource($borrowing),
        ], 201);
    }
}
