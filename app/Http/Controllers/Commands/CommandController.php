<?php

namespace App\Http\Controllers\Commands;

use App\Actions\Commands\CommandAction;
use App\Http\Commands\CommandPipeline;
use App\Http\Requests\Commands\CommandRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The base of every command's invokable controller (ROUTE_CONTRACTS RT-03,
 * section 6; TASK-008): it names the command's FormRequest and Action and
 * hands the request to the pipeline, which writes the request receipt before
 * it resolves the FormRequest (INTERACTION_CONTRACTS section 1.1). It holds
 * no business rule. Each command's Task adds `Http/Controllers/<Module>/
 * <Operation>Controller` extending it.
 */
abstract class CommandController
{
    /**
     * @return class-string<CommandRequest>
     */
    abstract protected function request(): string;

    /**
     * @return class-string<CommandAction>
     */
    abstract protected function action(): string;

    public function __invoke(Request $request, CommandPipeline $pipeline): JsonResponse
    {
        return $pipeline->run($request, $this->request(), $this->action());
    }
}
