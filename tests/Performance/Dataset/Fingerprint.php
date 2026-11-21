<?php

namespace Tests\Performance\Dataset;

use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * The content of a dataset without what a build makes anew each time — internal keys, intents, keys,
 * document numbers and the instants of the real clock — as one SHA-256: the categories; each product's
 * SKU, name, category, price, minimum, state and barcodes; each product's movements in its order with
 * their kind, quantity and business instant; and each Sale's day, lines and payment (AC-043-01).
 */
final class Fingerprint
{
    public static function of(): string
    {
        $parts = [
            'categories' => DB::table('categories')->orderBy('name')->pluck('name')->all(),
            'products' => DB::table('products as p')
                ->join('categories as c', 'c.id', '=', 'p.category_id')
                ->join('selling_price_revisions as r', 'r.id', '=', 'p.current_price_revision_id')
                ->orderBy('p.sku')
                ->get(['p.sku', 'p.name', 'c.name as category', 'r.amount', 'p.minimum_stock', 'p.active'])
                ->map(static fn (stdClass $row): array => (array) $row)
                ->all(),
            'tokens' => DB::table('barcode_mappings as m')
                ->join('barcode_tokens as t', 't.id', '=', 'm.token_id')
                ->join('products as p', 'p.id', '=', 'm.product_id')
                ->leftJoin('barcode_mapping_retirements as x', 'x.mapping_id', '=', 'm.id')
                ->orderBy('t.token')
                ->get(['t.token', 'p.sku', 'x.mapping_id as retired'])
                ->map(static fn (stdClass $row): array => [$row->token, $row->sku, $row->retired !== null])
                ->all(),
            'movements' => DB::table('stock_movements as m')
                ->join('products as p', 'p.id', '=', 'm.product_id')
                ->orderBy('p.sku')
                ->orderBy('m.product_sequence')
                ->get(['p.sku', 'm.movement_type', 'm.quantity', 'm.recorded_at'])
                ->map(static fn (stdClass $row): array => (array) $row)
                ->all(),
            'sales' => DB::table('sales as s')
                ->join('payments as y', 'y.sale_id', '=', 's.id')
                ->orderBy('s.committed_at')
                ->orderBy('s.sale_number_seq')
                ->get(['s.business_date', 's.committed_at', 's.total_amount', 's.line_count', 's.actor_display_name', 'y.method'])
                ->map(static fn (stdClass $row): array => (array) $row)
                ->all(),
            'counts' => [
                'auditEvents' => DB::table('audit_events')->count(),
                'sessions' => DB::table('cash_sessions')->count(),
                'saleLines' => DB::table('sale_lines')->count(),
            ],
        ];

        return hash('sha256', json_encode($parts, JSON_THROW_ON_ERROR));
    }
}
