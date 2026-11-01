<?php

namespace App\Http\Requests\Catalog;

use App\Actions\Catalog\SetProductPhoto;

/**
 * The FormRequest of RC-25 `product_photo.set` (INTERACTION_CONTRACTS section 1.1 and
 * RC-25; ROUTE_CONTRACTS RT-12): multipart, the envelope travelling as form fields
 * beside the file, so its version, the product and the expected version arrive as texts
 * and are read as the whole numbers they stand for, only in their canonical form,
 * before validation. A JSON body has no file and is refused at `payload.photo`. The
 * file's name and declared type are never read (SEC-UP-01); its content is judged by
 * the Action before the transaction, and its SHA-256 stands for it in the fingerprint.
 */
final class SetProductPhotoRequest extends CatalogCommandRequest
{
    /** The photo's limit of RT-12, in kibibytes, as the file rule reads it. */
    private const int PHOTO_KIB = 8 * 1024;

    public function operation(): string
    {
        return SetProductPhoto::OPERATION;
    }

    protected function prepareForValidation(): void
    {
        if ($this->isJson()) {
            return;
        }

        $replaced = ['version' => self::whole($this->input('version'))];
        $expected = $this->input('expected');
        $payload = $this->input('payload');

        if (is_array($expected) && array_key_exists('productVersion', $expected)) {
            $replaced['expected'] = [...$expected, 'productVersion' => self::whole($expected['productVersion'])];
        }

        if (is_array($payload) && array_key_exists('productId', $payload)) {
            $replaced['payload'] = [...$payload, 'productId' => self::whole($payload['productId'])];
        }

        $this->merge($replaced);
    }

    /**
     * @return array<string, mixed>
     */
    protected function payloadRules(): array
    {
        return [
            ...self::productTargetRules(),
            'photo' => ['required', 'file', 'max:'.self::PHOTO_KIB],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function expectedRules(): array
    {
        return ['productVersion' => ['required', 'integer', 'min:1']];
    }

    /**
     * @return array<string, array{0: string, 1?: array<string, string|int>}>
     */
    protected function fieldKeys(): array
    {
        return [
            ...parent::fieldKeys(),
            'payload.photo.required' => ['ux.validation.upload.unreadable'],
            'payload.photo.file' => ['ux.validation.upload.unreadable'],
            'payload.photo.max' => ['ux.validation.upload.too_large', ['ukuran' => '8 MB']],
        ];
    }

    /**
     * A form field that stands for a whole number, read as one; anything else stays
     * as it came, for the rules to refuse.
     */
    private static function whole(mixed $value): mixed
    {
        return is_string($value) && preg_match('/^(?:0|[1-9][0-9]{0,17})$/D', $value) === 1 ? (int) $value : $value;
    }
}
