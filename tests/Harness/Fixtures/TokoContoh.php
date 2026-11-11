<?php

namespace Tests\Harness\Fixtures;

use App\Actions\Access\BootstrapInput;
use App\Actions\Access\BootstrapInstallation;
use App\Actions\Access\ConsumeLink;
use App\Actions\Access\CreateStaffAccount;
use App\Actions\Cash\CreateCashRegister;
use App\Actions\Cash\OpenCashSession;
use App\Actions\Catalog\AddBarcode;
use App\Actions\Catalog\CreateCategory;
use App\Actions\Catalog\CreateProduct;
use App\Actions\Catalog\RetireBarcode;
use App\Actions\Commands\CommandAction;
use App\Actions\Commands\CommandContext;
use App\Actions\Costing\CorrectCost;
use App\Actions\Inventory\ConfirmAdjustment;
use App\Actions\Inventory\ConfirmManualIssue;
use App\Actions\Inventory\ConfirmReceiving;
use App\Actions\Inventory\ConfirmReversal;
use App\Actions\Sales\CheckoutSale;
use App\Database\DatabaseRefused;
use App\Database\EnvironmentMarker;
use App\Domain\Access\Accounts;
use App\Domain\Access\OneTimeLinks;
use App\Domain\Access\Role;
use App\Domain\Access\StaffRoles;
use App\Domain\Commands\CommandIdentities;
use App\Domain\Commands\Envelope;
use App\Domain\Commands\Fingerprint;
use App\Domain\Commands\Operations;
use App\Domain\Commands\RecoveryEpoch;
use App\Domain\Commands\StoredResult;
use App\Domain\Configuration\BusinessProfiles;
use App\Domain\Configuration\ProfileFields;
use App\Domain\Inventory\OnboardingState;
use App\Domain\Inventory\PostingBlocks;
use App\Models\User;
use App\Support\Numbers\Money;
use App\Support\Numbers\Quantity;
use App\Support\Time\Clock;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use Tests\Harness\Inventory\PostingBlockScenario;

/**
 * The fixture builder of the synthetic shop "Toko Contoh" (KEY_SCREENS
 * section 5; EXECUTION_CONTEXT section 10; TASK-004). It runs only where the
 * database's marker and the configured environment name are both test or
 * local (ENVIRONMENTS ENV-04), never on any other: it lives in the test
 * harness, which the release build does not contain.
 *
 * Its people and shop are those of the previews, all invented. Fixtures are
 * created through the Actions and services, never by inserting rows
 * (TEST_STRATEGY section 3; EXC-45), so each Task that delivers an Action
 * extends this builder with the records it creates. Since TASK-011 the people's
 * accounts come from OP-01 (Hendra, the Owner) and RC-35 (the staff, created by
 * the Owner), and a password is set the one way a password is set, through a
 * one-time link issued by the broker and consumed by ConsumeLink. A password
 * is generated at run time and never committed (EXECUTION_CONTEXT EXC-63).
 */
final class TokoContoh
{
    public const string SHOP = 'Toko Contoh';

    /**
     * The people of KEY_SCREENS section 5 with their roles (D-032).
     */
    public const array PEOPLE = [
        'Hendra' => ['SUPER_ADMIN'],
        'Agus' => ['OPERATIONS_ADMIN'],
        'Rina' => ['CASHIER'],
        'Dewi' => ['CASHIER', 'OPERATIONS_ADMIN'],
    ];

    /**
     * The day around which the previews' numbers and dates lie.
     */
    public const string AROUND = '2026-10-17';

    /**
     * Builds the shop on the database of this connection, after the marker
     * check, and returns the marker it was built on.
     *
     * @throws DatabaseRefused on any database whose marker is not test or local
     */
    public static function build(ConnectionInterface $connection): string
    {
        return EnvironmentMarker::require($connection, ['test', 'local']);
    }

    /**
     * The email of a person, under the reserved domain `.test`.
     */
    public static function email(string $person): string
    {
        self::roles($person);

        return strtolower($person).'@contoh.test';
    }

