<?php

namespace App\Domain\Catalog\Barcodes;

use App\Database\SqlState;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use App\Support\Time\Clock;
use Illuminate\Support\Facades\DB;
use LogicException;
use Throwable;

/**
 * The barcode tokens, their mappings and retirements, in the Catalog module (SCHEMA
 * section 5.6; BR-CAT-16 to BR-CAT-22, BR-CAT-52; D-051, D-052), which only this
 * module writes (STK-12). It opens no transaction of its own: the Action that calls
 * it owns the transaction and the locks (TR-TX-01). Its facts are only ever added;
 * only the active row of a mapping is removed, by the retirement that ends it (G-09).
 *
 * A token that exists was mapped when it was registered, so an add is refused
 * whether it is active or retired (BR-CAT-19). Two commands that register one token
 * at the same time meet on the unique index: the second waits, and once the first
 * commits its violation is the refusal DUPLICATE_BARCODE, which ends the transaction
 * (TR-TX-08). A generated code that clashes is drawn again without ending it, because
 * ON CONFLICT raises nothing (BR-CAT-18).
 *
 * The Owner's correction (TASK-020; RC-30) adds a mapping of a token that exists, whose
 * guarantees are the keys of SCHEMA section 5.6: a violation of one is the refusal
 * DUPLICATE_BARCODE.
 */
final class Barcodes
{
    public const string MANUFACTURER = 'MANUFACTURER';

    public const string INTERNAL = 'INTERNAL';

    /** The reason class of a retirement that a correction may follow (BR-CAT-52). */
    public const string MISASSIGNMENT = 'MISASSIGNMENT';

    /** How many codes a generation draws before it gives up; 80 random bits never come near. */
    public const int CODE_ATTEMPTS = 5;

    /** The index that holds a token's uniqueness (SCHEMA section 5.6). */
    private const array TOKEN_INDEX = ['barcode_tokens_token_unique'];

    /** Each retirement is corrected once (SCHEMA section 5.6). */
    private const array CORRECTED_ONCE = ['barcode_mappings_corrects_unique'];

    /** A token is active on at most one product (BR-CAT-16). */
    private const array ACTIVE_ONCE = ['barcode_active_mappings_pkey', 'barcode_active_mappings_mapping_unique'];

    public function __construct(
        private readonly Clock $clock,
        private readonly InternalCodes $codes,
    ) {}

    /**
     * The checks of RC-27 (TECHNICAL_RULES section 7.2): not in the `BK-` namespace;
     * a declared EAN or UPC with a valid check digit; never mapped to any product.
     *
     * @param  string  $field  the path of the request that holds the token
     *
     * @throws CommandRefused DUPLICATE_BARCODE or BARCODE_NOT_ELIGIBLE
     */
    public function refuseIfNotEligible(BarcodeInput $input, string $field): void
    {
        if (BarcodeToken::isReserved($input->token)) {
            throw self::notEligible('reserved', $field);
        }

        if ($input->symbology !== null && ! $input->symbology->accepts($input->token)) {
            throw self::notEligible('check_digit', $field);
        }

        $row = DB::table('barcode_tokens', 't')
            ->leftJoin('barcode_active_mappings as a', 'a.token_id', '=', 't.id')
            ->where('t.token', $input->token)
            ->first(['a.mapping_id']);

        if ($row !== null) {
            throw ((array) $row)['mapping_id'] === null ? self::notEligible('retired', $field) : self::duplicate($field);
        }
    }

    /**
     * Registers a manufacturer token and returns its internal key.
     *
     * @throws CommandRefused DUPLICATE_BARCODE when another command registered it first
     */
    public function insertManufacturerToken(BarcodeInput $input, string $field): int
    {
        self::requireTransaction();

        try {
            return DB::table('barcode_tokens')->insertGetId([
                'token' => $input->token,
                'kind' => self::MANUFACTURER,
                'symbology' => $input->symbology?->value,
                'created_at' => $this->now(),
            ]);
        } catch (Throwable $failure) {
            throw SqlState::uniqueOn($failure, self::TOKEN_INDEX) ? self::duplicate($field) : $failure;
        }
    }

