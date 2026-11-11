<?php

namespace App\Http\Requests\Sales;

use App\Actions\Sales\CheckoutSale;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use App\Domain\Sales\PaymentMethods;
use App\Http\Requests\Commands\CommandRequest;
use App\Support\Formatting\Format;
use App\Support\Numbers\Quantity;
use App\Support\Validation\MoneyRule;
use App\Support\Validation\QuantityRule;
use Illuminate\Contracts\Validation\Validator;

/**
 * The FormRequest of RC-01 `sale.checkout` (INTERACTION_CONTRACTS RC-01; TECHNICAL_RULES sections 5,
 * 11.1 to 11.3; COPY_DECK section 16.1; D-067, D-077): `sessionId`, the cart's session; `lines`, 1 to
 * 200, each with its `productId` and its `quantity`, each product at most once; `payment`, its
 * `method` of the server's list, `tendered` for CASH alone and required there, `reference` for
 * BANK_TRANSFER alone, optional, up to 100 characters, and the optional `customerName`, up to 100.
 * `expected.lines` names, at the index of each line, the price revision that the cashier reviewed. A
 * line takes no price, total or cost, and the payment no amount: the server computes them (TR-VAL-03),
 * and the allowlist refuses them, as it refuses a second payment. Identifiers and revisions are JSON
 * whole numbers (TR-VAL-06); a quantity is judged here at the largest precision, and against its
 * product's only under its lock (TR-VAL-08).
 *
 * Refused before any lock (RC-01): a method outside the list, STATIC_QRIS included, is
 * METHOD_NOT_ALLOWED (BR-PAY-01); a product named twice is VALIDATION_FAILED at each repeated line,
 * never merged (D-067); anything else that fails is VALIDATION_FAILED at its fields.
 */
final class CheckoutSaleRequest extends CommandRequest
{
    private const string PLAIN_TEXT = '/\A[^\p{Cc}]*\z/u';

    public function operation(): string
    {
        return CheckoutSale::OPERATION;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payloadRules(): array
    {
        return [
            'sessionId' => ['required', 'integer', 'min:1'],
            'lines' => ['required', 'array', 'list', 'min:1', 'max:'.CheckoutSale::MAX_LINES],
            'lines.*' => ['required', 'array'],
            'lines.*.productId' => ['required', 'integer', 'min:1'],
            'lines.*.quantity' => ['required', new QuantityRule(Quantity::MAX_PRECISION)],
            'payment' => ['required', 'array'],
            'payment.method' => ['required', 'string', 'in:'.implode(',', PaymentMethods::ALL)],
            'payment.tendered' => ['nullable', 'required_if:payload.payment.method,'.PaymentMethods::CASH, 'prohibited_if:payload.payment.method,'.PaymentMethods::BANK_TRANSFER, new MoneyRule],
            'payment.reference' => ['nullable', 'string', 'max:100', 'regex:'.self::PLAIN_TEXT, 'prohibited_if:payload.payment.method,'.PaymentMethods::CASH],
            'payment.customerName' => ['nullable', 'string', 'max:100', 'regex:'.self::PLAIN_TEXT],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function expectedRules(): array
    {
        return [
            'lines' => ['required', 'array', 'list'],
            'lines.*' => ['required', 'array'],
            'lines.*.priceRevisionId' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [...parent::after(), function (Validator $validator): void {
            $session = $this->input('payload.sessionId');
            $lines = $this->input('payload.lines');
            $reviewed = $this->input('expected.lines');

            if ($session !== null && ! is_int($session)) {
                $validator->errors()->add('', '');
            }

            if (! is_array($lines)) {
                return;
            }

            foreach ($lines as $line) {
                $product = is_array($line) ? ($line['productId'] ?? null) : null;

                if ($product !== null && ! is_int($product)) {
                    $validator->errors()->add('', '');
                }
            }

            if (! self::reviewsEachLine($reviewed, count($lines)) || $this->repeatedLines() !== []) {
                $validator->errors()->add('', '');
            }
        }];
    }

    /**
     * A method outside the server's list is its own refusal (BR-PAY-01), and a product named twice
     * is refused at each line that repeats it (D-067); every other failure is the base's.
     */
    protected function failedValidation(Validator $validator): never
    {
        $method = $validator->failed()['payload.payment.method'] ?? [];

        if (is_array($method) && array_key_exists('In', $method) && is_string($this->input('payload.payment.method'))) {
            throw CommandRefused::of(ErrorCode::MethodNotAllowed);
        }

        $repeated = $this->repeatedLines();

        if ($repeated !== []) {
            throw new CommandRefused(ErrorCode::ValidationFailed, lines: $repeated);
        }

        parent::failedValidation($validator);
    }

    /**
     * @return array<string, array{0: string, 1?: array<string, string|int>}>
     */
    protected function fieldKeys(): array
    {
        $money = MoneyRule::problem($this->input('payload.payment.tendered'), 'payload.payment.tendered') ?? 'money';
        $keys = [
            'payload.lines.required' => ['ux.validation.lines_min'],
            'payload.lines.min' => ['ux.validation.lines_min'],
            'payload.lines.max' => ['ux.validation.lines_max', ['maks' => CheckoutSale::MAX_LINES]],
            'payload.lines.*.productId.integer' => ['ux.validation.choose_one'],
            'payload.lines.*.productId.min' => ['ux.validation.choose_one'],
            'payload.payment.tendered.required_if' => ['ux.validation.required'],
            'payload.payment.tendered.money_rule' => ['ux.validation.'.$money],
            'payload.payment.reference.max' => ['ux.validation.text_max', ['maks' => 100]],
            'payload.payment.customerName.max' => ['ux.validation.text_max', ['maks' => 100]],
        ];
        $lines = $this->input('payload.lines');
        $rule = new QuantityRule(Quantity::MAX_PRECISION);

        foreach (is_array($lines) ? $lines : [] as $index => $line) {
            $values = is_array($line) ? $line : [];
            $path = 'payload.lines.'.(string) $index;
            $quantity = $rule->problem($values['quantity'] ?? null, $path.'.quantity');

            if ($quantity !== null) {
                $keys[$path.'.quantity.quantity_rule'] = ['ux.validation.'.$quantity, $quantity === 'quantity_decimals' ? ['desimal' => Format::count(Quantity::MAX_PRECISION)] : []];
            }
        }

        return $keys;
    }

    /**
     * The lines that name a product an earlier line names, each by its index with its product
     * (INTERACTION_CONTRACTS section 1.6; D-067).
     *
     * @return array<int, array{productId: int}>
     */
    private function repeatedLines(): array
    {
        $lines = $this->input('payload.lines');
        $seen = [];
        $repeated = [];

        foreach (is_array($lines) ? array_values($lines) : [] as $index => $line) {
            $product = is_array($line) ? ($line['productId'] ?? null) : null;

            if (! is_int($product)) {
                continue;
            }

            if (isset($seen[$product])) {
                $repeated[$index] = ['productId' => $product];
            }

            $seen[$product] = true;
        }

        return $repeated;
    }

    /**
     * Whether `expected.lines` names the price revision of each line, one entry per line, each a
     * JSON whole number.
     */
    private static function reviewsEachLine(mixed $reviewed, int $lines): bool
    {
        if (! is_array($reviewed) || count($reviewed) !== $lines) {
            return false;
        }

        foreach ($reviewed as $entry) {
            if (! is_int(is_array($entry) ? ($entry['priceRevisionId'] ?? null) : null)) {
                return false;
            }
        }

        return true;
    }
}
