<?php

namespace Tests\Performance\Dataset;

use App\Actions\Cash\CloseCashSession;
use App\Actions\Cash\CreateCashRegister;
use App\Actions\Cash\OpenCashSession;
use App\Actions\Catalog\AddBarcode;
use App\Actions\Catalog\CreateCategory;
use App\Actions\Catalog\CreateProduct;
use App\Actions\Catalog\DeactivateProduct;
use App\Actions\Catalog\RetireBarcode;
use App\Actions\Commands\CommandAction;
use App\Actions\Commands\CommandContext;
use App\Actions\Documents\RecordReceiptPrint;
use App\Actions\Inventory\ConfirmReceiving;
use App\Actions\Sales\CheckoutSale;
use App\Domain\Commands\Envelope;
use App\Domain\Commands\Operations;
use App\Models\User;
use App\Support\Time\TestClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use Tests\Harness\Fixtures\TokoContoh;

/**
 * Builds a dataset of TECHNICAL_RULES section 13.2 on Toko Contoh through the Actions (TEST_STRATEGY
 * section 3; EXECUTION_CONTEXT EXC-45; TASK-043 scope item 1): every record is a command of its
 * Action, run by the Owner or by a cashier as themselves, at the instant of the test clock, which the
 * builder moves forward through the dataset's days (STK-31), so that Sales, receivings and sessions lie
 * on their business dates. Only the counts of DatasetSize and the seed decide what it builds.
 *
 * It runs only through Datasets, after the check of the marker (ENV-04), and it keeps in memory what a
 * page would have read before its command — prices, product versions, stock — so that each command is
 * the one a person would send, never one that the server refuses.
 */
final class ShopBuilder
{
    /** The cashiers of the dataset (KEY_SCREENS section 5): two concurrent cashiers. */
    public const array CASHIERS = ['Rina', 'Dewi'];

    /** The three registers: one for each cashier, and one for the dry run's close of a session. */
    public const array REGISTERS = [['LACI-1', 'Laci 1'], ['LACI-2', 'Laci 2'], ['LACI-3', 'Laci 3']];

    /** The words of the products' invented names. */
    private const array GOODS = ['Pulpen', 'Buku Tulis', 'Kertas HVS', 'Map Plastik', 'Penggaris', 'Spidol', 'Lem Kertas', 'Gunting', 'Isolasi', 'Amplop', 'Sabun Mandi', 'Sampo', 'Pasta Gigi', 'Mi Instan', 'Teh Celup', 'Kopi Bubuk', 'Gula Pasir', 'Minyak Goreng', 'Beras', 'Air Mineral'];

    private const array VARIANTS = ['Merah', 'Biru', 'Hitam', 'Kecil', 'Besar', 'Ekonomis', 'Isi Ulang', 'Premium'];

    private readonly int $owner;

    private readonly string $epoch;

    /** @var array<int, array{price: int, cost: int, revision: int, version: int}> */
    private array $products = [];

    /** @var list<int> */
    private array $active = [];

    /** @var list<int> */
    private array $categories = [];

    /** @var array<int, int> the stock of each product, in whole units */
    private array $stock = [];

    /** @var list<int> */
    private array $registers = [];

    /** @var array<string, int> */
    private array $cashiers = [];

    private int $tokens = 0;

    public function __construct(
        private readonly Seeded $random,
        private readonly TestClock $clock,
    ) {
        TokoContoh::shop();
        $this->epoch = TokoContoh::recoveryEpoch();
        $this->owner = User::query()->where('role_super_admin', true)->where('status', 'ACTIVE')->firstOrFail()->id;
    }

