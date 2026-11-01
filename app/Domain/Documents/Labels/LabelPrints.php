<?php

namespace App\Domain\Documents\Labels;

use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The print requests of barcode labels (RC-47, RP-306P; DC-05): a print request is the
 * committed command, whose only record is its audit event (BR-AUD-06), which holds the
 * code and the count. RP-306P reads it, and rendering it is a read (TR-HTTP-02). The name
 * and SKU are the product's as they stand. A print request of another product, or any
 * other command's key, is none of this product's.
 */
final class LabelPrints
{
    public const string AUDIT_ACTION = 'PRINT';

    public const string AUDIT_SUBJECT = 'barcode_mapping';

    /**
     * The address of RP-306P.
     */
    public static function path(int $product, int $printRequest): string
    {
        return '/products/'.(string) $product.'/labels/print/'.(string) $printRequest;
    }

    public function find(int $product, int $printRequest): ?LabelSheet
    {
        $row = DB::table('audit_events', 'a')
            ->join('products as p', 'p.id', '=', 'a.product_id')
            ->where('a.command_id', $printRequest)
            ->where('a.product_id', $product)
            ->where('a.action', self::AUDIT_ACTION)
            ->where('a.subject_type', self::AUDIT_SUBJECT)
            ->first(['a.change_data', 'p.name', 'p.sku']);

        if ($row === null) {
            return null;
        }

        $values = (array) $row;
        $data = json_decode(is_string($values['change_data'] ?? null) ? $values['change_data'] : '', true);
        $name = $values['name'] ?? null;
        $sku = $values['sku'] ?? null;
        $code = is_array($data) ? ($data['barcode'] ?? null) : null;
        $count = is_array($data) ? ($data['count'] ?? null) : null;

        if (! is_string($name) || ! is_string($sku) || ! is_string($code) || ! is_int($count)) {
            throw new LogicException('The audit event of a print request holds its code and count (RC-47).');
        }

        return new LabelSheet($name, $sku, $code, $count);
    }
}
