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
use App\Models\PublisherRequest;
use App\Models\Reservation;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function summary()
    {
        $totalCopies = (int) Book::sum('total_copies');
        $availableCopies = (int) Book::sum('available_copies');
        $borrowedCopies = max($totalCopies - $availableCopies, 0);

        $totalBooks = Book::count();
        $totalMembers = Member::count();
        $activeMembers = Member::where('status', 'active')->count();
        $currentlyBorrowed = Borrowing::where('status', 'borrowed')->count();
        $pendingReservations = Reservation::where('status', 'pending')->count();
        $pendingPublisherBooks = Book::where('review_status', 'pending')->count();
        $pendingPublisherRequests = PublisherRequest::where('status', 'pending')->count();

        return response()->json([
            'data' => [
                // Flat keys used by the React dashboard cards.
                'total_books' => $totalBooks,
                'books_count' => $totalBooks,
                'new_books_this_week' => Book::where('created_at', '>=', now()->subWeek())->count(),
                'total_members' => $totalMembers,
                'members_count' => $totalMembers,
                'active_members' => $activeMembers,
                'new_members_this_week' => Member::where('created_at', '>=', now()->subWeek())->count(),
                'active_borrowings' => $currentlyBorrowed,
                'borrowed_books' => $currentlyBorrowed,
                'due_soon_borrowings' => Borrowing::where('status', 'borrowed')
                    ->whereDate('due_date', '>=', now()->toDateString())
                    ->whereDate('due_date', '<=', now()->addDays(3)->toDateString())
                    ->count(),
                'pending_reservations' => $pendingReservations,
                'pending_publisher_books' => $pendingPublisherBooks,
                'pending_publisher_requests' => $pendingPublisherRequests,
                'pending_requests_total' => $pendingReservations + $pendingPublisherBooks + $pendingPublisherRequests,

                // Dynamic dashboard widgets.
                'borrowing_trend' => $this->borrowingTrend(),
                'category_distribution' => $this->categoryDistribution($totalBooks),
                'recent_borrowings' => $this->recentBorrowings(),
                'publisher_requests' => $this->pendingPublisherRequests(),
                'pending_books' => $this->pendingBooks(),

                // Old nested structure kept for reports page compatibility.
                'authors_count' => Author::count(),
                'categories_count' => Category::count(),

                'books' => [
                    'total_books' => $totalBooks,
                    'active_books' => Book::where('status', 'active')->count(),
                    'inactive_books' => Book::where('status', 'inactive')->count(),
                    'total_copies' => $totalCopies,
                    'available_copies' => $availableCopies,
                    'borrowed_copies' => $borrowedCopies,
                    'pending_review' => $pendingPublisherBooks,
                ],

                'members' => [
                    'total_members' => $totalMembers,
                    'active_members' => $activeMembers,
                    'inactive_members' => Member::where('status', 'inactive')->count(),
                    'suspended_members' => Member::where('status', 'suspended')->count(),
                ],

                'borrowings' => [
                    'total_borrowings' => Borrowing::count(),
                    'currently_borrowed' => $currentlyBorrowed,
                    'returned' => Borrowing::where('status', 'returned')->count(),
                    'overdue' => Borrowing::where('status', 'borrowed')
                        ->whereDate('due_date', '<', now()->toDateString())
                        ->count(),
                ],

                'reservations' => [
                    'total_reservations' => Reservation::count(),
                    'pending' => $pendingReservations,
                    'cancelled' => Reservation::where('status', 'cancelled')->count(),
                    'fulfilled' => Reservation::where('status', 'fulfilled')->count(),
                    'expired' => Reservation::where('status', 'expired')->count(),
                ],
            ],
        ]);
    }

    private function borrowingTrend(): array
    {
        $months = [];

        for ($i = 7; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i)->startOfMonth();

            $months[] = [
                'label' => $month->format('M'),
                'month' => $month->format('Y-m'),
                'count' => Borrowing::whereBetween('borrowed_at', [
                    $month->copy()->startOfMonth()->toDateString(),
                    $month->copy()->endOfMonth()->toDateString(),
                ])->count(),
            ];
        }

        return $months;
    }

    private function categoryDistribution(int $totalBooks): array
    {
        return Category::query()
            ->withCount('books')
            ->orderByDesc('books_count')
            ->take(5)
            ->get()
            ->map(function ($category) use ($totalBooks) {
                $count = (int) $category->books_count;

                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'count' => $count,
                    'percentage' => $totalBooks > 0 ? round(($count / $totalBooks) * 100) : 0,
                ];
            })
            ->values()
            ->all();
    }

    private function recentBorrowings(): array
    {
        return Borrowing::query()
            ->with(['member', 'book'])
            ->latest()
            ->take(5)
            ->get()
            ->map(fn($borrowing) => [
                'id' => $borrowing->id,
                'member_name' => $borrowing->member?->name ?? 'Unknown member',
                'book_title' => $borrowing->book?->title ?? 'Unknown book',
                'due_date' => $borrowing->due_date,
                'status' => $borrowing->status,
                'is_overdue' => $borrowing->status === 'borrowed' && $borrowing->due_date < now()->toDateString(),
            ])
            ->values()
            ->all();
    }

    private function pendingPublisherRequests(): array
    {
        return PublisherRequest::query()
            ->with('user')
            ->where('status', 'pending')
            ->latest()
            ->take(5)
            ->get()
            ->map(fn($request) => [
                'id' => $request->id,
                'display_name' => $request->display_name,
                'user_name' => $request->user?->name,
                'status' => $request->status,
                'created_at' => $request->created_at?->format('Y-m-d'),
            ])
            ->values()
            ->all();
    }

    private function pendingBooks(): array
    {
        return Book::query()
            ->with(['author', 'category'])
            ->where('review_status', 'pending')
            ->latest()
            ->take(5)
            ->get()
            ->map(fn($book) => [
                'id' => $book->id,
                'title' => $book->title,
                'author_name' => $book->author?->name,
                'category_name' => $book->category?->name,
                'review_status' => $book->review_status,
                'created_at' => $book->created_at?->format('Y-m-d'),
            ])
            ->values()
            ->all();
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
