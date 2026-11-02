<?php

namespace App\Http\Requests\Catalog;

use App\Domain\Catalog\Barcodes\Symbology;
use App\Http\Requests\Commands\CommandRequest;
use App\Support\Formatting\Format;
use App\Support\Numbers\Quantity;
use App\Support\Validation\BarcodeTokenRule;
use App\Support\Validation\MinimumStockRule;
use App\Support\Validation\MoneyRule;
use Illuminate\Contracts\Validation\Validator;

/**
 * The FormRequest base of RC-17 to RC-24 and RC-27 to RC-29 (INTERACTION_CONTRACTS
 * section 5; TECHNICAL_RULES sections 5 and 11.3; COPY_DECK section 16.1; TASK-015
 * scope item 2, TASK-016 scope item 3): the rules of the fields they share and their
 * keys. Every text is trimmed by the global middleware, is valid UTF-8 and holds no
 * control character, a line break included (TR-VAL-05); `[[:cntrl:]]` of the
 * database matches the same set as `\p{Cc}` (TextSemanticsTest, SCH-14). A barcode
 * token alone follows BR-CAT-17 (BarcodeTokenRule). An identifier, a version and the
 * precision are JSON whole numbers, never a text that looks like one (TR-VAL-06).
 * An object's scope is the whole shop's, so a missing one is the Action's
 * NOT_FOUND under its lock.
 */
abstract class CatalogCommandRequest extends CommandRequest
{
    protected const string PLAIN_TEXT = '/\A[^\p{Cc}]*\z/u';

    /** The longest name of the request's record (TECHNICAL_RULES section 11.3). */
    protected const int NAME_MAX = 200;

    /** The paths that hold a JSON whole number. */
    private const array WHOLE_NUMBERS = ['payload.productId', 'payload.targetProductId', 'payload.mappingId', 'payload.categoryId', 'payload.quantityPrecision', 'expected.productVersion', 'expected.categoryVersion', 'expected.mapping.productId'];

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [...parent::after(), function (Validator $validator): void {
            foreach (self::WHOLE_NUMBERS as $path) {
                $value = $this->input($path);

                if ($value !== null && ! is_int($value)) {
                    $validator->errors()->add('', '');
                }
            }
        }];
    }

    /**
     * RC-17's and RC-18's fields, but the SKU and the barcode.
     *
     * @return array<string, mixed>
     */
    protected function productRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200', 'regex:'.self::PLAIN_TEXT],
            'categoryId' => ['required', 'integer', 'min:1'],
            'brand' => ['nullable', 'string', 'max:100', 'regex:'.self::PLAIN_TEXT],
            'baseUnit' => ['required', 'string', 'max:32', 'regex:'.self::PLAIN_TEXT],
            'quantityPrecision' => ['required', 'integer', 'between:0,'.Quantity::MAX_PRECISION],
            'sellingPrice' => ['required', new MoneyRule],
            'minimumStock' => ['required', new MinimumStockRule($this->precision())],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function productTargetRules(): array
    {
        return ['productId' => ['required', 'integer', 'min:1']];
    }

    /**
     * The rules of a token that must be there; its presence is the caller's.
     *
     * @return list<mixed>
     */
    protected static function tokenRules(): array
    {
        return ['string', new BarcodeTokenRule];
    }

    /**
     * @return list<string>
     */
    protected static function symbologyRules(): array
    {
        return ['nullable', 'string', 'in:'.implode(',', Symbology::names())];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function categoryTargetRules(): array
    {
        return ['categoryId' => ['required', 'integer', 'min:1']];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function categoryNameRules(): array
    {
        return ['name' => ['required', 'string', 'max:100', 'regex:'.self::PLAIN_TEXT]];
    }

    /**
     * @return array<string, array{0: string, 1?: array<string, string|int>}>
     */
    protected function fieldKeys(): array
    {
        $money = MoneyRule::problem($this->input('payload.sellingPrice'), 'payload.sellingPrice') ?? 'money';
        $stock = (new MinimumStockRule($this->precision()))->problem($this->input('payload.minimumStock'), 'payload.minimumStock') ?? 'min_stock';

        return [
            'payload.sku.string' => ['ux.validation.sku'],
            'payload.sku.max' => ['ux.validation.sku'],
            'payload.sku.regex' => ['ux.validation.sku'],
            'payload.token.string' => ['ux.validation.barcode_token'],
            'payload.token.barcode_token_rule' => ['ux.validation.barcode_token'],
            'payload.barcode.token.required_with' => ['ux.validation.required'],
            'payload.barcode.token.string' => ['ux.validation.barcode_token'],
            'payload.barcode.token.barcode_token_rule' => ['ux.validation.barcode_token'],
            'payload.symbology.string' => ['ux.validation.choose_one'],
            'payload.symbology.in' => ['ux.validation.choose_one'],
            'payload.barcode.symbology.string' => ['ux.validation.choose_one'],
            'payload.barcode.symbology.in' => ['ux.validation.choose_one'],
            'payload.reasonClass.string' => ['ux.validation.choose_one'],
            'payload.reasonClass.in' => ['ux.validation.choose_one'],
            'payload.name.string' => ['ux.validation.required'],
            'payload.name.max' => ['ux.validation.text_max', ['maks' => static::NAME_MAX]],
            'payload.brand.string' => ['ux.validation.required'],
            'payload.brand.max' => ['ux.validation.text_max', ['maks' => 100]],
            'payload.baseUnit.string' => ['ux.validation.required'],
            'payload.baseUnit.max' => ['ux.validation.text_max', ['maks' => 32]],
            'payload.categoryId.integer' => ['ux.validation.choose_one'],
            'payload.categoryId.min' => ['ux.validation.choose_one'],
            'payload.quantityPrecision.integer' => ['ux.validation.choose_one'],
            'payload.quantityPrecision.between' => ['ux.validation.choose_one'],
            'payload.sellingPrice.money_rule' => ['ux.validation.'.$money],
            'payload.minimumStock.minimum_stock_rule' => ['ux.validation.'.$stock, $stock === 'quantity_decimals' ? ['desimal' => Format::count($this->precision())] : []],
        ];
    }

    /**
     * The payload's precision, or the largest when it is not valid, which its own
     * rule refuses.
     */
    private function precision(): int
    {
        $precision = $this->input('payload.quantityPrecision');

        return is_int($precision) && $precision >= 0 && $precision <= Quantity::MAX_PRECISION ? $precision : Quantity::MAX_PRECISION;
    }
}
