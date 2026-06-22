<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\AuthorController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\BookController;
use App\Http\Controllers\Api\V1\MemberController;
use App\Http\Controllers\Api\V1\BorrowingController;
use App\Http\Controllers\Api\V1\ReservationController;
use App\Http\Controllers\Api\V1\FineController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\MemberPortalController;
use App\Http\Controllers\Api\V1\PublisherRequestController;
use App\Http\Controllers\Api\V1\PublisherPortalController;

Route::get('/test', function () {
    return response()->json([
        'message' => 'Library API is working',
        'developer' => 'Mahmoud',
        'status' => true,
    ]);
});

Route::prefix('v1')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Public Auth Routes
    |--------------------------------------------------------------------------
    */

    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    /*
    |--------------------------------------------------------------------------
    | Public Website Routes
    |--------------------------------------------------------------------------
    */

    Route::get('/public/books', [BookController::class, 'index']);
    Route::get('/public/books/{book}', [BookController::class, 'show']);
    Route::get('/public/categories', [CategoryController::class, 'index']);
    Route::get('/public/authors', [AuthorController::class, 'index']);

    /*
    |--------------------------------------------------------------------------
    | Protected Routes
    |--------------------------------------------------------------------------
    */

    Route::middleware('auth:sanctum')->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Authenticated User
        |--------------------------------------------------------------------------
        */

        Route::get('/profile', [AuthController::class, 'profile']);
        Route::post('/profile', [AuthController::class, 'updateProfile']);
        Route::post('/logout', [AuthController::class, 'logout']);

        /*
        |--------------------------------------------------------------------------
        | Books Browsing
        |--------------------------------------------------------------------------
        */

        Route::get('/books', [BookController::class, 'index'])
            ->middleware('permission:view books');

        Route::get('/books/{book}', [BookController::class, 'show'])
            ->middleware('permission:view books');

        /*
        |--------------------------------------------------------------------------
        | Admin / Librarian: Authors Management
        |--------------------------------------------------------------------------
        */

        Route::middleware('permission:manage authors')->group(function () {
            Route::apiResource('authors', AuthorController::class);
        });

        /*
        |--------------------------------------------------------------------------
        | Admin / Librarian: Categories Management
        |--------------------------------------------------------------------------
        */

        Route::middleware('permission:manage categories')->group(function () {
            Route::apiResource('categories', CategoryController::class);
        });

        /*
        |--------------------------------------------------------------------------
        | Admin / Librarian: Books Management
        |--------------------------------------------------------------------------
        */

        Route::middleware('permission:manage books')->group(function () {
            Route::post('/books', [BookController::class, 'store']);
            Route::put('/books/{book}', [BookController::class, 'update']);
            Route::patch('/books/{book}', [BookController::class, 'update']);
            Route::delete('/books/{book}', [BookController::class, 'destroy']);
        });

        /*
        |--------------------------------------------------------------------------
        | Admin Only: Publisher Books Review
        |--------------------------------------------------------------------------
        */

        Route::middleware('role:admin')->group(function () {
            Route::get('/admin/books/pending', [BookController::class, 'pendingBooks']);
            Route::post('/admin/books/{book}/approve', [BookController::class, 'approve']);
            Route::post('/admin/books/{book}/reject', [BookController::class, 'reject']);
        });

        /*
        |--------------------------------------------------------------------------
        | Admin / Librarian: Members Management
        |--------------------------------------------------------------------------
        */

        Route::middleware('permission:manage members')->group(function () {
            Route::apiResource('members', MemberController::class);
        });

        /*
        |--------------------------------------------------------------------------
        | Admin / Librarian: Borrowings Management
        |--------------------------------------------------------------------------
        */

        Route::middleware('permission:manage borrowings')->group(function () {
            Route::get('/borrowings', [BorrowingController::class, 'index']);
            Route::post('/borrowings', [BorrowingController::class, 'store']);

            // Important: this route must be before /borrowings/{borrowing}
            Route::get('/borrowings/overdue/list', [BorrowingController::class, 'overdue']);

            Route::get('/borrowings/{borrowing}', [BorrowingController::class, 'show']);
            Route::post('/borrowings/{borrowing}/return', [BorrowingController::class, 'returnBook']);
        });

        /*
        |--------------------------------------------------------------------------
        | Admin / Librarian: Reservations Management
        |--------------------------------------------------------------------------
        */

        Route::middleware('permission:manage reservations')->group(function () {
            Route::get('/reservations', [ReservationController::class, 'index']);
            Route::get('/reservations/{reservation}', [ReservationController::class, 'show']);
            Route::post('/reservations/{reservation}/cancel', [ReservationController::class, 'cancel']);
            Route::post('/reservations/{reservation}/fulfill', [ReservationController::class, 'fulfill']);
        });

        /*
        |--------------------------------------------------------------------------
        | Admin / Librarian: Fines Management
        |--------------------------------------------------------------------------
        */

        Route::middleware('permission:manage fines')->group(function () {
            Route::get('/fines', [FineController::class, 'index']);
            Route::get('/fines/{fine}', [FineController::class, 'show']);
            Route::post('/fines/{fine}/pay', [FineController::class, 'pay']);
            Route::post('/fines/{fine}/waive', [FineController::class, 'waive']);
        });

        /*
        |--------------------------------------------------------------------------
        | Admin / Librarian: Reports
        |--------------------------------------------------------------------------
        */

        Route::middleware('permission:view reports')
            ->prefix('reports')
            ->group(function () {
                Route::get('/summary', [ReportController::class, 'summary']);
                Route::get('/books', [ReportController::class, 'books']);
                Route::get('/borrowings', [ReportController::class, 'borrowings']);
                Route::get('/members', [ReportController::class, 'members']);
                Route::get('/reservations', [ReportController::class, 'reservations']);
            });

        /*
        |--------------------------------------------------------------------------
        | Publisher Requests For Members
        |--------------------------------------------------------------------------
        */

        Route::middleware('permission:request publisher account')->group(function () {
            Route::get('/publisher-request', [PublisherRequestController::class, 'myRequest'])
                ->middleware('permission:view own publisher request');

            Route::post('/publisher-request', [PublisherRequestController::class, 'store']);
        });

        /*
        |--------------------------------------------------------------------------
        | Admin Only: Publisher Requests Management
        |--------------------------------------------------------------------------
        */

        Route::middleware(['role:admin', 'permission:manage publisher requests'])->group(function () {
            Route::get('/publisher-requests', [PublisherRequestController::class, 'index']);
            Route::get('/publisher-requests/{publisherRequest}', [PublisherRequestController::class, 'show']);
            Route::post('/publisher-requests/{publisherRequest}/approve', [PublisherRequestController::class, 'approve']);
            Route::post('/publisher-requests/{publisherRequest}/reject', [PublisherRequestController::class, 'reject']);
        });

        /*
        |--------------------------------------------------------------------------
        | Publisher Portal
        |--------------------------------------------------------------------------
        */

        Route::middleware(['role:publisher', 'permission:manage own books'])
            ->prefix('publisher')
            ->group(function () {
                Route::get('/dashboard', [PublisherPortalController::class, 'dashboard']);

                Route::get('/books', [PublisherPortalController::class, 'myBooks']);
                Route::post('/books', [PublisherPortalController::class, 'storeBook']);
                Route::get('/books/{book}', [PublisherPortalController::class, 'showBook']);
                Route::put('/books/{book}', [PublisherPortalController::class, 'updateBook']);
                Route::patch('/books/{book}', [PublisherPortalController::class, 'updateBook']);
                Route::delete('/books/{book}', [PublisherPortalController::class, 'deleteBook']);
            });

        /*
        |--------------------------------------------------------------------------
        | Member Portal
        |--------------------------------------------------------------------------
        */

        Route::middleware('role:member')
            ->prefix('my')
            ->group(function () {

                Route::get('/borrowings', [MemberPortalController::class, 'myBorrowings'])
                    ->middleware('permission:view own borrowings');

                Route::post('/books/{book}/borrow', [MemberPortalController::class, 'borrowBook'])
                    ->middleware('permission:borrow books');

                Route::post('/borrowings/{borrowing}/return', [MemberPortalController::class, 'returnMyBook'])
                    ->middleware('permission:return own books');

                Route::get('/reservations', [MemberPortalController::class, 'myReservations'])
                    ->middleware('permission:reserve books');

                Route::post('/books/{book}/reserve', [MemberPortalController::class, 'reserveBook'])
                    ->middleware('permission:reserve books');

                Route::post('/reservations/{reservation}/cancel', [MemberPortalController::class, 'cancelMyReservation'])
                    ->middleware('permission:cancel own reservations');

                Route::get('/fines', [MemberPortalController::class, 'myFines'])
                    ->middleware('permission:view own fines');
            });
    });
});
