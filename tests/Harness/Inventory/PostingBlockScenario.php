<?php

namespace Tests\Harness\Inventory;

use App\Database\CommandPoints;
use App\Domain\Audit\Actor;
use App\Domain\Catalog\Products;
use App\Domain\Commands\CommandIdentities;
use App\Domain\Commands\Envelope;
use App\Domain\Commands\Fingerprint;
use App\Domain\Commands\Operations;
use App\Domain\Commands\StoredResult;
use App\Domain\Inventory\PostingBlocks;
use App\Support\Time\Clock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Harness\Race\Scenario;

/**
 * A posting block written as the scheduled SC-01 will write it (INTERACTION_CONTRACTS section
 * 7; RACE-24; TASK-021 scope item 7; EXECUTION_CONTEXT EXC-45): under the product guard,
 * through the Inventory module's own block service, with an identity of the process
 * `reconciliation`. TASK-036 builds that command; until then the fixture and the race workers
 * use this.
 */
final class PostingBlockScenario implements Scenario
{
    /**
     * @param  array<string, mixed>  $arguments  product, epoch
     * @return array<string, mixed>
     */
    public function run(array $arguments): array
    {
        $product = $arguments['product'] ?? null;
        $epoch = $arguments['epoch'] ?? null;

        return ['block' => self::write(is_int($product) ? $product : 0, 'BALANCE_MISMATCH', is_string($epoch) ? $epoch : '')];
    }

    public static function write(int $product, string $detailCode, string $epoch): int
    {
        return DB::transaction(static function () use ($product, $detailCode, $epoch): int {
            $identities = app(CommandIdentities::class);
            $points = app(CommandPoints::class);
            $envelope = new Envelope('posting_block.block', Operations::VERSION, (string) Str::uuid(), (string) Str::uuid(), $epoch, [], ['productId' => $product]);
            $fingerprint = Fingerprint::of($envelope->operation, $envelope->version, 'process:reconciliation', [], $envelope->payload);
            $command = $identities->insertProcessCommand($envelope, 'SCHEDULED_COMMAND', 'reconciliation', $fingerprint, app(Clock::class)->now()->format('Y-m-d H:i:s.uP'));

            app(Products::class)->lockForPosting([$product]);
            $points->afterLock('6');

            $block = app(PostingBlocks::class)->block($product, Actor::scheduledCommand('reconciliation'), $detailCode, $command);
            $points->afterWrite(1);

            $identities->recordResult($command, new StoredResult('posting_block', $block));

            return $block;
        });
    }
}
