<?php

namespace App\Policies;

use App\Models\Fine;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class FinePolicy
{
    public function viewOwn(User $user, Fine $fine): Response
    {
        if (!$user->hasRole('member')) {
            return Response::deny('Only members can view their own fines.');
        }

        if (!$user->member) {
            return Response::deny('No member profile linked to this user.');
        }

        return $fine->member_id === $user->member->id
            ? Response::allow()
            : Response::deny('You can only view your own fines.');
    }
}
