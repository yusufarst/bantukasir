<?php

namespace App\Actions\Configuration;

use App\Actions\Commands\CommandAction;
use App\Actions\Commands\CommandContext;
use App\Actions\Commands\CommandServices;
use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Domain\Audit\Actor;
use App\Domain\Audit\AuditCategory;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use App\Domain\Commands\StoredResult;
use App\Domain\Configuration\BusinessProfiles;
use App\Domain\Configuration\ProfileFields;
use App\Domain\Media\ImageInspector;
use App\Domain\Media\ImageLimits;
use App\Domain\Media\ImageNormalizer;
use App\Domain\Media\ImageProblem;
use App\Domain\Media\ImageRefused;
use App\Domain\Media\MediaStore;
use App\Domain\Media\StoredBlob;
use App\Domain\Media\StoredFiles;
use App\Models\User;
use LogicException;
use SplFileInfo;

/**
 * RC-32 `business_profile.change` (INTERACTION_CONTRACTS RC-32; ROUTE_CONTRACTS
 * section 6; TECHNICAL_RULES section 7.2, "BusinessProfile change"; DOMAIN_MODEL
 * BR-CFG-01 to BR-CFG-04; TASK-010 scope item 4). The Owner (CAP-30), with
 * recent authentication on the route.
 *
 * Before the transaction, a new logo (`logoAction` replace) is judged by its
 * content, decoded, turned upright, re-encoded without metadata and written
 * under a new random key (TR-TX-05; SECURITY_MODEL section 13): a refused
 * image is VALIDATION_FAILED at `payload.logo` with its reason, before any
 * fact. Then, in the transaction after the identity, the epoch and the actor
 * guard: step 2.4, the update lock of the current pointer; the expected
 * revision must be the one in force, otherwise STALE_VERSION (TR-VAL-08);
 * then step 8: the new logo's record, the new immutable revision — keeping
 * the logo in force for `keep`, none for `remove`, the new one for `replace`;
 * a referenced logo file is never deleted (BR-CFG-03) — the pointer moved to
 * it and the audit event PROFILE_CHANGE with the names of the changed fields.
 * Guard G-20: the logo a revision references is a file of purpose LOGO.
 *
 * Write steps (TEST_STRATEGY TS-21): 1 the logo's record (replace only), then
 * the revision, the pointer and the audit event, then the result.
 */
final class ChangeBusinessProfile extends CommandAction
{
    public const string OPERATION = 'business_profile.change';

    /** The logo actions of RC-32. */
    public const array LOGO_ACTIONS = ['keep', 'replace', 'remove'];

    /** The page of the result (ROUTE_CONTRACTS RP-607). */
    public const string LOCATION = '/settings/business-profile';

    private ?StoredBlob $logo = null;

    public function __construct(
        CommandServices $services,
        private readonly BusinessProfiles $profiles,
        private readonly StoredFiles $files,
        private readonly ImageInspector $inspector,
        private readonly ImageNormalizer $normalizer,
        private readonly MediaStore $store,
    ) {
        parent::__construct($services);
    }

    public function operation(): string
    {
        return self::OPERATION;
    }

    public function capabilities(): array
    {
        return [Capability::Cap30];
    }

    protected function beforeTransaction(CommandContext $context): void
    {
        $upload = $context->envelope->payload['logo'] ?? null;

        if (($context->envelope->payload['logoAction'] ?? null) !== 'replace') {
            return;
        }

        if (! $upload instanceof SplFileInfo) {
            throw new LogicException('A replaced logo arrives as a validated upload (ChangeBusinessProfileRequest).');
        }

        $limits = ImageLimits::logo();

        try {
            $path = $upload->getRealPath();
            $size = $upload->getSize();

            if ($path === false || $size === false) {
                throw new ImageRefused(ImageProblem::Unreadable);
            }

            if ($size > $limits->maxBytes) {
                throw new ImageRefused(ImageProblem::TooLarge);
            }

            $bytes = file_get_contents($path);

            if (! is_string($bytes)) {
                throw new ImageRefused(ImageProblem::Unreadable);
            }

            $image = $this->normalizer->normalize($bytes, $this->inspector->inspect($bytes, $limits));
        } catch (ImageRefused $refusal) {
            throw new CommandRefused(ErrorCode::ValidationFailed, fields: [
                'payload.logo' => [['key' => $refusal->problem->messageKey(), 'params' => $limits->params($refusal->problem)]],
            ]);
        }

        $this->logo = $this->store->store($image);
    }

    protected function perform(CommandContext $context, User $actor, int $commandId): StoredResult
    {
        $payload = $context->envelope->payload;
        $expected = $context->envelope->expected['revisionId'] ?? null;

        // Step 2.4: the current pointer, under its update lock.
        $current = $this->profiles->lockCurrent();
        $this->services->points->afterLock('2.4');

        if ($current === null || $current !== $expected) {
            throw CommandRefused::of(ErrorCode::StaleVersion);
        }

        $previous = $this->profiles->revision($current) ?? throw new LogicException('The revision in force exists.');
        $fields = new ProfileFields(
            shopName: self::text($payload['shopName'] ?? null) ?? '',
            address: self::text($payload['address'] ?? null),
            contact: self::text($payload['contact'] ?? null),
            email: self::text($payload['email'] ?? null),
            documentHeader: self::text($payload['documentHeader'] ?? null),
            documentFooter: self::text($payload['documentFooter'] ?? null),
            documentIdentity: self::text($payload['documentIdentity'] ?? null),
            accentToken: self::text($payload['accentToken'] ?? null),
        );
        $logoAction = $payload['logoAction'] ?? null;

        $logoFileId = match ($logoAction) {
            'keep' => $previous->logoFileId,
            'remove' => null,
            'replace' => $this->recordLogo($actor, $commandId),
            default => throw new LogicException('The logo action is one of RC-32.'),
        };

        // G-20: the logo of a revision is a file of purpose LOGO.
        if ($logoFileId !== null && $this->files->purposeOf($logoFileId) !== StoredFiles::LOGO) {
            throw new LogicException('A BusinessProfile revision references a logo only (G-20).');
        }

        $revisionId = $this->profiles->insertRevision($fields, $logoFileId, $actor->id, $actor->display_name, $commandId);
        $this->written();

        $this->profiles->movePointer($revisionId);
        $this->written();

        $changed = $fields->changedFrom($previous->fields);

        if ($logoFileId !== $previous->logoFileId) {
            $changed[] = 'logo';
        }

        $this->services->audit->record(
            AuditCategory::Security,
            'PROFILE_CHANGE',
            Actor::user($actor->id, $actor->display_name),
            ['revisionId' => $revisionId, 'previousRevisionId' => $previous->id, 'changed' => $changed],
            subject: ['type' => 'business_profile_revision', 'id' => $revisionId],
            commandId: $commandId,
        );
        $this->written();

        return new StoredResult('business_profile_revision', $revisionId, ['revisionId' => $revisionId]);
    }

    public function present(User $viewer, int $commandId, StoredResult $result): ?array
    {
        if (! Capabilities::allows($viewer, Capability::Cap30)) {
            return null;
        }

        return ['type' => $result->type, 'id' => $result->ref, 'location' => self::LOCATION];
    }

    /**
     * The record of the logo written before the transaction (TR-TX-05).
     */
    private function recordLogo(User $actor, int $commandId): int
    {
        if ($this->logo === null) {
            throw new LogicException('A replaced logo is stored before the transaction.');
        }

        $id = $this->files->insert($this->logo, StoredFiles::LOGO, $actor->id, $commandId);
        $this->written();

        return $id;
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
