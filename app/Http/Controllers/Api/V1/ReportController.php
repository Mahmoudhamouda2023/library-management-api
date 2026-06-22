<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookResource;
use App\Http\Resources\BorrowingResource;
use App\Http\Resources\ReservationResource;
use App\Models\Author;
use App\Models\Book;
use App\Models\Borrowing;
use App\Models\Category;
use App\Models\Member;
use App\Models\Reservation;

class ReportController extends Controller
{
    public function summary()
    {
        $totalCopies = (int) Book::sum('total_copies');
        $availableCopies = (int) Book::sum('available_copies');
        $borrowedCopies = $totalCopies - $availableCopies;

        return response()->json([
            'data' => [
                'authors_count' => Author::count(),
                'categories_count' => Category::count(),

                'books' => [
                    'total_books' => Book::count(),
                    'active_books' => Book::where('status', 'active')->count(),
                    'inactive_books' => Book::where('status', 'inactive')->count(),
                    'total_copies' => $totalCopies,
                    'available_copies' => $availableCopies,
                    'borrowed_copies' => $borrowedCopies,
                ],

                'members' => [
                    'total_members' => Member::count(),
                    'active_members' => Member::where('status', 'active')->count(),
                    'inactive_members' => Member::where('status', 'inactive')->count(),
                    'suspended_members' => Member::where('status', 'suspended')->count(),
                ],

                'borrowings' => [
                    'total_borrowings' => Borrowing::count(),
                    'currently_borrowed' => Borrowing::where('status', 'borrowed')->count(),
                    'returned' => Borrowing::where('status', 'returned')->count(),
                    'overdue' => Borrowing::where('status', 'borrowed')
                        ->whereDate('due_date', '<', now()->toDateString())
                        ->count(),
                ],

                'reservations' => [
                    'total_reservations' => Reservation::count(),
                    'pending' => Reservation::where('status', 'pending')->count(),
                    'cancelled' => Reservation::where('status', 'cancelled')->count(),
                    'fulfilled' => Reservation::where('status', 'fulfilled')->count(),
                    'expired' => Reservation::where('status', 'expired')->count(),
                ],
            ],
        ]);
    }

    public function books()
    {
        $mostBorrowedBooks = Book::query()
            ->with(['author', 'category'])
            ->withCount('borrowings')
            ->orderByDesc('borrowings_count')
            ->take(10)
            ->get();

        $lowStockBooks = Book::query()
            ->with(['author', 'category'])
            ->where('available_copies', '<=', 1)
            ->orderBy('available_copies')
            ->take(10)
            ->get();

        return response()->json([
            'data' => [
                'most_borrowed_books' => $mostBorrowedBooks->map(function ($book) {
                    return [
                        'id' => $book->id,
                        'title' => $book->title,
                        'isbn' => $book->isbn,
                        'author' => [
                            'id' => $book->author?->id,
                            'name' => $book->author?->name,
                        ],
                        'category' => [
                            'id' => $book->category?->id,
                            'name' => $book->category?->name,
                        ],
                        'total_copies' => $book->total_copies,
                        'available_copies' => $book->available_copies,
                        'borrowings_count' => $book->borrowings_count,
                    ];
                }),

                'low_stock_books' => BookResource::collection($lowStockBooks),
            ],
        ]);
    }

    public function borrowings()
    {
        $latestBorrowings = Borrowing::query()
            ->with(['member', 'book'])
            ->latest()
            ->take(10)
            ->get();

        $overdueBorrowings = Borrowing::query()
            ->with(['member', 'book'])
            ->where('status', 'borrowed')
            ->whereDate('due_date', '<', now()->toDateString())
            ->latest()
            ->take(10)
            ->get();

        $overdueCount = Borrowing::where('status', 'borrowed')
            ->whereDate('due_date', '<', now()->toDateString())
            ->count();

        return response()->json([
            'data' => [
                'total_borrowings' => Borrowing::count(),
                'currently_borrowed' => Borrowing::where('status', 'borrowed')->count(),
                'returned' => Borrowing::where('status', 'returned')->count(),
                'overdue_count' => $overdueCount,

                'latest_borrowings' => BorrowingResource::collection($latestBorrowings),
                'overdue_borrowings' => BorrowingResource::collection($overdueBorrowings),
            ],
        ]);
    }

    public function members()
    {
        $topMembers = Member::query()
            ->withCount('borrowings')
            ->orderByDesc('borrowings_count')
            ->take(10)
            ->get();

        return response()->json([
            'data' => [
                'total_members' => Member::count(),
                'active_members' => Member::where('status', 'active')->count(),
                'inactive_members' => Member::where('status', 'inactive')->count(),
                'suspended_members' => Member::where('status', 'suspended')->count(),

                'top_borrowing_members' => $topMembers->map(function ($member) {
                    return [
                        'id' => $member->id,
                        'membership_number' => $member->membership_number,
                        'name' => $member->name,
                        'email' => $member->email,
                        'status' => $member->status,
                        'borrowings_count' => $member->borrowings_count,
                    ];
                }),
            ],
        ]);
    }

    public function reservations()
    {
        $latestReservations = Reservation::query()
            ->with(['member', 'book'])
            ->latest()
            ->take(10)
            ->get();

        return response()->json([
            'data' => [
                'total_reservations' => Reservation::count(),
                'pending' => Reservation::where('status', 'pending')->count(),
                'cancelled' => Reservation::where('status', 'cancelled')->count(),
                'fulfilled' => Reservation::where('status', 'fulfilled')->count(),
                'expired' => Reservation::where('status', 'expired')->count(),

                'latest_reservations' => ReservationResource::collection($latestReservations),
            ],
        ]);
    }
}
