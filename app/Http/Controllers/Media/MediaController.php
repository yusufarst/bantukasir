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
 * thumbnail only with CAP-03 (TASK-018 adds them and the `variant` thumb) —
 * never from a storage path, which no answer names. A file that does not
 * exist, one the user may not see, a variant that does not apply and a
 * missing stored file are all NOT_FOUND, one answer (TR-ERR-03). Its key
 * never changes, so the answer may be cached privately by the browser.
 */
final class MediaController
{
    /** The private caching of an immutable stored image (INTERACTION_CONTRACTS section 3.7). */
    public const string CACHE_CONTROL = 'private, max-age=86400, immutable';

    public function __invoke(Request $request, string $file, StoredFiles $files, MediaStore $store): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        if (preg_match('/^[1-9][0-9]{0,17}$/D', $file) !== 1 || $request->query('variant') !== null) {
            throw new NotFound;
        }

        $record = $files->find((int) $file);

        if ($record === null || ($record->purpose !== StoredFiles::LOGO && ! Capabilities::allows($user, Capability::Cap03))) {
            throw new NotFound;
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
