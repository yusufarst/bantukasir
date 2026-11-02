<?php

namespace App\Queries\Catalog;

use App\Domain\Catalog\Barcodes\CurrentMapping;
use App\DTO\Catalog\TokenMappingData;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * RD-05 (ROUTE_CONTRACTS RD-05; INTERACTION_CONTRACTS section 3.5; BR-CAT-17, BR-CAT-52; D-051,
 * D-052; TASK-020 scope item 1): every mapping of the token compared exactly, the newest first,
 * in one statement, so that a correction is seen whole (RACE-18). The newest says what the token
 * is now (CurrentMapping). The list is bounded by the most that a token ever needs, and has no
 * paging.
 */
final class TokenMapping
{
    /** The most mappings that one answer reads. */
    public const int MAPPINGS = 100;

    /** The time of a retirement, as the pages receive every instant: UTC with microseconds. */
    private const string RETIRED_AT = 'to_char(x.retired_at at time zone \'UTC\', \'YYYY-MM-DD"T"HH24:MI:SS.US"Z"\') as retired_at';

    public function __invoke(string $token): TokenMappingData
    {
        $rows = DB::table('barcode_tokens', 't')
            ->join('barcode_mappings as m', 'm.token_id', '=', 't.id')
            ->join('products as p', 'p.id', '=', 'm.product_id')
            ->leftJoin('barcode_active_mappings as a', 'a.mapping_id', '=', 'm.id')
            ->leftJoin('barcode_mapping_retirements as x', 'x.mapping_id', '=', 'm.id')
            ->where('t.token', $token)
            ->orderByDesc('m.id')
            ->limit(self::MAPPINGS)
            ->select(['t.id as token_id', 't.kind', 'm.id as mapping_id', 'm.product_id', 'p.name as product_name', 'a.mapping_id as active_mapping', 'x.reason_class', 'x.actor_display_name'])
            ->selectRaw(self::RETIRED_AT)
            ->get();

        $kind = null;
        $activeProduct = null;
        $retirements = [];
        $mapping = null;
        $newest = true;

        foreach ($rows as $row) {
            $values = (array) $row;
            $tokenId = $values['token_id'] ?? null;
            $rowKind = $values['kind'] ?? null;
            $mappingId = $values['mapping_id'] ?? null;
            $product = $values['product_id'] ?? null;
            $name = $values['product_name'] ?? null;
            $reason = $values['reason_class'] ?? null;
            $active = ($values['active_mapping'] ?? null) !== null;

            if (! is_int($tokenId) || ! is_string($rowKind) || ! is_int($mappingId) || ! is_int($product) || ! is_string($name)) {
                throw new LogicException('A barcode mapping holds its columns (SCHEMA section 5.6).');
            }

            $kind = $rowKind;

            if ($active) {
                $activeProduct = ['id' => $product, 'name' => $name];
            }

            if (is_string($reason)) {
                $retirements[] = ['productId' => $product, 'productName' => $name, 'reasonClass' => $reason, ...self::retirement($values)];
            }

            if ($newest) {
                $state = (new CurrentMapping($tokenId, $rowKind, $mappingId, $product, $active, is_string($reason) ? $reason : null))->correctableState();
                $mapping = $state === null ? null : ['productId' => $product, 'productName' => $name, 'state' => $state];
                $newest = false;
            }
        }

        return new TokenMappingData($token, $kind, $activeProduct, $retirements, $mapping);
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array{retiredAt: string, actorDisplayName: string}
     */
    private static function retirement(array $values): array
    {
        $retiredAt = $values['retired_at'] ?? null;
        $actor = $values['actor_display_name'] ?? null;

        if (! is_string($retiredAt) || ! is_string($actor)) {
            throw new LogicException('A retirement holds its columns (SCHEMA section 5.6).');
        }

        return ['retiredAt' => $retiredAt, 'actorDisplayName' => $actor];
    }
}
