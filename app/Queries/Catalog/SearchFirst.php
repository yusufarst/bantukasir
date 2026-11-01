<?php

namespace App\Queries\Catalog;

use Illuminate\Database\Query\Builder;

/**
 * RD-06, the search-first check of UXP-303 (ROUTE_CONTRACTS RD-06;
 * INTERACTION_CONTRACTS section 3.5; BR-CAT-04; TASK-015 scope item 4): the product
 * whose SKU is the one typed, without regard to letter case, and up to 10 products
 * of a similar name. It refuses nothing: a similar name is a warning, and a taken
 * SKU is refused when the product is created (RC-17). TASK-016 adds the barcode.
 *
 * A similar name is one that pg_trgm finds similar (`%`, its threshold of 0.3) or
 * that holds the typed name, which a long name holding a short one is not; the most
 * similar first, then by name and identifier. Every row is on the GIN index of
 * lower(name) (SCHEMA section 11).
 */
final class SearchFirst
{
    public const int SIMILAR_NAMES = 10;

    /**
     * @return array{sku: array<string, mixed>|null, similarNames: list<array<string, mixed>>}
     */
    public function __invoke(?string $sku, ?string $name): array
    {
        return [
            'sku' => $sku === null ? null : $this->exactSku($sku),
            'similarNames' => $name === null ? [] : $this->similarNames($name),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function exactSku(string $sku): ?array
    {
        $row = ProductRows::query()->whereRaw('lower(p.sku) = lower(?)', [$sku])->first();

        return $row === null ? null : ProductRows::row($row)->toArray();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function similarNames(string $name): array
    {
        $pattern = ProductRows::literal($name);
        $rows = ProductRows::query()
            ->where(static function (Builder $match) use ($name, $pattern): void {
                $match->whereRaw('lower(p.name) % lower(?)', [$name])
                    ->orWhereRaw("lower(p.name) like '%' || lower(?) || '%' escape '!'", [$pattern]);
            })
            ->orderByRaw('similarity(lower(p.name), lower(?)) desc', [$name])
            ->orderBy('p.name')
            ->orderBy('p.id')
            ->limit(self::SIMILAR_NAMES)
            ->get()
            ->all();

        return array_values(array_map(static fn (object $row): array => ProductRows::row($row)->toArray(), $rows));
    }
}
