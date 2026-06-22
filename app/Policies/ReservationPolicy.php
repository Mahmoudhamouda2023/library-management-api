<?php

namespace App\Policies;

use App\Models\Reservation;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ReservationPolicy
{
    public function viewOwn(User $user, Reservation $reservation): Response
    {
        if (!$user->hasRole('member')) {
            return Response::deny('Only members can view their own reservations.');
        }

        if (!$user->member) {
            return Response::deny('No member profile linked to this user.');
        }

        return $reservation->member_id === $user->member->id
            ? Response::allow()
            : Response::deny('You can only view your own reservations.');
    }

    public function cancelOwn(User $user, Reservation $reservation): Response
    {
        if (!$user->hasRole('member')) {
            return Response::deny('Only members can cancel their own reservations.');
        }

        if (!$user->member) {
            return Response::deny('No member profile linked to this user.');
        }

        return $reservation->member_id === $user->member->id
            ? Response::allow()
            : Response::deny('You can only cancel your own reservations.');
    }
}
