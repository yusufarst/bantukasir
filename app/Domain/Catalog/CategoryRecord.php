<?php

namespace App\Domain\Catalog;

/**
 * A row of `categories` as an Action reads it (SCHEMA section 5.6).
 */
final readonly class CategoryRecord
{
    public function __construct(
        public int $id,
        public string $name,
        public bool $active,
        public int $version,
    ) {}
}
