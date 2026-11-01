<?php

namespace App\Http\Controllers\Documents;

use App\Domain\Documents\Labels\LabelPrints;
use App\Policies\NotFound;
use App\Policies\ObjectScope;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RP-306P, the print view of barcode labels (ROUTE_CONTRACTS section 2; DC-05; TASK-018
 * scope item 3): the code and count that a committed RC-47 of this product recorded, a
 * read (TR-HTTP-02) that writes nothing, so that a print request is recorded once. A
 * print request that does not exist, of another product or of another command is one
 * NOT_FOUND (TR-ERR-03).
 */
final class LabelPrintController
{
    public function show(string $product, string $printRequest, LabelPrints $prints): Response
    {
        $productKey = ObjectScope::key($product);
        $requestKey = ObjectScope::key($printRequest);
        $sheet = $productKey === null || $requestKey === null ? null : $prints->find($productKey, $requestKey);

        if ($sheet === null) {
            throw new NotFound;
        }

        return Inertia::render('documents/LabelPrint', ['label' => $sheet->toArray()]);
    }
}