    /**
     * Registers a new internal code, drawn again on a clash (BR-CAT-18).
     *
     * @return array{id: int, code: string}
     *
     * @throws CommandRefused SERVICE_BUSY when no code that it drew was free
     */
    public function insertInternalToken(): array
    {
        self::requireTransaction();

        for ($attempt = 1; $attempt <= self::CODE_ATTEMPTS; $attempt++) {
            $code = $this->codes->next();
            $row = (array) DB::selectOne("insert into barcode_tokens (token, kind, created_at) values (?, 'INTERNAL', ?) on conflict (token) do nothing returning id", [$code, $this->now()]);
            $id = $row['id'] ?? null;

            if (is_int($id)) {
                return ['id' => $id, 'code' => $code];
            }
        }

        throw CommandRefused::of(ErrorCode::ServiceBusy);
    }

    /**
     * Adds the one ordinary mapping of a token to the product whose lock the caller
     * holds, with its actor's name frozen (SCHEMA section 3.5).
     *
     * @param  self::MANUFACTURER|self::INTERNAL  $kind
     */
    public function insertMapping(int $tokenId, string $kind, int $productId, int $actorUserId, string $actorDisplayName, int $commandId): int
    {
        self::requireTransaction();

        return DB::table('barcode_mappings')->insertGetId([
            'token_id' => $tokenId,
            'token_kind' => $kind,
            'product_id' => $productId,
            'attach_kind' => 'ORIGINAL',
            'attached_at' => $this->now(),
            'actor_user_id' => $actorUserId,
            'actor_display_name' => $actorDisplayName,
            'command_id' => $commandId,
        ]);
    }

    /**
     * Makes a mapping the active one of its token (BR-CAT-16).
     */
    public function activate(int $tokenId, int $mappingId, int $productId): void
    {
        self::requireTransaction();

        DB::table('barcode_active_mappings')->insert(['token_id' => $tokenId, 'mapping_id' => $mappingId, 'product_id' => $productId]);
    }

    /**
     * The mapping as committed, without a lock, or null: what a retirement reads to
     * choose its product's lock. A mapping never moves to another product.
     */
    public function mapping(int $id): ?MappingRecord
    {
        $row = DB::table('barcode_mappings', 'm')
            ->join('barcode_tokens as t', 't.id', '=', 'm.token_id')
            ->where('m.id', $id)
            ->first(['m.id', 'm.token_id', 'm.product_id', 't.token']);

        if ($row === null) {
            return null;
        }

        $values = (array) $row;
        $mapping = $values['id'] ?? null;
        $token = $values['token_id'] ?? null;
        $product = $values['product_id'] ?? null;
        $text = $values['token'] ?? null;

        if (! is_int($mapping) || ! is_int($token) || ! is_int($product) || ! is_string($text)) {
            throw new LogicException('A barcode mapping holds its columns (SCHEMA section 5.6).');
        }

        return new MappingRecord($mapping, $token, $text, $product);
    }

    /**
     * The last mapping of the token as committed, or null when no such token exists:
     * read before the locks to choose them, and again under them (RC-30). The token is
     * compared exactly (BR-CAT-17).
     */
    public function currentMapping(string $token): ?CurrentMapping
    {
        $row = DB::table('barcode_tokens', 't')
            ->join('barcode_mappings as m', 'm.token_id', '=', 't.id')
            ->leftJoin('barcode_active_mappings as a', 'a.mapping_id', '=', 'm.id')
            ->leftJoin('barcode_mapping_retirements as x', 'x.mapping_id', '=', 'm.id')
            ->where('t.token', $token)
            ->orderByDesc('m.id')
            ->first(['t.id as token_id', 't.kind', 'm.id as mapping_id', 'm.product_id', 'a.mapping_id as active_mapping', 'x.reason_class']);

        if ($row === null) {
            return null;
        }

        $values = (array) $row;
        $tokenId = $values['token_id'] ?? null;
        $kind = $values['kind'] ?? null;
        $mapping = $values['mapping_id'] ?? null;
        $product = $values['product_id'] ?? null;
        $reason = $values['reason_class'] ?? null;

        if (! is_int($tokenId) || ! is_string($kind) || ! is_int($mapping) || ! is_int($product)) {
            throw new LogicException('A barcode mapping holds its columns (SCHEMA section 5.6).');
        }

        return new CurrentMapping($tokenId, $kind, $mapping, $product, ($values['active_mapping'] ?? null) !== null, is_string($reason) ? $reason : null);
    }

