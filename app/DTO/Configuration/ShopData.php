<?php

namespace App\DTO\Configuration;

use App\DTO\PageData;

/**
 * The shop's identity in the shell of every signed-in page (DESIGN_SYSTEM
 * section 7.2; SECURITY_MODEL SEC-CH-02 as TASK-010 amends it; REQ-SCOPE-03):
 * the shop name and its initials from the BusinessProfile revision in force,
 * and its accent, green when the revision names none. Before the bootstrap
 * there is no revision: no name and no initials, the default accent. Nothing
 * else of the BusinessProfile reaches every page.
 */
final readonly class ShopData implements PageData
{
    public function __construct(
        public ?string $name,
        public ?string $initials,
        public string $accent,
    ) {}

    /**
     * @return array{name: string|null, initials: string|null, accent: string}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'initials' => $this->initials,
            'accent' => $this->accent,
        ];
    }
}
