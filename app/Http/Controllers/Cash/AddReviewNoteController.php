<?php

namespace App\Http\Controllers\Cash;

use App\Actions\Cash\AddReviewNote;
use App\Http\Controllers\Commands\CommandController;
use App\Http\Requests\Cash\AddReviewNoteRequest;

/**
 * RC-43 `review_note.add` (ROUTE_CONTRACTS section 6; TASK-032 scope item 4).
 */
final class AddReviewNoteController extends CommandController
{
    protected function request(): string
    {
        return AddReviewNoteRequest::class;
    }

    protected function action(): string
    {
        return AddReviewNote::class;
    }
}
