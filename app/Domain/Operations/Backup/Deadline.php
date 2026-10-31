<?php

namespace App\Domain\Operations\Backup;

/**
 * The bound of one run: it stops with TIMEOUT after 30 minutes (BACKUP_RECOVERY
 * section 4; ENVIRONMENTS ENV-31). Every program and every request of the run is
 * limited to what remains.
 */
final class Deadline
{
    private function __construct(private readonly int $expiresAt) {}

    public static function after(int $seconds): self
    {
        return new self(time() + $seconds);
    }

    public function expired(): bool
    {
        return time() >= $this->expiresAt;
    }

    /**
     * @throws BackupFailed
     */
    public function check(): void
    {
        if ($this->expired()) {
            throw new BackupFailed(BackupFailure::TIMEOUT, 'The time limit passed.');
        }
    }

    /**
     * The whole seconds that remain, at least one.
     */
    public function remaining(): int
    {
        return max(1, $this->expiresAt - time());
    }
}
