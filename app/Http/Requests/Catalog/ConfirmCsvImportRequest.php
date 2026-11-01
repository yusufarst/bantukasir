<?php

namespace App\Http\Requests\Catalog;

use App\Actions\Catalog\ConfirmCsvImport;

/**
 * The FormRequest of RC-31 `csv_import.confirm` (INTERACTION_CONTRACTS RC-31): `importId`
 * and `fileSha256` as RR-01 gave them, each 64 lower-case hexadecimal digits. Whether the
 * import exists and holds that file is the Action's, after the identity, so that a repeat
 * of a committed import answers its original.
 */
final class ConfirmCsvImportRequest extends CatalogCommandRequest
{
    private const string HEX_256_BITS = 'regex:/^[0-9a-f]{64}$/D';

    public function operation(): string
    {
        return ConfirmCsvImport::OPERATION;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payloadRules(): array
    {
        return [
            'importId' => ['required', 'string', self::HEX_256_BITS],
            'fileSha256' => ['required', 'string', self::HEX_256_BITS],
        ];
    }
}
