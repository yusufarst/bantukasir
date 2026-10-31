<?php

namespace Tests\Harness\Backup;

use App\Domain\Operations\Backup\BackupFailed;
use App\Domain\Operations\Backup\BackupFailure;
use App\Domain\Operations\Backup\BackupStore;
use App\Domain\Operations\Backup\StoredObject;
use Carbon\CarbonInterface;

/**
 * The store between a run and the test server: it records each upload in order and
 * counts the reads of one object, refuses the first upload whose key holds a given
 * text, for the failure injection after a step (TS-21), and can report the size of
 * such a key one byte short.
 */
final class RecordingStore implements BackupStore
{
    /** @var list<string> */
    public array $puts = [];

    public int $heads = 0;

    public function __construct(
        private readonly BackupStore $store,
        private readonly ?string $refuse = null,
        private readonly ?string $shrink = null,
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
        $this->heads++;

        return $this->store->head($key);
    }

    /**
     * @return array<string, int>
     */
    public function sizes(string $prefix): array
    {
        $sizes = $this->store->sizes($prefix);

        foreach ($sizes as $key => $size) {
            if ($this->shrink !== null && str_contains($key, $this->shrink)) {
                $sizes[$key] = $size - 1;
            }
        }

        return $sizes;
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
