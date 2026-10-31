<?php

namespace Tests\Harness\Backup;

use App\Domain\Operations\Backup\BackupFailed;
use App\Domain\Operations\Backup\BackupFailure;
use App\Domain\Operations\Backup\BackupStore;
use App\Domain\Operations\Backup\StoredObject;
use Carbon\CarbonInterface;

/**
 * The store between a run and the test server: it records each upload in order,
 * and refuses the first upload whose key holds a given text, for the failure
 * injection after a step (TS-21).
 */
final class RecordingStore implements BackupStore
{
    /** @var list<string> */
    public array $puts = [];

    public function __construct(
        private readonly BackupStore $store,
        private readonly ?string $refuse = null,
    ) {}

    /**
     * @param  array<string, string>  $metadata
     */
    public function put(string $key, string $path, array $metadata = [], ?CarbonInterface $retainUntil = null): void
    {
        if ($this->refuse !== null && str_contains($key, $this->refuse)) {
            throw new BackupFailed(BackupFailure::STORE_REFUSED, 'The harness refused the upload.');
        }

        $this->store->put($key, $path, $metadata, $retainUntil);
        $this->puts[] = $key;
    }

    public function putText(string $key, string $text): void
    {
        $this->store->putText($key, $text);
    }

    public function getText(string $key): ?string
    {
        return $this->store->getText($key);
    }

    public function head(string $key): ?StoredObject
    {
        return $this->store->head($key);
    }

    /**
     * @return array<string, true>
     */
    public function keys(string $prefix): array
    {
        return $this->store->keys($prefix);
    }

    public function usageBytes(): int
    {
        return $this->store->usageBytes();
    }

    public function download(string $key, string $path): bool
    {
        return $this->store->download($key, $path);
    }
}
