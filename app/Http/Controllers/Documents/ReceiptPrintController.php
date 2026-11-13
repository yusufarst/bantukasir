<?php

namespace App\Http\Controllers\Documents;

use App\Domain\Documents\Receipts\ReceiptPrints;
use App\Domain\Documents\Templates;
use App\Http\Requests\Documents\ReceiptPrintPageRequest;
use App\Models\User;
use App\Policies\NotFound;
use App\Policies\ObjectScope;
use App\Queries\Sales\Receipts;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RP-103P, the print view of a receipt (ROUTE_CONTRACTS RP-103P; DOCUMENT_CONTRACTS DC-01; BR-DOC-01 to
 * BR-DOC-04, BR-DOC-06; ARC-44, ARC-45; UXA-23; TASK-031 scope item 2): the receipt rendered from its
 * snapshot only, in the size asked for, by the template version the snapshot names, with the copy
 * marker when its print request recorded a copy. The Sale is read under the viewer's scope now — its
 * own cashier with CAP-21, or the Owner — and the print request must be a committed RC-44 of this Sale
 * by this viewer; anything else, a guessed key included, is one NOT_FOUND (SEC-AZ-06, TR-ERR-03). A
 * read (TR-HTTP-02): it writes nothing, so a print is recorded once.
 */
final class ReceiptPrintController
{
    /**
     * @throws NotFound
     */
    public function show(ReceiptPrintPageRequest $request, string $sale, string $printRequest, Receipts $receipts, ReceiptPrints $prints): Response
    {
        $viewer = $request->user();

        if (! $viewer instanceof User) {
            abort(401);
        }

        $read = $receipts->forViewer($sale, $viewer);
        $saleKey = ObjectScope::key($sale) ?? throw new NotFound;
        $requestKey = ObjectScope::key($printRequest);
        $print = $requestKey === null ? null : $prints->find($saleKey, $requestKey, $viewer->id);

        if ($print === null) {
            throw new NotFound;
        }

        return Inertia::render('documents/ReceiptPrint', [
            ...$read,
            'template' => Templates::receipt($receipts->template($saleKey)),
            'size' => $request->size(),
            'copy' => $print->copy,
        ]);
    }
}