    /**
     * The person's account, created if it does not exist yet, ACTIVE, with a
     * new password that meets AICWDF-COMPAT-8, set through a one-time link;
     * every sign-in session of the account is revoked by the consumption.
     * Runs on the application's default connection, after the marker check.
     *
     * @return array{id: int, email: string, password: string}
     *
     * @throws DatabaseRefused
     */
    public static function person(string $person): array
    {
        self::build(DB::connection());

        $email = self::email($person);

        // The installation first: its Owner, Hendra, comes from OP-01's Action.
        self::shop();

        $account = app(Accounts::class)->findByEmail($email);

        if ($account !== null && ! $account->isActive()) {
            throw new LogicException(sprintf('The fixture account of %s is not ACTIVE.', $person));
        }

        $id = $account === null ? self::createStaff($person, $email, StaffRoles::namesOf(self::roles($person))) : $account->id;
        $password = self::newPassword();
        app(ConsumeLink::class)($id, self::link($person)['token'], $password);

        return ['id' => $id, 'email' => $email, 'password' => $password];
    }

    /**
     * The installation of Toko Contoh (TASK-010; EXECUTION_CONTEXT EXC-45):
     * the recovery epoch, the onboarding state NOT_STARTED and the first
     * BusinessProfile revision "Toko Contoh" with its pointer, whose actor is
     * the Owner Hendra. On a database without any account, epoch or
     * onboarding state it is OP-01's own Action that installs it, with Hendra
     * as the Owner, as the operator would; where the fixtures of earlier
     * Tasks already created accounts or the epoch, the same services write
     * only what is missing. Returns the internal key of the revision in force.
     *
     * @throws DatabaseRefused
     */
    public static function shop(): int
    {
        self::build(DB::connection());

        $profiles = app(BusinessProfiles::class);
        $current = $profiles->current();

        if ($current !== null) {
            return $current->id;
        }

        if (! DB::table('users')->exists() && app(RecoveryEpoch::class)->current() === null && app(OnboardingState::class)->current() === null) {
            $revision = app(BootstrapInstallation::class)(new BootstrapInput('Hendra', self::email('Hendra'), self::SHOP, 'Instalasi Toko Contoh untuk pengujian.'))->revisionId;

            // The fixtures hand over no link: the Owner's first one is revoked, so
            // that an account's only link is the one its test issues.
            DB::transaction(static function (): void {
                $found = app(Accounts::class)->findByEmail(self::email('Hendra'));
                $owner = $found === null ? null : app(Accounts::class)->lockForUpdate($found->id);

                if ($owner instanceof User) {
                    app(OneTimeLinks::class)->revoke($owner);
                }
            });

            return $revision;
        }

        return DB::transaction(static function () use ($profiles): int {
            $accounts = app(Accounts::class);
            $owner = $accounts->findByEmail(self::email('Hendra')) ?? $accounts->create('Hendra', self::email('Hendra'), self::roles('Hendra'));
            $epochs = app(RecoveryEpoch::class);
            $onboarding = app(OnboardingState::class);

            if ($epochs->current() === null) {
                $epochs->issue();
            }

            if ($onboarding->current() === null) {
                $onboarding->insertInitial();
            }

            return $profiles->insertFirst(new ProfileFields(self::SHOP), $owner->id, $owner->display_name);
        });
    }

    /**
     * The installation's recovery epoch as pages receive it (TASK-008),
     * issued through the Commands module's service if absent; the bootstrap
     * of TASK-010 issues it, and shop() installs it with the rest.
     *
     * @throws DatabaseRefused
     */
    public static function recoveryEpoch(): string
    {
        self::build(DB::connection());
        self::shop();
        $epochs = app(RecoveryEpoch::class);

        return $epochs->current() ?? DB::transaction(static fn (): string => $epochs->issue());
    }

    /**
     * A new one-time link for the person's existing account, replacing any
     * earlier one: the account's internal identity and the token.
     *
     * @return array{user: int, token: string}
     *
     * @throws DatabaseRefused
     */
    public static function link(string $person): array
    {
        self::build(DB::connection());

        $email = self::email($person);

        return DB::transaction(static function () use ($email, $person): array {
            $found = app(Accounts::class)->findByEmail($email);
            $account = $found === null ? null : app(Accounts::class)->lockForUpdate($found->id);

            if (! $account instanceof User) {
                throw new LogicException(sprintf('The fixture account of %s does not exist.', $person));
            }

            return ['user' => $account->id, 'token' => app(OneTimeLinks::class)->issue($account)];
        });
    }

