<?php

namespace App\DTO\Configuration;

use App\DTO\PageData;

/**
 * One BusinessProfile revision as the Owner's pages show it (UXP-607,
 * UXP-608; ROUTE_CONTRACTS RP-607, RP-608; CAP-30): its key — the expected
 * version that RC-32 sends back — its number among the revisions, its fields,
 * its accent as rendered, its logo by the path of RD-07 (never a storage key
 * or path, SEC-CH-06), when it was made, as an instant in UTC, and the
 * display name of the Owner who made it, frozen with it.
 */
final readonly class RevisionData implements PageData
{
    public function __construct(
        public int $id,
        public int $number,
        public string $shopName,
        public ?string $address,
        public ?string $contact,
        public ?string $email,
        public ?string $documentHeader,
        public ?string $documentFooter,
        public ?string $documentIdentity,
        public string $accentToken,
        public ?string $logo,
        public string $createdAt,
        public string $actorDisplayName,
    ) {}

    /**
     * @return array<string, int|string|null>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'shopName' => $this->shopName,
            'address' => $this->address,
            'contact' => $this->contact,
            'email' => $this->email,
            'documentHeader' => $this->documentHeader,
            'documentFooter' => $this->documentFooter,
            'documentIdentity' => $this->documentIdentity,
            'accentToken' => $this->accentToken,
            'logo' => $this->logo,
            'createdAt' => $this->createdAt,
            'actorDisplayName' => $this->actorDisplayName,
        ];
    }
}
