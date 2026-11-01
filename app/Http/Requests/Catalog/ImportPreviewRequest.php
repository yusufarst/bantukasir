<?php

namespace App\Http\Requests\Catalog;

use App\Domain\Catalog\Import\CsvDialect;
use App\Domain\Catalog\Import\CsvFileRefused;
use App\Http\Requests\Commands\RefusingRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\UploadedFile;
use LogicException;

/**
 * The request of RR-01 (ROUTE_CONTRACTS section 5; INTERACTION_CONTRACTS section 6):
 * `multipart/form-data` with `file` and nothing else. The file's name and declared type
 * are never read (SEC-UP-01), and its size and content are the Action's, which refuses
 * them as CSV_INVALID. A file above PHP's limit arrives as an upload that failed, which
 * is that same refusal (SEC-UP-02); any other failed upload cannot be read.
 */
final class ImportPreviewRequest extends RefusingRequest
{
    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [...parent::messages(), 'uploaded' => ''];
    }

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
            'file.uploaded' => ['ux.validation.upload.unreadable'],
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        $upload = $this->file('file');

        if ($upload instanceof UploadedFile && in_array($upload->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            throw CsvFileRefused::of('ux.validation.upload.too_large', ['ukuran' => CsvDialect::SIZE_LABEL])->asRefusal('file');
        }

        parent::failedValidation($validator);
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
