<?php

namespace App\Queries\Catalog;

/**
 * The validated parameters of RD-01 (ROUTE_CONTRACTS RD-01): the search text, with
 * its case and punctuation as typed; the filters of a Product Search; and its page.
 */
final readonly class SearchFilters
{
    /**
     * @param  'active'|'inactive'|null  $status
     */
    public function __construct(
        public string $q = '',
        public ?int $category = null,
        public ?string $brand = null,
        public ?string $status = null,
        public int $page = 1,
        public int $perPage = ProductSearch::PER_PAGE,
    ) {}
}