    /**
     * Another staff account of the shop, beside the people of KEY_SCREENS section
     * 5, for a test that changes it (TASK-011): created by RC-35's Action, with
     * these roles and, unless asked not to, a password set through a link.
     *
     * @param  list<string>  $roles  the names of RC-35's payload
     * @return array{id: int, email: string, password: string|null}
     *
     * @throws DatabaseRefused
     */
    public static function staff(string $name, array $roles = ['cashier'], bool $withPassword = true): array
    {
        self::build(DB::connection());
        self::shop();

        $email = strtolower($name).'@contoh.test';
        $id = self::createStaff($name, $email, $roles);

        if (! $withPassword) {
            return ['id' => $id, 'email' => $email, 'password' => null];
        }

        $password = self::newPassword();
        app(ConsumeLink::class)($id, self::tokenFor($id), $password);

        return ['id' => $id, 'email' => $email, 'password' => $password];
    }

    /**
     * A new one-time link for an existing account, replacing any earlier one,
     * issued by the broker under the account's lock: its token.
     *
     * @throws DatabaseRefused
     */
    public static function tokenFor(int $id): string
    {
        self::build(DB::connection());

        return DB::transaction(static function () use ($id): string {
            $account = app(Accounts::class)->lockForUpdate($id);

            return $account instanceof User ? app(OneTimeLinks::class)->issue($account) : throw new LogicException('The fixture account exists.');
        });
    }

    /**
     * A random password of 24 characters that meets AICWDF-COMPAT-8: an
     * uppercase and a lowercase letter, a number and a symbol, then random
     * hexadecimal digits.
     */
    public static function newPassword(): string
    {
        return 'Kc7#'.bin2hex(random_bytes(10));
    }

    /**
     * A category of the shop, created by the Owner through RC-21's Action
     * (TASK-015; EXECUTION_CONTEXT EXC-45): its internal key.
     *
     * @throws DatabaseRefused
     */
    public static function category(string $name = 'Alat Tulis'): int
    {
        return self::asOwner(CreateCategory::class, CreateCategory::OPERATION, ['name' => $name]);
    }

    /**
     * A product of the shop, created by the Owner through RC-17's Action
     * (TASK-015; EXECUTION_CONTEXT EXC-45), in its own new category unless one
     * is given: its internal key.
     *
     * @param  array<string, mixed>  $fields  those of RC-17's payload that the test names
     *
     * @throws DatabaseRefused
     */
    public static function product(string $sku, array $fields = []): int
    {
        return self::asOwner(CreateProduct::class, CreateProduct::OPERATION, [
            'sku' => $sku,
            'name' => 'Pulpen Hitam',
            'categoryId' => $fields['categoryId'] ?? self::category(),
            'brand' => null,
            'baseUnit' => 'pcs',
            'quantityPrecision' => 0,
            'sellingPrice' => '7500',
            'minimumStock' => '0',
            ...$fields,
        ]);
    }

    /**
     * A manufacturer barcode of a product, attached by the Owner through RC-27's
     * Action (TASK-016; EXECUTION_CONTEXT EXC-45): its mapping's internal key.
     *
     * @throws DatabaseRefused
     */
    public static function barcode(int $product, string $token, ?string $symbology = null): int
    {
        return self::asOwner(AddBarcode::class, AddBarcode::OPERATION, ['productId' => $product, 'token' => $token, 'symbology' => $symbology]);
    }

    /**
     * Retires a mapping, by the Owner through RC-29's Action (TASK-016).
     *
     * @throws DatabaseRefused
     */
    public static function retireBarcode(int $mapping, string $reasonClass = 'OTHER'): void
    {
        self::asOwner(RetireBarcode::class, RetireBarcode::OPERATION, ['mappingId' => $mapping, 'reasonClass' => $reasonClass]);
    }

    /**
     * A receiving of the shop, confirmed by the Owner through RC-07's Action (TASK-021;
     * EXECUTION_CONTEXT EXC-45), at the versions its products stand at: its document's
     * internal key.
     *
     * @param  list<array{productId: int, quantity: string, cost: array<string, string>}>  $lines
     *
     * @throws DatabaseRefused
     */
    public static function receive(array $lines, ?string $reference = null, ?string $documentDate = null): int
    {
        $versions = [];

        foreach ($lines as $line) {
            $version = DB::table('products')->where('id', $line['productId'])->value('version');
            $versions[$line['productId']] = ['productId' => $line['productId'], 'version' => is_int($version) ? $version : 0];
        }

        return self::asOwner(ConfirmReceiving::class, ConfirmReceiving::OPERATION, ['reference' => $reference, 'documentDate' => $documentDate, 'lines' => $lines], ['products' => array_values($versions)]);
    }

