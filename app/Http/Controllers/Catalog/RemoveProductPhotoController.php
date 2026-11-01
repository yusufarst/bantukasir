<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\RemoveProductPhoto;
use App\Http\Controllers\Commands\CommandController;
use App\Http\Requests\Catalog\RemoveProductPhotoRequest;

/**
 * RC-26 `product_photo.remove` (ROUTE_CONTRACTS section 6; TASK-018 scope item 1).
 */
final class RemoveProductPhotoController extends CommandController
{
    protected function request(): string
    {
        return RemoveProductPhotoRequest::class;
    }

    protected function action(): string
    {
        return RemoveProductPhoto::class;
    }
}
