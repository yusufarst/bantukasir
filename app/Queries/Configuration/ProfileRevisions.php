<?php

namespace App\Queries\Configuration;

use App\Domain\Configuration\Accents;
use App\Domain\Configuration\BusinessProfiles;
use App\Domain\Configuration\ProfileRevision;
use App\Domain\Media\StoredFiles;
use App\DTO\Configuration\RevisionData;

/**
 * The BusinessProfile revisions of UXP-607 and UXP-608 (ROUTE_CONTRACTS
 * RP-607, RP-608; BR-CFG-02): the revision in force and, for UXP-607, the
 * earlier ones, the newest first, at most EARLIER of them (TECHNICAL_RULES
 * section 12: no unbounded list). A revision is never deleted, so every one
 * stays readable.
 */
final class ProfileRevisions
{
    /** The earlier revisions shown at most. */
    public const int EARLIER = 50;

    public function __construct(private readonly BusinessProfiles $profiles) {}

    public function current(): ?RevisionData
    {
        $revision = $this->profiles->current();

        return $revision === null ? null : self::data($revision);
    }

    /**
     * @return list<RevisionData>
     */
    public function earlier(): array
    {
        return array_map(self::data(...), $this->profiles->earlier(self::EARLIER));
    }

    private static function data(ProfileRevision $revision): RevisionData
    {
        $fields = $revision->fields;

        return new RevisionData(
            id: $revision->id,
            number: $revision->number,
            shopName: $fields->shopName,
            address: $fields->address,
            contact: $fields->contact,
            email: $fields->email,
            documentHeader: $fields->documentHeader,
            documentFooter: $fields->documentFooter,
            documentIdentity: $fields->documentIdentity,
            accentToken: Accents::rendered($fields->accentToken),
            logo: $revision->logoFileId === null ? null : StoredFiles::deliveryPath($revision->logoFileId),
            createdAt: $revision->createdAt,
            actorDisplayName: $revision->actorDisplayName,
        );
    }
}
