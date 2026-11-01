<?php

namespace App\DTO\Catalog;

use App\DTO\PageData;

/**
 * A category as the catalogue pages show it (ROUTE_CONTRACTS RP-301, RP-303, RP-304,
 * RP-307; BR-CAT-09): its identity, name, active flag and the version that RC-22 to
 * RC-24 expect.
 */
final readonly class CategoryRowData implements PageData
{
    public function __construct(
        public int $id,
        public string $name,
        public bool $active,
        public int $version,
    ) {}

    /**
     * @return array{id: int, name: string, active: bool, version: int}
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'active' => $this->active, 'version' => $this->version];
    }
}