    /**
     * One line of a product received at a KNOWN cost, through RC-07's Action (TASK-021): the
     * document's internal key.
     *
     * @throws DatabaseRefused
     */
    public static function stock(int $product, string $quantity, string $amount = '1000'): int
    {
        return self::receive([['productId' => $product, 'quantity' => $quantity, 'cost' => ['status' => 'KNOWN', 'amount' => $amount]]]);
    }

    /**
     * A manual issue of the shop, confirmed by the Owner through RC-08's Action (TASK-023;
     * EXECUTION_CONTEXT EXC-45), at the versions its products stand at: its internal
     * key.
     *
     * @param  list<array{productId: int, quantity: string}>  $lines
     *
     * @throws DatabaseRefused
     */
    public static function issue(array $lines, string $reasonCode = 'DAMAGED', ?string $note = null): int
    {
        $versions = [];

        foreach ($lines as $line) {
            $version = DB::table('products')->where('id', $line['productId'])->value('version');
            $versions[$line['productId']] = ['productId' => $line['productId'], 'version' => is_int($version) ? $version : 0];
        }

        return self::asOwner(ConfirmManualIssue::class, ConfirmManualIssue::OPERATION, ['reasonCode' => $reasonCode, 'note' => $note, 'lines' => $lines], ['products' => array_values($versions)]);
    }

    /**
     * A stock adjustment of the shop, confirmed by the Owner through RC-09's Action (TASK-024;
     * EXECUTION_CONTEXT EXC-45), at the versions its products stand at: its internal key. The
     * recent authentication of RC-09 is its route's, which this fixture does not pass through.
     *
     * @param  list<array{productId: int, direction: string, quantity: string, cost?: array<string, string>}>  $lines
     *
     * @throws DatabaseRefused
     */
    public static function adjust(array $lines, string $reason = 'Hitung fisik bulanan', string $countReference = 'HIT-01', string $reference = 'REF-01'): int
    {
        $versions = [];

        foreach ($lines as $line) {
            $version = DB::table('products')->where('id', $line['productId'])->value('version');
            $versions[$line['productId']] = ['productId' => $line['productId'], 'version' => is_int($version) ? $version : 0];
        }

        return self::asOwner(ConfirmAdjustment::class, ConfirmAdjustment::OPERATION, ['reason' => $reason, 'countReference' => $countReference, 'reference' => $reference, 'lines' => $lines], ['products' => array_values($versions)]);
    }

    /**
     * The Owner's correction or completion of a cost source through RC-15's Action (TASK-025;
     * EXECUTION_CONTEXT EXC-45), as the latest correction of the source stands: its internal key. The
     * recent authentication of RC-15 is its route's, which this fixture does not pass through.
     *
     * @throws DatabaseRefused
     */
    public static function correctCost(int $evidence, string $amount, string $reason = 'Nota pemasok', ?string $zeroEvidence = null): int
    {
        $latest = DB::table('cost_corrections')->where('evidence_id', $evidence)->max('correction_no');

        return self::asOwner(CorrectCost::class, CorrectCost::OPERATION, ['evidenceId' => $evidence, 'newCost' => ['amount' => $amount, ...($zeroEvidence === null ? [] : ['zeroEvidence' => $zeroEvidence])], 'reason' => $reason], ['correctionNo' => is_int($latest) ? $latest : 0]);
    }

    /**
     * The Owner's reversal of one movement through RC-10's Action (TASK-026; EXECUTION_CONTEXT
     * EXC-45): its internal key. The recent authentication of RC-10 is its route's, which this
     * fixture does not pass through.
     *
     * @throws DatabaseRefused
     */
    public static function reverse(int $movement, string $reason = 'Salah catat'): int
    {
        return self::asOwner(ConfirmReversal::class, ConfirmReversal::OPERATION, ['movementId' => $movement, 'reason' => $reason]);
    }

    /**
     * A cash register of the shop, created by the Owner through RC-33's Action (TASK-027;
     * EXECUTION_CONTEXT EXC-45): its internal key. The recent authentication of RC-33 is its
     * route's, which this fixture does not pass through.
     *
     * @throws DatabaseRefused
     */
    public static function register(string $code = 'LACI-1', string $label = 'Laci 1', bool $active = true): int
    {
        return self::asOwner(CreateCashRegister::class, CreateCashRegister::OPERATION, ['code' => $code, 'label' => $label, 'active' => $active]);
    }

