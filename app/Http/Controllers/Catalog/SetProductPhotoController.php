<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\SetProductPhoto;
use App\Http\Controllers\Commands\CommandController;
use App\Http\Requests\Catalog\SetProductPhotoRequest;

/**
 * RC-25 `product_photo.set` (ROUTE_CONTRACTS section 6; TASK-018 scope item 1).
 */
final class SetProductPhotoController extends CommandController
{
    protected function request(): string
    {
        return SetProductPhotoRequest::class;
    }

    protected function action(): string
    {
        return SetProductPhoto::class;
    }
}
