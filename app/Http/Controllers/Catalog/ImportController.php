<?php

namespace App\Http\Controllers\Catalog;

use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Domain\Catalog\Import\CsvColumn;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RP-308, UXP-308 "Impor produk (CSV)" (ROUTE_CONTRACTS section 2; TASK-019 scope item 5):
 * the column list of DOCUMENT_CONTRACTS DC-04 and whether UXI-209's page opens for the
 * viewer (CAP-04).
 */
final class ImportController
{
    public function show(Request $request): Response
    {
        $viewer = $request->user();

        return Inertia::render('catalog/Import', [
            'columns' => array_map(static fn (CsvColumn $column): array => ['header' => $column->header(), 'required' => $column->required()], CsvColumn::cases()),
            'canManageCategories' => $viewer instanceof User && Capabilities::allows($viewer, Capability::Cap04),
        ]);
    }
}
