<?php

namespace Tests\Harness\Backup;

use App\Domain\Operations\Backup\BackupSettings;
use App\Domain\Operations\Backup\BackupStore;
use App\Domain\Operations\Backup\Deadline;
use App\Domain\Operations\Backup\ObjectStoreFactory;

/**
 * Opens a RecordingStore for the run under test.
 */
final class RecordingStoreFactory extends ObjectStoreFactory
{
    public ?RecordingStore $store = null;

    public function __construct(private readonly ?string $refuse = null)
    {
        parent::__construct();
    }

    public function make(BackupSettings $settings, Deadline $deadline): BackupStore
    {
        return $this->store = new RecordingStore(parent::make($settings, $deadline), $this->refuse);
    }
}
