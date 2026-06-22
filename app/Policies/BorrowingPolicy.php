<?php

namespace App\Policies;

use App\Models\Borrowing;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BorrowingPolicy
{
    public function viewOwn(User $user, Borrowing $borrowing): Response
    {
        if (!$user->hasRole('member')) {
            return Response::deny('Only members can view their own borrowings.');
        }

        if (!$user->member) {
            return Response::deny('No member profile linked to this user.');
        }

        return $borrowing->member_id === $user->member->id
            ? Response::allow()
            : Response::deny('You can only view your own borrowings.');
    }

    public function returnOwn(User $user, Borrowing $borrowing): Response
    {
        if (!$user->hasRole('member')) {
            return Response::deny('Only members can return their own borrowed books.');
        }

        if (!$user->member) {
            return Response::deny('No member profile linked to this user.');
        }

        return $borrowing->member_id === $user->member->id
            ? Response::allow()
            : Response::deny('You can only return your own borrowed books.');
    }
}