    /**
     * The catalogue, the registers and the cashiers, a second apart, from this instant on.
     */
    public function catalogue(DatasetSize $size, CarbonImmutable $from): void
    {
        $this->clock->set($from);

        for ($c = 1; $c <= $size->categories; $c++) {
            $this->categories[] = $this->id($this->asOwner(CreateCategory::class, CreateCategory::OPERATION, ['name' => sprintf('Kategori %02d', $c)]));
        }

        $categories = $this->categories;

        for ($p = 1; $p <= $size->activeProducts + $size->inactiveProducts; $p++) {
            $price = $this->random->between(4, 500) * 500;
            $product = $this->id($this->asOwner(CreateProduct::class, CreateProduct::OPERATION, [
                'sku' => sprintf('PRF-%05d', $p),
                'name' => sprintf('%s %s %d', $this->random->pick(self::GOODS), $this->random->pick(self::VARIANTS), $p),
                'categoryId' => $categories[($p - 1) % count($categories)],
                'brand' => null,
                'baseUnit' => 'pcs',
                'quantityPrecision' => 0,
                'sellingPrice' => (string) $price,
                'minimumStock' => (string) $this->random->between(0, 10),
            ]));
            $this->products[$product] = ['price' => $price, 'cost' => intdiv($price * 6, 1000) * 100, 'revision' => 0, 'version' => 0];
            $this->stock[$product] = 0;

            if ($p <= $size->activeProducts) {
                $this->active[] = $product;
            }
        }

        $this->readProducts();

        foreach (array_keys($this->products) as $index => $product) {
            if ($index >= $size->activeProducts) {
                $this->asOwner(DeactivateProduct::class, DeactivateProduct::OPERATION, ['productId' => $product], ['productVersion' => $this->products[$product]['version']]);

                continue;
            }

            $this->barcode($product);

            if ($index % 2 === 1 && intdiv($index, 2) < $size->secondTokens) {
                $this->barcode($product);
            }
        }

        $active = $this->active;

        for ($r = 0; $r < $size->retiredTokens; $r++) {
            $mapping = $this->barcode($active[$this->random->between(0, count($active) - 1)]);
            $this->asOwner(RetireBarcode::class, RetireBarcode::OPERATION, ['mappingId' => $mapping, 'reasonClass' => 'OTHER']);
        }

        foreach (self::REGISTERS as [$code, $label]) {
            $this->registers[] = $this->id($this->asOwner(CreateCashRegister::class, CreateCashRegister::OPERATION, ['code' => $code, 'label' => $label, 'active' => true]));
        }

        foreach (self::CASHIERS as $cashier) {
            $this->cashiers[$cashier] = TokoContoh::person($cashier)['id'];
        }

        // A product's version as its pages read it after the catalogue: receivings expect it.
        $this->readProducts();
    }

    /**
     * The active products, in the order they were created: the first is the product of 10,000 movements.
     *
     * @return non-empty-list<int>
     */
    public function active(): array
    {
        return $this->active !== [] ? $this->active : throw new LogicException('The catalogue comes first.');
    }

    /**
     * A receiving of these quantities, each at the product's cost, by the Owner.
     *
     * @param  array<int, int>  $quantities  by product
     */
    public function receive(array $quantities, ?string $reference = null): void
    {
        $lines = [];
        $versions = [];

        foreach ($quantities as $product => $quantity) {
            $lines[] = ['productId' => $product, 'quantity' => (string) $quantity, 'cost' => ['status' => 'KNOWN', 'amount' => (string) ($quantity * $this->products[$product]['cost'])]];
            $versions[] = ['productId' => $product, 'version' => $this->products[$product]['version']];
            $this->stock[$product] += $quantity;
        }

        $this->asOwner(ConfirmReceiving::class, ConfirmReceiving::OPERATION, ['reference' => $reference, 'documentDate' => null, 'lines' => $lines], ['products' => $versions]);
    }

    /**
     * The opening receipts of every active product, in documents of at most 500 lines.
     *
     * @param  array<int, int>  $quantities  by product
     */
    public function receiveAll(array $quantities): void
    {
        foreach (array_chunk($quantities, 500, true) as $chunk) {
            $this->receive($chunk, 'PEMBUKAAN');
            $this->clock->advanceSeconds(60);
        }
    }

    /**
     * Each cashier's session, opened on their own register.
     *
     * @return array<string, array{session: int, cash: int}>
     */
    public function openSessions(): array
    {
        $sessions = [];

        foreach (self::CASHIERS as $index => $cashier) {
            $envelope = $this->envelope(OpenCashSession::OPERATION, [], ['registerId' => $this->registers[$index], 'openingCash' => '200000']);
            $sessions[$cashier] = ['session' => $this->id(app(OpenCashSession::class)->execute(new CommandContext($this->cashiers[$cashier], $envelope, $this->clock->now()))->result), 'cash' => 200000];
            $this->clock->advanceSeconds(1);
        }

        return $sessions;
    }

    /**
     * The close of each session by its cashier, with the cash it should hold, at its activity version.
     *
     * @param  array<string, array{session: int, cash: int}>  $sessions
     */
    public function closeSessions(array $sessions): void
    {
        foreach ($sessions as $cashier => $open) {
            $version = DB::table('cash_sessions')->where('id', $open['session'])->value('activity_version');
            $envelope = $this->envelope(CloseCashSession::OPERATION, ['activityVersion' => is_int($version) ? $version : 0], ['sessionId' => $open['session'], 'physicalCash' => (string) $open['cash']]);
            app(CloseCashSession::class)->execute(new CommandContext($this->cashiers[$cashier], $envelope, $this->clock->now()));
            $this->clock->advanceSeconds(1);
        }
    }