    /**
     * Adds a correction of a mapping retired as a misassignment, to the product whose lock
     * the caller holds, with its reason and its actor's name frozen (D-051, D-052). The
     * keys of SCHEMA section 5.6 hold that the token is a manufacturer's and that the
     * mapping is of this token, retired so, and corrected once.
     *
     * @param  string  $field  the path of the request that holds the token
     *
     * @throws CommandRefused DUPLICATE_BARCODE when the retirement was already corrected
     */
    public function insertCorrection(int $tokenId, int $correctedMappingId, int $productId, string $reason, int $actorUserId, string $actorDisplayName, int $commandId, string $field): int
    {
        self::requireTransaction();

        try {
            return DB::table('barcode_mappings')->insertGetId([
                'token_id' => $tokenId,
                'token_kind' => self::MANUFACTURER,
                'product_id' => $productId,
                'attach_kind' => 'MISASSIGNMENT_CORRECTION',
                'corrects_mapping_id' => $correctedMappingId,
                'corrected_reason_class' => self::MISASSIGNMENT,
                'reason' => $reason,
                'attached_at' => $this->now(),
                'actor_user_id' => $actorUserId,
                'actor_display_name' => $actorDisplayName,
                'command_id' => $commandId,
            ]);
        } catch (Throwable $failure) {
            throw SqlState::uniqueOn($failure, self::CORRECTED_ONCE) ? self::duplicate($field) : $failure;
        }
    }

    /**
     * Makes a correction the active mapping of its token, whose primary key leaves at most
     * one after the commit (BR-CAT-16; G-09).
     *
     * @param  string  $field  the path of the request that holds the token
     *
     * @throws CommandRefused DUPLICATE_BARCODE when the token is active already
     */
    public function activateCorrection(int $tokenId, int $mappingId, int $productId, string $field): void
    {
        try {
            $this->activate($tokenId, $mappingId, $productId);
        } catch (Throwable $failure) {
            throw SqlState::uniqueOn($failure, self::ACTIVE_ONCE) ? self::duplicate($field) : $failure;
        }
    }

    /**
     * The refusal of a token that RC-30 cannot correct (COPY_DECK section 13.2).
     */
    public static function notCorrectable(string $field): CommandRefused
    {
        return self::notEligible('not_correctable', $field);
    }

    /**
     * Whether the mapping is the active one of its token, read under its product's
     * lock (G-09).
     */
    public function isActive(int $mappingId): bool
    {
        return DB::table('barcode_active_mappings')->where('mapping_id', $mappingId)->exists();
    }

    /**
     * Records the retirement of a mapping with its reason class (BR-CAT-52). The
     * record is history and is never rewritten.
     *
     * @param  'MISASSIGNMENT'|'OTHER'  $reasonClass
     */
    public function insertRetirement(int $mappingId, string $reasonClass, int $actorUserId, string $actorDisplayName, int $commandId): void
    {
        self::requireTransaction();

        DB::table('barcode_mapping_retirements')->insert([
            'mapping_id' => $mappingId,
            'reason_class' => $reasonClass,
            'retired_at' => $this->now(),
            'actor_user_id' => $actorUserId,
            'actor_display_name' => $actorDisplayName,
            'command_id' => $commandId,
        ]);
    }

    /**
     * Removes the active row of a retired mapping, in the transaction of its
     * retirement (G-09).
     */
    public function deactivate(int $mappingId): void
    {
        self::requireTransaction();

        if (DB::table('barcode_active_mappings')->where('mapping_id', $mappingId)->delete() !== 1) {
            throw new LogicException('A mapping retired under its product\'s lock was active.');
        }
    }

    /**
     * @param  'retired'|'reserved'|'check_digit'|'not_correctable'  $reason  a reason of COPY_DECK section 13.2
     */
    private static function notEligible(string $reason, string $field): CommandRefused
    {
        return new CommandRefused(ErrorCode::BarcodeNotEligible, params: ['reason' => $reason], fields: [$field => [['key' => ErrorCode::BarcodeNotEligible->messageKey().'.'.$reason, 'params' => []]]]);
    }

    private static function duplicate(string $field): CommandRefused
    {
        return new CommandRefused(ErrorCode::DuplicateBarcode, fields: [$field => [['key' => ErrorCode::DuplicateBarcode->messageKey(), 'params' => []]]]);
    }

    private function now(): string
    {
        return $this->clock->now()->format('Y-m-d H:i:s.uP');
    }

    private static function requireTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('A barcode is written inside the transaction of its Action (TR-TX-01).');
        }
    }
}
