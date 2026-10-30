<?php

namespace App\Domain\Configuration;

use App\Support\Time\Clock;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The BusinessProfile service of the Configuration module (DOMAIN_MODEL
 * section 10; SCHEMA section 5.2): the immutable revisions and the one
 * current pointer, which only this module writes (STK-12). It opens no
 * transaction of its own: the Action that calls it owns the transaction and
 * the locks (TR-TX-01). A revision is only ever added, never changed or
 * deleted (BR-CFG-02, D-042); the pointer moves to the newest revision under
 * its update lock (TR §7.1 step 2.4, SCHEMA section 7), the guard that a
 * command freezing the identity in a snapshot takes as a share lock
 * (RACE-25).
 */
final class BusinessProfiles
{
    /** The time of a revision, as the pages receive every instant: UTC with microseconds. */
    private const string CREATED_AT = 'to_char(r.created_at at time zone \'UTC\', \'YYYY-MM-DD"T"HH24:MI:SS.US"Z"\') as created_at';

    public function __construct(private readonly Clock $clock) {}

    /**
     * The first revision and the pointer to it, written by the bootstrap
     * procedure in its transaction (SEC-OP-01; guard G-23): the new Owner is
     * its actor, and it has no command (TR-CMD-07). The database refuses a
     * second pointer row.
     */
    public function insertFirst(ProfileFields $fields, int $ownerId, string $ownerDisplayName): int
    {
        self::requireTransaction();

        $revisionId = $this->insertRevision($fields, null, $ownerId, $ownerDisplayName, null);
        DB::table('business_profile_current')->insert(['id' => 1, 'revision_id' => $revisionId, 'changed_at' => $this->now()]);

        return $revisionId;
    }

    /**
     * The current pointer's revision, under the pointer's update lock (step
     * 2.4: FOR NO KEY UPDATE), or null before the bootstrap.
     */
    public function lockCurrent(): ?int
    {
        self::requireTransaction();

        $revision = DB::table('business_profile_current')->where('id', 1)->lock('for no key update')->value('revision_id');

        return is_int($revision) ? $revision : null;
    }

    /**
     * Adds an immutable revision and returns its internal key.
     */
    public function insertRevision(ProfileFields $fields, ?int $logoFileId, int $actorUserId, string $actorDisplayName, ?int $commandId): int
    {
        self::requireTransaction();

        return DB::table('business_profile_revisions')->insertGetId([
            'shop_name' => $fields->shopName,
            'address' => $fields->address,
            'contact' => $fields->contact,
            'email' => $fields->email,
            'document_header' => $fields->documentHeader,
            'document_footer' => $fields->documentFooter,
            'document_identity' => $fields->documentIdentity,
            'accent_token' => $fields->accentToken,
            'logo_file_id' => $logoFileId,
            'created_at' => $this->now(),
            'actor_user_id' => $actorUserId,
            'actor_display_name' => $actorDisplayName,
            'command_id' => $commandId,
        ]);
    }

    /**
     * Moves the pointer, whose update lock the caller holds, to a revision.
     */
    public function movePointer(int $revisionId): void
    {
        self::requireTransaction();

        $moved = DB::table('business_profile_current')->where('id', 1)->update(['revision_id' => $revisionId, 'changed_at' => $this->now()]);

        if ($moved !== 1) {
            throw new LogicException('The BusinessProfile pointer exists from the bootstrap on (G-23).');
        }
    }

    /**
     * The revision in force, read without a lock, or null before the
     * bootstrap.
     */
    public function current(): ?ProfileRevision
    {
        $row = self::revisions()->whereRaw('r.id = (select c.revision_id from business_profile_current c where c.id = 1)')->first();

        return $row === null ? null : self::revisionOf($row);
    }

    /**
     * A revision by its internal key, or null.
     */
    public function revision(int $id): ?ProfileRevision
    {
        $row = self::revisions()->where('r.id', $id)->first();

        return $row === null ? null : self::revisionOf($row);
    }

    /**
     * The revisions before the one in force, the newest first, at most
     * $limit of them (TECHNICAL_RULES section 12: no unbounded list).
     *
     * @return list<ProfileRevision>
     */
    public function earlier(int $limit): array
    {
        $rows = self::revisions()
            ->whereRaw('r.id <> coalesce((select c.revision_id from business_profile_current c where c.id = 1), 0)')
            ->orderByDesc('r.id')
            ->limit($limit)
            ->get()
            ->all();

        return array_values(array_map(self::revisionOf(...), $rows));
    }

    private static function revisions(): Builder
    {
        return DB::table('business_profile_revisions', 'r')->selectRaw(
            'r.id, (select count(*) from business_profile_revisions n where n.id <= r.id) as number, r.shop_name, r.address, r.contact, r.email, '
            .'r.document_header, r.document_footer, r.document_identity, r.accent_token, r.logo_file_id, '
            .self::CREATED_AT.', r.actor_display_name'
        );
    }

    private static function revisionOf(object $row): ProfileRevision
    {
        $values = (array) $row;

        return new ProfileRevision(
            id: self::integer($values['id'] ?? null),
            number: self::integer($values['number'] ?? null),
            fields: new ProfileFields(
                shopName: self::text($values['shop_name'] ?? null) ?? '',
                address: self::text($values['address'] ?? null),
                contact: self::text($values['contact'] ?? null),
                email: self::text($values['email'] ?? null),
                documentHeader: self::text($values['document_header'] ?? null),
                documentFooter: self::text($values['document_footer'] ?? null),
                documentIdentity: self::text($values['document_identity'] ?? null),
                accentToken: self::text($values['accent_token'] ?? null),
            ),
            logoFileId: is_int($values['logo_file_id'] ?? null) ? $values['logo_file_id'] : null,
            createdAt: self::text($values['created_at'] ?? null) ?? '',
            actorDisplayName: self::text($values['actor_display_name'] ?? null) ?? '',
        );
    }

    private static function integer(mixed $value): int
    {
        if (! is_int($value)) {
            throw new LogicException('A key or count of a revision is a whole number.');
        }

        return $value;
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    private function now(): string
    {
        return $this->clock->now()->format('Y-m-d H:i:s.uP');
    }

    private static function requireTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('The BusinessProfile is changed inside the transaction of the Action that changes it (TR-TX-01).');
        }
    }
}
