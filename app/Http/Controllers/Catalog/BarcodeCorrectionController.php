<?php

namespace App\Http\Controllers\Catalog;

use App\Queries\Catalog\ProductPages;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RP-305 "Koreksi salah pasang barcode" (ROUTE_CONTRACTS section 2; COPY_DECK section 8.6;
 * TASK-020 scope item 3): the form, opened from a product, to which it leads back. The token
 * is looked up through RD-05, and the correct product through RD-01.
 */
final class BarcodeCorrectionController
{
    public function create(string $product, ProductPages $pages): Response
    {
        $detail = $pages->detail($product);

        return Inertia::render('catalog/BarcodeCorrection', [
            'product' => ['id' => $detail->id],
        ]);
    }
}
