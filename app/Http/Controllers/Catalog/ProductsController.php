<?php

namespace App\Http\Controllers\Catalog;

use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Domain\Access\Grant;
use App\DTO\Catalog\CategoryRowData;
use App\Http\Requests\Catalog\ProductCreatePageRequest;
use App\Http\Requests\Catalog\ProductsPageRequest;
use App\Models\User;
use App\Queries\Catalog\CategoryRows;
use App\Queries\Catalog\ProductPages;
use App\Queries\Inventory\ProductStock;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RP-301 to RP-304, the product pages (ROUTE_CONTRACTS section 2; COPY_DECK sections 8.2
 * to 8.5; TASK-017 scope item 1). RP-301 gives its filters and the categories to filter
 * by; its results come from RD-01. RP-302's sections go by capability: the price history
 * (CAP-04, the page's gate), the photo (CAP-09), the barcodes (CAP-06) with the way to their
 * correction (CAP-07, TASK-020), the labels (CAP-08) and the stock (CAP-14, TASK-022); TASK-025
 * adds its own.
 * Whether a product's unit and precision are locked is Inventory's to say (BR-CAT-07; STK-13).
 */
final class ProductsController
{
    public function index(ProductsPageRequest $request, CategoryRows $categories): Response
    {
        return Inertia::render('catalog/Products', [
            'filters' => $request->filters(),
            'categories' => self::arrays($categories->list()),
            'canPrintLabels' => self::allows($request->user(), Capability::Cap08),
        ]);
    }

    public function create(ProductCreatePageRequest $request, CategoryRows $categories): Response
    {
        return Inertia::render('catalog/ProductCreate', [
            'categories' => self::arrays($categories->choices()),
            'code' => $request->code(),
        ]);
    }

    public function show(Request $request, string $product, ProductPages $pages, ProductStock $stock): Response
    {
        $detail = $pages->detail($product);
        $detail = $detail->withUnitLocked($stock->unitLocked($detail->id));
        $viewer = $request->user();

        return Inertia::render('catalog/Product', [
            'product' => $detail->toArray(),
            'priceHistory' => $pages->priceHistory($detail->id),
            'barcodes' => self::allows($viewer, Capability::Cap06) ? $pages->barcodes($detail->id) : null,
            'canCorrectBarcode' => self::allows($viewer, Capability::Cap07),
            'canChangePhoto' => self::allows($viewer, Capability::Cap09),
            // UXI-175: a product with an active barcode (INTERACTION_INVENTORY).
            'canPrintLabels' => self::allows($viewer, Capability::Cap08) && $pages->activeBarcodes($detail->id) !== [],
            // UXI-167, the section "Stok" (TASK-022): for a full holder of CAP-14.
            'stock' => $viewer instanceof User && Capabilities::of($viewer, Capability::Cap14) === Grant::Full ? $stock->of($detail->id, $detail->active) : null,
        ]);
    }

    public function edit(string $product, ProductPages $pages, CategoryRows $categories, ProductStock $stock): Response
    {
        $detail = $pages->detail($product);
        $detail = $detail->withUnitLocked($stock->unitLocked($detail->id));

        return Inertia::render('catalog/ProductEdit', [
            'product' => $detail->toArray(),
            'categories' => self::arrays($categories->choices($detail->categoryId)),
        ]);
    }

    private static function allows(mixed $viewer, Capability $capability): bool
    {
        return $viewer instanceof User && Capabilities::allows($viewer, $capability);
    }

    /**
     * @param  list<CategoryRowData>  $rows
     * @return list<array{id: int, name: string, active: bool, version: int}>
     */
    private static function arrays(array $rows): array
    {
        return array_map(static fn (CategoryRowData $row): array => $row->toArray(), $rows);
    }
}
