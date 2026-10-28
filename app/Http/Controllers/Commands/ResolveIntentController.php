<?php

namespace App\Http\Controllers\Commands;

use App\Actions\Commands\ResolveIntent;
use App\DTO\Commands\CommittedData;
use App\Http\Commands\ReceivedAt;
use App\Http\Requests\Commands\ResolveIntentRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use LogicException;

/**
 * RC-50, `POST /commands/resolve` (ROUTE_CONTRACTS section 6; CAP-34): the
 * status check of an uncertain intent, answered 200 with COMMITTED,
 * IN_PROGRESS or NOT_COMMITTED, or refused with the error body of RT-07
 * (EPOCH_STALE, NOT_FOUND, SERVICE_BUSY), which the exception handler gives
 * a CommandRefused.
 */
final class ResolveIntentController
{
    public function __invoke(ResolveIntentRequest $request, ResolveIntent $resolve): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new LogicException('RC-50 runs only for a signed-in user (ROUTE_CONTRACTS RT-04).');
        }

        $intent = $request->intent();
        $resolution = $resolve($user, $intent['operation'], $intent['sourceIntentId'], $intent['recoveryEpoch'], ReceivedAt::of($request));

        return new JsonResponse(CommittedData::resolution($resolution), 200);
    }
}
