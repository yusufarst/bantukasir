<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\CancelCsvImport;
use App\Models\User;
use Illuminate\Http\Request;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

/**
 * RR-02 (ROUTE_CONTRACTS section 5; TASK-019 scope item 3): `204`, also for a missing or
 * expired import.
 */
final class ImportCancelController
{
    public function __invoke(Request $request, string $import, CancelCsvImport $cancel): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new LogicException('RR-02 runs only for a signed-in user (ROUTE_CONTRACTS RT-04).');
        }

        $cancel($user->id, $import);

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