    /**
     * A cash session opened by a person of Toko Contoh as themselves through RC-02's Action (TASK-027;
     * EXECUTION_CONTEXT EXC-45): its internal key.
     *
     * @throws DatabaseRefused
     */
    public static function openSession(string $person, int $register, string $openingCash = '0'): int
    {
        self::build(DB::connection());
        self::shop();

        // The person's account as it is, its password and sign-in sessions kept; created on first use.
        $actor = app(Accounts::class)->findByEmail(self::email($person))->id ?? self::person($person)['id'];
        $envelope = new Envelope(OpenCashSession::OPERATION, Operations::VERSION, (string) Str::uuid(), (string) Str::uuid(), self::recoveryEpoch(), [], ['registerId' => $register, 'openingCash' => $openingCash]);
        $id = app(OpenCashSession::class)->execute(new CommandContext($actor, $envelope, app(Clock::class)->now()))->result['id'] ?? null;

        return is_int($id) ? $id : throw new LogicException('The fixture session was not opened.');
    }

    /**
     * A Sale by a person of Toko Contoh in their own OPEN session through RC-01's Action (TASK-028;
     * EXECUTION_CONTEXT EXC-45), at the price revisions its products stand at, paid by default in
     * CASH with the exact total: its internal key.
     *
     * @param  list<array{productId: int, quantity: string}>  $lines
     * @param  array<string, mixed>  $payment  RC-01's `payment`, when the test names one
     *
     * @throws DatabaseRefused
     */
    public static function sell(string $person, int $session, array $lines, array $payment = []): int
    {
        self::build(DB::connection());
        self::shop();

        // The person's account as it is, its password and sign-in sessions kept; created on first use.
        $actor = app(Accounts::class)->findByEmail(self::email($person))->id ?? self::person($person)['id'];
        $reviewed = [];
        $total = Money::zero();

        foreach ($lines as $line) {
            $values = (array) DB::table('products as p')->join('selling_price_revisions as r', 'r.id', '=', 'p.current_price_revision_id')->where('p.id', $line['productId'])->first(['p.quantity_precision', 'r.id', 'r.amount']);
            $precision = $values['quantity_precision'] ?? null;
            $revision = $values['id'] ?? null;
            $amount = $values['amount'] ?? null;

            if (! is_int($precision) || ! is_int($revision) || (! is_int($amount) && ! is_string($amount))) {
                throw new LogicException('A product of the fixture Sale exists, with its price in force.');
            }

            $reviewed[] = ['priceRevisionId' => $revision];
            $total = $total->plus(Money::lineTotal(Quantity::of($line['quantity'], $precision), Money::fromDatabase($amount)));
        }

        $payload = ['sessionId' => $session, 'lines' => $lines, 'payment' => $payment === [] ? ['method' => 'CASH', 'tendered' => $total->toCanonical()] : $payment];
        $envelope = new Envelope(CheckoutSale::OPERATION, Operations::VERSION, (string) Str::uuid(), (string) Str::uuid(), self::recoveryEpoch(), ['lines' => $reviewed], $payload);
        $id = app(CheckoutSale::class)->execute(new CommandContext($actor, $envelope, app(Clock::class)->now()))->result['id'] ?? null;

        return is_int($id) ? $id : throw new LogicException('The fixture Sale was not committed.');
    }

    /**
     * The SALE_ISSUE of a Sale's first line (TASK-028): its internal key.
     */
    public static function saleIssueOf(int $sale): int
    {
        $movement = DB::table('stock_movements as m')->join('sale_lines as l', 'l.id', '=', 'm.sale_line_id')->where('l.sale_id', $sale)->orderBy('l.line_no')->value('m.id');

        return is_int($movement) ? $movement : throw new LogicException('The Sale posted its SALE_ISSUE.');
    }

    /**
     * The movement of a receiving document's first line, or of a manual issue's (TASK-026): its
     * internal key.
     */
    public static function movementOf(int $document, string $kind = 'RECEIPT'): int
    {
        $movement = $kind === 'RECEIPT'
            ? DB::table('stock_movements as m')->join('receiving_lines as l', 'l.id', '=', 'm.receiving_line_id')->where('l.receiving_document_id', $document)->orderBy('l.line_no')->value('m.id')
            : DB::table('stock_movements as m')->join('manual_issue_lines as l', 'l.id', '=', 'm.manual_issue_line_id')->where('l.manual_issue_id', $document)->orderBy('l.line_no')->value('m.id');

        return is_int($movement) ? $movement : throw new LogicException('The document posted its movement.');
    }

