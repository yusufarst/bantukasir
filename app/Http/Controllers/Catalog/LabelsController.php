<?php

namespace App\Http\Controllers\Catalog;

use App\Queries\Catalog\ProductPages;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RP-306 "Cetak label barcode" (ROUTE_CONTRACTS section 2; COPY_DECK section 8.7; BR-CAT-35;
 * TASK-018 scope item 3): the product's name and its active tokens, from which RC-47 takes
 * one. Nothing is recorded before printing.
 */
final class LabelsController
{
    public function create(string $product, ProductPages $pages): Response
    {
        $detail = $pages->detail($product);

        return Inertia::render('catalog/Labels', [
            'product' => ['id' => $detail->id, 'name' => $detail->name],
            'barcodes' => $pages->activeBarcodes($detail->id),
        ]);
    }
}
