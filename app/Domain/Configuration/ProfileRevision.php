<?php

namespace App\Domain\Configuration;

/**
 * One immutable BusinessProfile revision as it was stored (SCHEMA section
 * 5.2): its fields, its logo by reference, when and by whom it was made, and
 * its number among the revisions, counted from 1 in the order they were made
 * (COPY_DECK section 11.7, "Versi :nomor"). The time is an instant in UTC,
 * as the pages receive every time (YYYY-MM-DDTHH:MM:SS.uuuuuuZ).
 */
final readonly class ProfileRevision
{
    public function __construct(
        public int $id,
        public int $number,
        public ProfileFields $fields,
        public ?int $logoFileId,
        public string $createdAt,
        public string $actorDisplayName,
    ) {}
}
