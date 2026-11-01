<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\PreviewCsvImport;
use App\Http\Requests\Catalog\ImportPreviewRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use LogicException;

/**
 * RR-01 (ROUTE_CONTRACTS section 5; TASK-019 scope item 2). A refusal is the error body
 * of RT-07, by the handler of CommandRefused.
 */
final class ImportPreviewController
{
    public function __invoke(ImportPreviewRequest $request, PreviewCsvImport $preview): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new LogicException('RR-01 runs only for a signed-in user (ROUTE_CONTRACTS RT-04).');
        }

        return new JsonResponse($preview($user->id, $request->upload()));
    }
}
