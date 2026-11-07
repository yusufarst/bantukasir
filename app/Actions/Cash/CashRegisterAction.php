<?php

namespace App\Actions\Cash;

use App\Actions\Commands\CommandAction;
use App\Actions\Commands\CommandServices;
use App\Database\SqlState;
use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Domain\Audit\Actor;
use App\Domain\Cash\Registers;
use App\Domain\Cash\Sessions;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use App\Domain\Commands\StoredResult;
use App\Models\User;
use Closure;
use LogicException;
use Throwable;

/**
 * The base of RC-33 `cash_register.create` and RC-34 `cash_register.update` (INTERACTION_CONTRACTS
 * RC-33 and RC-34, section 1.5; TECHNICAL_RULES section 7.2, "Cash register configuration";
 * BR-CASH-02; G-19; CAP-31; TASK-027 scope item 2): the Owner, with the recent authentication of
 * the route; the code 1 to 32 and unique without regard to letter case, which the database alone
 * decides (SCH-14), the label 1 to 100, the active flag. The result leads to RP-609.
 */
abstract class CashRegisterAction extends CommandAction
{
    /** The result's type (SCHEMA section 5.3) and the page of the registers (ROUTE_CONTRACTS RP-609). */
    public const string RESULT_TYPE = 'cash_register';

    public const string REGISTERS_PAGE = '/settings/cash-registers';

    public function __construct(
        CommandServices $services,
        protected readonly Registers $registers,
        protected readonly Sessions $sessions,
    ) {
        parent::__construct($services);
    }

    public function capabilities(): array
    {
        return [Capability::Cap31];
    }

    public function present(User $viewer, int $commandId, StoredResult $result): ?array
    {
        if (! Capabilities::allows($viewer, Capability::Cap31)) {
            return null;
        }

        return ['type' => $result->type, 'id' => $result->ref, 'location' => self::REGISTERS_PAGE];
    }

    /**
     * Runs a write of the code, mapping the unique index's refusal to the field (SCH-14; COPY_DECK
     * section 16.1).
     *
     * @template T
     *
     * @param  Closure(): T  $write
     * @return T
     *
     * @throws CommandRefused VALIDATION_FAILED at `payload.code`
     */
    protected static function withUniqueCode(Closure $write): mixed
    {
        try {
            return $write();
        } catch (Throwable $failure) {
            throw SqlState::uniqueOn($failure, Registers::CODE_INDEX)
                ? new CommandRefused(ErrorCode::ValidationFailed, fields: ['payload.code' => [['key' => 'ux.validation.register_code_taken', 'params' => []]]])
                : $failure;
        }
    }

    protected static function actorOf(User $actor): Actor
    {
        return Actor::user($actor->id, $actor->display_name);
    }

    protected static function text(mixed $value): string
    {
        return is_string($value) ? $value : throw new LogicException('A text of the payload was validated before the Action.');
    }

    protected static function flag(mixed $value): bool
    {
        return is_bool($value) ? $value : throw new LogicException('A flag of the payload was validated before the Action.');
    }

    protected static function whole(mixed $value): int
    {
        return is_int($value) ? $value : throw new LogicException('A whole number of the payload was validated before the Action.');
    }
}
