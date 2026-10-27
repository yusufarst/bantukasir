<?php

namespace App\DTO\Access;

use App\DTO\PageData;

/**
 * The shared props of a signed-in page (SECURITY_MODEL SEC-CH-02; TASK-007):
 * exactly the signed-in user's internal identifier, display name, roles and
 * capability flags, and the limits of the sign-in session that the shell's
 * timers mirror (SEC-SESS-02) — the idle limit and the seconds left until it,
 * and the absolute end, as its instant and the seconds left until it, counted
 * when the answer was made, so that the browser's clock never decides. No
 * business data, no other user's data, no cost. The recovery epoch joins them
 * with the Task that creates it (ADR-007; TASK-008, TASK-010); the flash
 * message codes are the pages' own props; the translation dictionary is the
 * once prop of ADR-005.
 */
final readonly class SignedInData implements PageData
{
    /**
     * @param  list<'SUPER_ADMIN'|'OPERATIONS_ADMIN'|'CASHIER'>  $roles  in the order of COPY_DECK section 3
     * @param  list<string>  $capabilities  the CAP identifiers that open their gates, in the order of section 6
     */
    public function __construct(
        public int $id,
        public string $displayName,
        public array $roles,
        public array $capabilities,
        public int $idleLimit,
        public int $idleEndsIn,
        public string $absoluteEndsAt,
        public int $absoluteEndsIn,
    ) {}

    /**
     * @return array{user: array{id: int, displayName: string, roles: list<'SUPER_ADMIN'|'OPERATIONS_ADMIN'|'CASHIER'>, capabilities: list<string>}, session: array{idleLimit: int, idleEndsIn: int, absoluteEndsAt: string, absoluteEndsIn: int}}
     */
    public function toArray(): array
    {
        return [
            'user' => [
                'id' => $this->id,
                'displayName' => $this->displayName,
                'roles' => $this->roles,
                'capabilities' => $this->capabilities,
            ],
            'session' => [
                'idleLimit' => $this->idleLimit,
                'idleEndsIn' => $this->idleEndsIn,
                'absoluteEndsAt' => $this->absoluteEndsAt,
                'absoluteEndsIn' => $this->absoluteEndsIn,
            ],
        ];
    }
}
