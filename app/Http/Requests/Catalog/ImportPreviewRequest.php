<?php

namespace App\Http\Requests\Catalog;

use App\Http\Requests\Commands\RefusingRequest;
use Illuminate\Http\UploadedFile;
use LogicException;

/**
 * The request of RR-01 (ROUTE_CONTRACTS section 5; INTERACTION_CONTRACTS section 6):
 * `multipart/form-data` with `file` and nothing else. The file's name and declared type
 * are never read (SEC-UP-01), and its size and content are the Action's, which refuses
 * them as CSV_INVALID. A file above PHP's limit arrives as an upload that failed.
 */
final class ImportPreviewRequest extends RefusingRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['file' => ['required', 'file']];
    }

    /**
     * @return list<string>
     */
    protected function allowed(): array
    {
        return ['file'];
    }

    /**
     * @return array<string, array{0: string, 1?: array<string, string|int>}>
     */
    protected function fieldKeys(): array
    {
        return [
            'file.required' => ['ux.validation.upload.unreadable'],
            'file.file' => ['ux.validation.upload.unreadable'],
        ];
    }

    /**
     * The validated upload.
     */
    public function upload(): UploadedFile
    {
        $upload = $this->validatedInput()['file'] ?? null;

        return $upload instanceof UploadedFile ? $upload : throw new LogicException('The upload was validated before it is read.');
    }
}
