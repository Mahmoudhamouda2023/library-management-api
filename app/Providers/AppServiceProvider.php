<?php

namespace App\Providers;

use App\Models\Book;
use App\Models\Borrowing;
use App\Models\Fine;
use App\Models\Reservation;
use App\Policies\BookPolicy;
use App\Policies\BorrowingPolicy;
use App\Policies\FinePolicy;
use App\Policies\ReservationPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Book::class, BookPolicy::class);
        Gate::policy(Borrowing::class, BorrowingPolicy::class);
        Gate::policy(Reservation::class, ReservationPolicy::class);
        Gate::policy(Fine::class, FinePolicy::class);
    }
}
