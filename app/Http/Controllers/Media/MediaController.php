<?php

namespace App\Http\Controllers\Media;

use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Domain\Media\MediaStore;
use App\Domain\Media\StoredFiles;
use App\Models\User;
use App\Policies\NotFound;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * RD-07 `GET /media/{file}` (ROUTE_CONTRACTS section 4; INTERACTION_CONTRACTS
 * section 3.7; SECURITY_MODEL SEC-CH-06, SEC-UP-06; THR-12; TASK-010 scope
 * item 2): a stored image, delivered through the application to a signed-in
 * user whose role may see it — a logo to every role; a product photo or
 * thumbnail only with CAP-03 (TASK-018), `variant=thumb` naming a photo's
 * thumbnail — never from a storage path, which no answer names. A file that
 * does not exist, one the user may not see, a variant that does not apply and a
 * missing stored file are all NOT_FOUND, one answer (TR-ERR-03). A file whose
 * command rolled back has no record, so it is never served (AC-018-03). A
 * replaced or removed photo is kept and no page references it any more
 * (INTERACTION_CONTRACTS section 3.7, D-042). Its key never changes, so the
 * answer may be cached privately by the browser.
 */
final class MediaController
{
    /** The private caching of an immutable stored image (INTERACTION_CONTRACTS section 3.7). */
    public const string CACHE_CONTROL = 'private, max-age=86400, immutable';

    /** The one variant of RD-07, of a product photo. */
    private const string THUMBNAIL = 'thumb';

    public function __invoke(Request $request, string $file, StoredFiles $files, MediaStore $store): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        if (preg_match('/^[1-9][0-9]{0,17}$/D', $file) !== 1) {
            throw new NotFound;
        }

        $record = $files->find((int) $file);

        if ($record === null || ($record->purpose !== StoredFiles::LOGO && ! Capabilities::allows($user, Capability::Cap03))) {
            throw new NotFound;
        }

        $variant = $request->query('variant');

        if ($variant !== null) {
            if ($variant !== self::THUMBNAIL || $record->purpose !== StoredFiles::PRODUCT_PHOTO) {
                throw new NotFound;
            }

            $record = $files->thumbnailOf($record->id) ?? throw new NotFound;
        }

        $bytes = $store->read($record->storageKey) ?? throw new NotFound;

        return new Response($bytes, 200, [
            'Content-Type' => $record->mediaType,
            'Content-Length' => (string) strlen($bytes),
            'Cache-Control' => self::CACHE_CONTROL,
            'Content-Disposition' => 'inline',
        ]);
    }
}