    /**
     * A Sale of these quantities by the cashier in their session, in cash or by bank transfer, its
     * products received first where the stock would not cover it: its internal key.
     *
     * @param  array<int, int>  $quantities  by product
     * @param  array{session: int, cash: int}  $open
     */
    public function sell(string $cashier, array &$open, array $quantities, bool $transfer): int
    {
        $short = array_filter($quantities, fn (int $quantity, int $product): bool => $this->stock[$product] < $quantity, ARRAY_FILTER_USE_BOTH);

        if ($short !== []) {
            $this->receive(array_map(static fn (int $quantity): int => $quantity + 100, $short), 'TAMBAHAN');
        }

        $lines = [];
        $reviewed = [];
        $total = 0;

        foreach ($quantities as $product => $quantity) {
            $lines[] = ['productId' => $product, 'quantity' => (string) $quantity];
            $reviewed[] = ['priceRevisionId' => $this->products[$product]['revision']];
            $total += $quantity * $this->products[$product]['price'];
            $this->stock[$product] -= $quantity;
        }

        $payment = $transfer ? ['method' => 'BANK_TRANSFER', 'reference' => sprintf('TRF-%d', $this->random->between(100000, 999999))] : ['method' => 'CASH', 'tendered' => (string) $total];
        $envelope = $this->envelope(CheckoutSale::OPERATION, ['lines' => $reviewed], ['sessionId' => $open['session'], 'lines' => $lines, 'payment' => $payment]);
        $sale = $this->id(app(CheckoutSale::class)->execute(new CommandContext($this->cashiers[$cashier], $envelope, $this->clock->now()))->result);

        if (! $transfer) {
            $open['cash'] += $total;
        }

        return $sale;
    }

    /**
     * A print request of a Sale's receipt by its cashier: the first is the original, every later one a copy.
     */
    public function printReceipt(string $cashier, int $sale, string $size): void
    {
        $envelope = $this->envelope(RecordReceiptPrint::OPERATION, [], ['saleId' => $sale, 'size' => $size]);
        app(RecordReceiptPrint::class)->execute(new CommandContext($this->cashiers[$cashier], $envelope, $this->clock->now()));
    }

    /**
     * A manufacturer barcode, EAN-13 in the GS1 prefix 20 of restricted circulation, which names no real
     * product: its mapping's internal key.
     */
    private function barcode(int $product): int
    {
        $this->tokens++;
        $digits = '20'.sprintf('%010d', $this->tokens);
        $sum = 0;

        foreach (str_split($digits) as $position => $digit) {
            $sum += (int) $digit * ($position % 2 === 0 ? 1 : 3);
        }

        return $this->id($this->asOwner(AddBarcode::class, AddBarcode::OPERATION, ['productId' => $product, 'token' => $digits.(string) ((10 - $sum % 10) % 10), 'symbology' => 'EAN_13']));
    }

    private function readProducts(): void
    {
        foreach (DB::table('products')->get(['id', 'version', 'current_price_revision_id']) as $row) {
            $id = $row->id;

            if (is_int($id) && isset($this->products[$id]) && is_int($row->version) && is_int($row->current_price_revision_id)) {
                $this->products[$id]['version'] = $row->version;
                $this->products[$id]['revision'] = $row->current_price_revision_id;
            }
        }
    }

    /**
     * A command that the Owner runs through its Action, a second after the one before it.
     *
     * @param  class-string<CommandAction>  $action
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $expected
     * @return array<array-key, mixed>|null
     */
    private function asOwner(string $action, string $operation, array $payload, array $expected = []): ?array
    {
        $this->clock->advanceSeconds(1);

        return app($action)->execute(new CommandContext($this->owner, $this->envelope($operation, $expected, $payload), $this->clock->now()))->result;
    }

    /**
     * @param  array<string, mixed>  $expected
     * @param  array<string, mixed>  $payload
     */
    private function envelope(string $operation, array $expected, array $payload): Envelope
    {
        return new Envelope($operation, Operations::VERSION, (string) Str::uuid(), (string) Str::uuid(), $this->epoch, $expected, $payload);
    }

    /**
     * @param  array<array-key, mixed>|null  $result
     */
    private function id(?array $result): int
    {
        $id = $result['id'] ?? null;

        return is_int($id) ? $id : throw new LogicException('A command of the dataset committed and named its record.');
    }
}