    /**
     * The cost source of a movement, the evidence of an OPENING, a RECEIPT or a positive ADJUSTMENT
     * (TASK-025): its internal key.
     */
    public static function evidenceOf(int $movement): int
    {
        $evidence = DB::table('acquisition_cost_evidence')->where('movement_id', $movement)->value('id');

        return is_int($evidence) ? $evidence : throw new LogicException('The movement carries cost evidence.');
    }

    /**
     * A posting block of a product, through the Inventory module's block service, until the
     * reconciliation of TASK-036 exists (TASK-021 scope item 7; EXECUTION_CONTEXT EXC-45): its
     * internal key.
     *
     * @throws DatabaseRefused
     */
    public static function postingBlock(int $product, string $detailCode = 'BALANCE_MISMATCH'): int
    {
        self::build(DB::connection());

        return PostingBlockScenario::write($product, $detailCode, self::recoveryEpoch());
    }

    /**
     * The Owner's reopening of a block, through the same service, until RC-16 of TASK-036
     * exists (TASK-021 scope item 7).
     *
     * @throws DatabaseRefused
     */
    public static function reopenPostings(int $block, ?string $reason = null): void
    {
        self::build(DB::connection());

        $owner = User::query()->where('role_super_admin', true)->where('status', 'ACTIVE')->firstOrFail();
        $envelope = new Envelope('posting_block.reopen', Operations::VERSION, (string) Str::uuid(), (string) Str::uuid(), self::recoveryEpoch(), [], ['blockId' => $block]);

        DB::transaction(static function () use ($owner, $envelope, $block, $reason): void {
            $identities = app(CommandIdentities::class);
            $fingerprint = Fingerprint::of($envelope->operation, $envelope->version, Fingerprint::user($owner->id), [], $envelope->payload);
            $command = $identities->insertCommand($envelope, $owner->id, $fingerprint, app(Clock::class)->now()->format('Y-m-d H:i:s.uP'));

            app(PostingBlocks::class)->reopen($block, $owner->id, $owner->display_name, $reason, $command);
            $identities->recordResult($command, new StoredResult('posting_block', $block));
        });
    }

    /**
     * The result's key of a command that the Owner runs through its Action.
     *
     * @param  class-string<CommandAction>  $action
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $expected
     */
    private static function asOwner(string $action, string $operation, array $payload, array $expected = []): int
    {
        self::build(DB::connection());
        self::shop();

        $owner = User::query()->where('role_super_admin', true)->where('status', 'ACTIVE')->firstOrFail();
        $envelope = new Envelope($operation, Operations::VERSION, (string) Str::uuid(), (string) Str::uuid(), self::recoveryEpoch(), $expected, $payload);
        $id = app($action)->execute(new CommandContext($owner->id, $envelope, app(Clock::class)->now()))->result['id'] ?? null;

        return is_int($id) ? $id : throw new LogicException(sprintf('The fixture of %s was not created.', $operation));
    }

    /**
     * The staff account of a person, created by the Owner through RC-35's
     * Action (EXECUTION_CONTEXT EXC-45), as the page would, without its link.
     *
     * @param  list<string>  $roles  the names of RC-35's payload
     */
    private static function createStaff(string $person, string $email, array $roles): int
    {
        $owner = User::query()->where('role_super_admin', true)->where('status', 'ACTIVE')->firstOrFail();
        $envelope = new Envelope(
            CreateStaffAccount::OPERATION,
            Operations::VERSION,
            (string) Str::uuid(),
            (string) Str::uuid(),
            self::recoveryEpoch(),
            [],
            ['displayName' => $person, 'email' => $email, 'roles' => $roles],
        );
        $outcome = app(CreateStaffAccount::class)->execute(new CommandContext($owner->id, $envelope, app(Clock::class)->now()));
        $id = $outcome->result['id'] ?? null;

        return is_int($id) ? $id : throw new LogicException(sprintf('The fixture account of %s was not created.', $person));
    }

    /**
     * @return non-empty-list<Role>
     */
    private static function roles(string $person): array
    {
        if (! array_key_exists($person, self::PEOPLE)) {
            throw new InvalidArgumentException(sprintf('%s is not a person of Toko Contoh (KEY_SCREENS section 5).', $person));
        }

        return array_map(static fn (string $role): Role => Role::from($role), self::PEOPLE[$person]);
    }
}
