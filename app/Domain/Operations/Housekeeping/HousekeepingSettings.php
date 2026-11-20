<?php

namespace App\Domain\Operations\Housekeeping;

use App\Domain\Media\MediaStore;
use InvalidArgumentException;

/**
 * The bounds of SC-02 `housekeeping:run`, from config/housekeeping.php only (TR-RUN-04, THR-10): no
 * option, argument or stored value reaches the run. A value outside its range refuses the run before it
 * removes anything; a grace period below one hour never applies (TR-TX-05).
 */
final readonly class HousekeepingSettings
{
    public const int MAX_BATCH_SIZE = 1000;

    public const int MAX_BATCHES = 100;

    public const int MAX_FILE_GRACE_SECONDS = 86400;

    private function __construct(
        public int $batchSize,
        public int $maxBatches,
        public int $fileGraceSeconds,
    ) {}

    /**
     * @throws InvalidArgumentException
     */
    public static function fromConfig(): self
    {
        return new self(
            self::bounded('batch_size', 1, self::MAX_BATCH_SIZE),
            self::bounded('max_batches', 1, self::MAX_BATCHES),
            self::bounded('file_grace_seconds', MediaStore::MIN_GRACE_SECONDS, self::MAX_FILE_GRACE_SECONDS),
        );
    }

    /** The most of one kind that one run removes. */
    public function limit(): int
    {
        return $this->batchSize * $this->maxBatches;
    }

    private static function bounded(string $name, int $minimum, int $maximum): int
    {
        $value = config('housekeeping.'.$name);

        if (! is_int($value) || $value < $minimum || $value > $maximum) {
            throw new InvalidArgumentException(sprintf('The setting housekeeping.%s is not a whole number from %d to %d.', $name, $minimum, $maximum));
        }

        return $value;
    }
}
