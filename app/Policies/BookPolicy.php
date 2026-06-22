<?php

namespace App\Policies;

use App\Models\Book;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BookPolicy
{
    public function viewOwn(User $user, Book $book): Response
    {
        return $this->publisherOwnsBook($user, $book, 'view');
    }

    public function updateOwn(User $user, Book $book): Response
    {
        return $this->publisherOwnsBook($user, $book, 'update');
    }

    public function deleteOwn(User $user, Book $book): Response
    {
        return $this->publisherOwnsBook($user, $book, 'delete');
    }

    private function publisherOwnsBook(User $user, Book $book, string $action): Response
    {
        if (!$user->hasRole('publisher')) {
            return Response::deny("Only publishers can {$action} their own books.");
        }

        if (!$user->author) {
            return Response::deny('No author profile linked to this user.');
        }

        $ownsByUser = !empty($book->user_id) && (int) $book->user_id === (int) $user->id;
        $ownsByAuthor = (int) $book->author_id === (int) $user->author->id;

        return ($ownsByUser || $ownsByAuthor)
            ? Response::allow()
            : Response::deny("You can only {$action} your own books.");
    }
}
