<?php

namespace App\Actions\Catalog;

use App\Actions\Commands\CommandContext;
use App\Actions\Commands\CommandServices;
use App\Domain\Catalog\Barcodes\Barcodes;
use App\Domain\Catalog\Categories;
use App\Domain\Catalog\Products;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use App\Domain\Commands\StoredResult;
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
 * RC-25 `product_photo.set` (INTERACTION_CONTRACTS RC-25; TASK-018 scope item 1).
 * Before the transaction the upload is judged by its content with the limits of a
 * photo, decoded, turned upright, reduced to at most 2,048 pixels, re-encoded without
 * metadata with its thumbnail, and both written under new random keys (TR-TX-05;
 * SECURITY_MODEL section 13): a refused image is VALIDATION_FAILED at `payload.photo`
 * with its reason, before any fact and any file. Then, in the transaction, the
 * product's update lock at the version the user reviewed, the two records, the product
 * pointed at the new photo and the audit event PHOTO_CHANGE. A rolled-back command, and
 * a repeat, leave only unreferenced files, which TASK-038 removes and which have no
 * record for RD-07 to serve. Guard G-20: a product references a file of purpose
 * PRODUCT_PHOTO.
 *
 * Write steps (TEST_STRATEGY TS-21): the photo's record, the thumbnail's record, the
 * product, the audit event, the result.
 */
final class SetProductPhoto extends ProductPhotoAction
{
    public const string OPERATION = 'product_photo.set';

    private ?StoredBlob $photo = null;

    private ?StoredBlob $thumbnail = null;

    public function __construct(
        CommandServices $services,
        Categories $categories,
        Products $products,
        Barcodes $barcodes,
        private readonly StoredFiles $files,
        private readonly ImageInspector $inspector,
        private readonly ImageNormalizer $normalizer,
        private readonly MediaStore $store,
    ) {
        parent::__construct($services, $categories, $products, $barcodes);
    }

    public function operation(): string
    {
        return self::OPERATION;
    }

    protected function beforeTransaction(CommandContext $context): void
    {
        $upload = $context->envelope->payload['photo'] ?? null;

        if (! $upload instanceof SplFileInfo) {
            throw new LogicException('A photo arrives as a validated upload (SetProductPhotoRequest).');
        }

        $limits = ImageLimits::photo();

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

            $normalized = $this->normalizer->normalizePhoto($bytes, $this->inspector->inspect($bytes, $limits));
        } catch (ImageRefused $refusal) {
            throw new CommandRefused(ErrorCode::ValidationFailed, fields: [
                'payload.photo' => [['key' => $refusal->problem->messageKey(), 'params' => $limits->params($refusal->problem)]],
            ]);
        }

        $this->photo = $this->store->store($normalized->photo);
        $this->thumbnail = $this->store->store($normalized->thumbnail);
    }

    protected function perform(CommandContext $context, User $actor, int $commandId): StoredResult
    {
        if ($this->photo === null || $this->thumbnail === null) {
            throw new LogicException('A photo is stored before the transaction.');
        }

        $product = $this->productToChange($context->envelope->payload, $context->envelope->expected);

        $photo = $this->files->insert($this->photo, StoredFiles::PRODUCT_PHOTO, $actor->id, $commandId);
        $this->written();

        $thumbnail = $this->files->insertThumbnail($this->thumbnail, $photo, $actor->id, $commandId);
        $this->written();

        // G-20: a product references a file of purpose PRODUCT_PHOTO.
        if ($this->files->purposeOf($photo) !== StoredFiles::PRODUCT_PHOTO) {
            throw new LogicException('A product references a product photo only (G-20).');
        }

        $this->products->setPhoto($product, $photo);
        $this->written();

        $this->audit('PHOTO_CHANGE', $product, ['fileId' => $photo, 'thumbnailFileId' => $thumbnail], $actor, $commandId);

        return new StoredResult(self::PRODUCT, $product->id);
    }
}
