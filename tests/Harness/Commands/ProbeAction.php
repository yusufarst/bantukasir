<?php

namespace Tests\Harness\Commands;

use App\Actions\Commands\CommandAction;
use App\Actions\Commands\CommandContext;
use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Domain\Audit\Actor;
use App\Domain\Audit\AuditCategory;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use App\Domain\Commands\StoredResult;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The Action of the probe operation: CAP-04, which the Owner, OPERATIONS_ADMIN
 * and the combined account hold and the cashier does not; the update lock of
 * its counter (step 6); then three write steps — a fact, the counter's
 * increment and its audit event — and the durable result, the fourth.
 * `refuseWith` refuses with that code under the lock, before any write.
 */
final class ProbeAction extends CommandAction
{
    public function operation(): string
    {
        return Probe::OPERATION;
    }

    public function capabilities(): array
    {
        return [Capability::Cap04];
    }

    protected function perform(CommandContext $context, User $actor, int $commandId): StoredResult
    {
        DB::table('harness_probe_counters')->where('id', 1)->lock('for no key update')->value('value');
        $this->services->points->afterLock('6');

        $refuseWith = $context->envelope->payload['refuseWith'] ?? null;

        if (is_string($refuseWith)) {
            $code = ErrorCode::from($refuseWith);

            throw new CommandRefused($code, lines: $code === ErrorCode::InsufficientStock ? [0 => ['available' => '2']] : []);
        }

        $note = $context->envelope->payload['note'] ?? '';
        $factId = DB::table('harness_probe_facts')->insertGetId(['command_id' => $commandId, 'note' => is_string($note) ? $note : '']);
        $this->written();

        DB::update('update harness_probe_counters set value = value + 1 where id = 1');
        $this->written();

        $this->services->audit->record(AuditCategory::Business, 'HARNESS_PROBE', Actor::user($actor->id, $actor->display_name), ['note' => $note], subject: ['type' => 'harness_probe', 'id' => (int) $factId], commandId: $commandId);
        $this->written();

        return new StoredResult('harness_probe', (int) $factId, ['commandId' => $commandId, 'hidden' => ($context->envelope->payload['hideResult'] ?? false) === true ? 1 : 0]);
    }

    /**
     * The result for a viewer who holds CAP-04; `hideResult` stands for a
     * record the viewer may not see, which a real command's object scope
     * decides.
     */
    public function present(User $viewer, int $commandId, StoredResult $result): ?array
    {
        if (! Capabilities::allows($viewer, Capability::Cap04) || ($result->detail['hidden'] ?? 0) === 1) {
            return null;
        }

        return ['type' => $result->type, 'id' => $result->ref, 'location' => '/account'];
    }
}
