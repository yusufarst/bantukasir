<?php

namespace App\Domain\Operations\Backup;

use App\Domain\Operations\OutboundHttp;
use GuzzleHttp\ClientInterface;

/**
 * Opens the store of one run. Not final: a test puts a recording or failing store
 * between the run and the test server.
 */
class ObjectStoreFactory
{
    public function __construct(private readonly ?ClientInterface $client = null) {}

    public function make(BackupSettings $settings, Deadline $deadline): BackupStore
    {
        return new ObjectStore($this->client ?? OutboundHttp::client(), $settings, $deadline);
    }
}
