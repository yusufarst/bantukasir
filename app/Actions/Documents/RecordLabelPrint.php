<?php

namespace App\Actions\Documents;

use App\Actions\Commands\CommandAction;
use App\Actions\Commands\CommandContext;
use App\Actions\Commands\CommandServices;
use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Domain\Audit\Actor;
use App\Domain\Audit\AuditCategory;
use App\Domain\Catalog\Barcodes\Barcodes;
use App\Domain\Catalog\Products;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use App\Domain\Commands\StoredResult;
use App\Domain\Documents\Labels\LabelPrints;
use App\Models\User;
use LogicException;

/**
 * RC-47 `document.print_labels` (INTERACTION_CONTRACTS, "RC-44 to RC-47"; TECHNICAL_RULES
 * section 7.2, "Print, reprint or export request"; BR-CAT-35, BR-CAT-36, BR-AUD-06; DC-05;
 * CAP-08): `count` labels, 1 to 100, of a mapping that must be active on the product,
 * otherwise STALE_VERSION. It takes no lock (steps 1, 2.1 and 8): a retirement that
 * commits meanwhile is seen by the next request, and the labels are the code as it was
 * when they were asked for. It writes only its audit event PRINT and its result, and no
 * business fact; the audit event holds the code and the count that RP-306P prints. The
 * print request is the command itself, so a repeat with the same identity answers the
 * same one.
 *
 * Write steps (TEST_STRATEGY TS-21): the audit event, the result.
 */
final class RecordLabelPrint extends CommandAction
{
    public const string OPERATION = 'document.print_labels';

    /** The result's type (SCHEMA section 5.3). */
    private const string RESULT = 'label_print';

    public function __construct(
        CommandServices $services,
        private readonly Products $products,
        private readonly Barcodes $barcodes,
    ) {
        parent::__construct($services);
    }

    public function operation(): string
    {
        return self::OPERATION;
    }

    public function capabilities(): array
    {
        return [Capability::Cap08];
    }

    protected function perform(CommandContext $context, User $actor, int $commandId): StoredResult
    {
        $payload = $context->envelope->payload;
        $productId = $payload['productId'] ?? null;
        $mappingId = $payload['mappingId'] ?? null;
        $count = $payload['count'] ?? null;

        if (! is_int($productId) || ! is_int($mappingId) || ! is_int($count)) {
            throw new LogicException('The whole numbers of the payload were validated before the Action.');
        }

        $product = $this->products->read($productId) ?? throw CommandRefused::of(ErrorCode::NotFound);
        $mapping = $this->barcodes->mapping($mappingId) ?? throw CommandRefused::of(ErrorCode::NotFound);

        if ($mapping->productId !== $product->id || ! $this->barcodes->isActive($mapping->id)) {
            throw CommandRefused::of(ErrorCode::StaleVersion);
        }

        $this->services->audit->record(
            AuditCategory::Business,
            LabelPrints::AUDIT_ACTION,
            Actor::user($actor->id, $actor->display_name),
            ['barcode' => $mapping->token, 'mappingId' => $mapping->id, 'count' => $count],
            subject: ['type' => LabelPrints::AUDIT_SUBJECT, 'id' => $mapping->id],
            commandId: $commandId,
            productId: $product->id,
        );
        $this->written();

        return new StoredResult(self::RESULT, $commandId, ['productId' => $product->id]);
    }

    public function present(User $viewer, int $commandId, StoredResult $result): ?array
    {
        if (! Capabilities::allows($viewer, Capability::Cap08)) {
            return null;
        }

        $product = $result->detail['productId'] ?? null;

        if (! is_int($product)) {
            throw new LogicException('A print request stored its product.');
        }

        return ['type' => $result->type, 'id' => $result->ref, 'location' => LabelPrints::path($product, $commandId)];
    }
}
