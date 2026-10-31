<?php

namespace App\Domain\Operations\Backup;

use Carbon\CarbonInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

/**
 * The S3 API of the backup store, with signed requests in path style (ADR-010 item
 * 4; TASK-013 scope items 2 and 9).
 *
 * The answers are read with fixed patterns, never with an XML parser, so that
 * nothing the store sends reaches libxml2 (D-112, OPEN_ITEMS T-38 (c)); an answer
 * of another shape refuses the run. A body is held in memory one part at a time.
 * A refusal keeps only the status and the store's code for the tests' messages,
 * never its message: BackupFailed. A failed connection and a transient answer are
 * tried again, within what remains of the run: one of them must not cost a whole
 * backup, whose next chance is six hours away.
 */
final class ObjectStore implements BackupStore
{
    private const string RECIPIENT_HEADER = 'x-amz-meta-bk-recipient';

    private const string LOCK_MODE = 'GOVERNANCE';

    private const int ATTEMPTS = 3;

    private const array TRANSIENT_STATUSES = [429, 500, 502, 503, 504];

    /** The codes of a key that the store does not accept (OPERATIONS_RUNBOOK RB-09). */
    private const string AUTH_CODES = '#<Code>(AccessDenied|InvalidAccessKeyId|SignatureDoesNotMatch|InvalidToken|ExpiredToken|Unauthorized)</Code>#';

    /** The store's refusal at its cap, which arrives with a status of authorization too. */
    private const string CAP = '/cap[_ ]exceeded/i';

    public function __construct(
        private readonly ClientInterface $client,
        private readonly BackupSettings $settings,
        private readonly Deadline $deadline,
        private readonly int $backoffSeconds = 1,
    ) {}

    /**
     * The address's host as the request's Host header sends it: a default port is
     * not part of it.
     */
    public static function hostOf(string $endpoint): string
    {
        $scheme = parse_url($endpoint, PHP_URL_SCHEME);
        $host = parse_url($endpoint, PHP_URL_HOST);
        $port = parse_url($endpoint, PHP_URL_PORT);

        if (! is_string($host)) {
            throw new BackupRefused('settings');
        }

        $default = $scheme === 'https' ? 443 : 80;

        return strtolower($host).(is_int($port) && $port !== $default ? ':'.$port : '');
    }

    /**
     * @param  array<string, string>  $metadata
     */
    public function put(string $key, string $path, array $metadata = [], ?CarbonInterface $retainUntil = null): void
    {
        $size = filesize($path);

        if ($size === false) {
            throw new BackupFailed(BackupFailure::DUMP_INVALID, 'The local object cannot be read.');
        }

        $headers = [];

        foreach ($metadata as $name => $value) {
            $headers['x-amz-meta-'.$name] = $value;
        }

        if ($retainUntil !== null) {
            $headers['x-amz-object-lock-mode'] = self::LOCK_MODE;
            $headers['x-amz-object-lock-retain-until-date'] = $retainUntil->copy()->utc()->format('Y-m-d\TH:i:s\Z');
        }

        if ($size > $this->settings->partBytes) {
            $this->putParts($key, $path, $headers);
        } else {
            $body = file_get_contents($path);

            if ($body === false) {
                throw new BackupFailed(BackupFailure::DUMP_INVALID, 'The local object cannot be read.');
            }

            $this->accepted($this->send('PUT', $key, [], $headers, $body));
        }
    }

    public function putText(string $key, string $text): void
    {
        $this->accepted($this->send('PUT', $key, [], [], $text));
    }

    public function getText(string $key): ?string
    {
        $response = $this->send('GET', $key);

        return $response->getStatusCode() === 404 ? null : (string) $this->accepted($response)->getBody();
    }

    public function head(string $key): ?StoredObject
    {
        $response = $this->send('HEAD', $key);

        if ($response->getStatusCode() === 404) {
            return null;
        }

        $length = $this->accepted($response)->getHeaderLine('Content-Length');

        if (! ctype_digit($length)) {
            throw new BackupFailed(BackupFailure::STORE_REFUSED, 'An answer without a size.');
        }

        return new StoredObject(
            (int) $length,
            $response->getHeaderLine(self::RECIPIENT_HEADER),
            $response->getHeaderLine('x-amz-object-lock-mode'),
            $response->getHeaderLine('x-amz-object-lock-retain-until-date'),
        );
    }

    /**
     * @return array<string, int>
     */
    public function sizes(string $prefix): array
    {
        $sizes = [];
        $query = ['list-type' => '2', 'prefix' => $prefix];

        do {
            $body = (string) $this->accepted($this->send('GET', '', $query))->getBody();
            self::requireShape($body, '</ListBucketResult>');

            preg_match_all('#<Contents>(.*?)</Contents>#s', $body, $matches);

            foreach ($matches[1] as $entry) {
                $sizes[self::element($entry, 'Key')] = (int) self::element($entry, 'Size');
            }

            $token = self::truncated($body) ? self::element($body, 'NextContinuationToken') : null;

            if ($token !== null) {
                $query['continuation-token'] = $token;
            }
        } while ($token !== null);

        return $sizes;
    }

    public function usageBytes(): int
    {
        $total = 0;
        $query = ['versions' => ''];

        do {
            $body = (string) $this->accepted($this->send('GET', '', $query))->getBody();
            self::requireShape($body, '</ListVersionsResult>');

            preg_match_all('#<Size>(\d+)</Size>#', $body, $matches);

            foreach ($matches[1] as $size) {
                $total += (int) $size;
            }

            $marker = self::truncated($body) ? self::element($body, 'NextKeyMarker') : null;

            if ($marker !== null) {
                $query['key-marker'] = $marker;
                $query['version-id-marker'] = self::element($body, 'NextVersionIdMarker');
            }
        } while ($marker !== null);

        return $total;
    }

    public function download(string $key, string $path): bool
    {
        $response = $this->send('GET', $key, [], [], '', $path);

        if ($response->getStatusCode() === 404) {
            return false;
        }

        $this->accepted($response);

        return true;
    }

    /**
     * A large object in parts, each with its own integrity check; the lock and
     * the metadata go with the first request. A failure abandons the upload, which
     * the store's rule for unfinished uploads removes if the abandon fails too
     * (BACKUP_RECOVERY section 6).
     *
     * @param  array<string, string>  $headers
     */
    private function putParts(string $key, string $path, array $headers): void
    {
        $created = (string) $this->accepted($this->send('POST', $key, ['uploads' => ''], $headers))->getBody();

        if (preg_match('#<UploadId>([^<]+)</UploadId>#', $created, $match) !== 1) {
            throw new BackupFailed(BackupFailure::STORE_REFUSED, 'An answer without an upload.');
        }

        $upload = self::text($match[1]);
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new BackupFailed(BackupFailure::DUMP_INVALID, 'The local object cannot be read.');
        }

        try {
            $parts = '';
            $number = 1;

            while (! feof($handle)) {
                $part = stream_get_contents($handle, max(1, $this->settings->partBytes));

                if ($part === false || $part === '') {
                    break;
                }

                $tag = $this->accepted($this->send('PUT', $key, ['partNumber' => (string) $number, 'uploadId' => $upload], [], $part))->getHeaderLine('ETag');

                if ($tag === '') {
                    throw new BackupFailed(BackupFailure::STORE_REFUSED, 'A part without its tag.');
                }

                $parts .= '<Part><PartNumber>'.$number.'</PartNumber><ETag>'.htmlspecialchars($tag, ENT_QUOTES | ENT_XML1).'</ETag></Part>';
                $number++;
            }

            $completed = (string) $this->accepted($this->send('POST', $key, ['uploadId' => $upload], [], '<CompleteMultipartUpload>'.$parts.'</CompleteMultipartUpload>'))->getBody();

            self::requireShape($completed, '</CompleteMultipartUploadResult>');
        } catch (BackupFailed $failure) {
            $this->abandon($key, $upload);

            throw $failure;
        } finally {
            fclose($handle);
        }
    }

    private function abandon(string $key, string $upload): void
    {
        try {
            $this->send('DELETE', $key, ['uploadId' => $upload]);
        } catch (BackupFailed) {
            // The failure that led here is the one to report.
        }
    }

    /**
     * One request, tried again after a failed connection or a transient answer. The
     * last answer is returned for the caller to judge.
     *
     * @param  array<string, string>  $query
     * @param  array<string, string>  $headers
     *
     * @throws BackupFailed
     */
    private function send(string $method, string $key, array $query = [], array $headers = [], string $body = '', ?string $sink = null): ResponseInterface
    {
        $attempt = 1;

        while (true) {
            $this->deadline->check();

            try {
                $response = $this->transmit($method, $key, $query, $headers, $body, $sink);

                if ($attempt >= self::ATTEMPTS || ! in_array($response->getStatusCode(), self::TRANSIENT_STATUSES, true)) {
                    return $response;
                }
            } catch (GuzzleException $failure) {
                if ($attempt >= self::ATTEMPTS) {
                    throw new BackupFailed(BackupFailure::STORE_REFUSED, $failure::class);
                }
            }

            sleep($this->backoffSeconds * $attempt);
            $attempt++;
        }
    }

    /**
     * One signed request, bounded by what remains of the run; signed for each
     * attempt, because a signature holds its time.
     *
     * @param  array<string, string>  $query
     * @param  array<string, string>  $headers
     *
     * @throws GuzzleException
     */
    private function transmit(string $method, string $key, array $query, array $headers, string $body, ?string $sink): ResponseInterface
    {
        $path = '/'.rawurlencode($this->settings->bucket);

        if ($key !== '') {
            $path .= '/'.implode('/', array_map(rawurlencode(...), explode('/', $key)));
        }

        if ($body !== '') {
            $headers['content-md5'] = base64_encode(hash('md5', $body, true));
        }

        $signed = SigV4::sign(
            $method,
            self::hostOf($this->settings->endpoint),
            $path,
            $query,
            $headers,
            hash('sha256', $body),
            $this->settings->region,
            $this->settings->keyId,
            $this->settings->key,
            gmdate('Ymd\THis\Z'),
        );

        $canonicalQuery = SigV4::query($query);
        $options = ['headers' => $signed, 'timeout' => $this->deadline->remaining()];

        if ($body !== '') {
            $options['body'] = $body;
        }

        if ($sink !== null) {
            $options['sink'] = $sink;
        }

        return $this->client->request($method, $this->settings->endpoint.$path.($canonicalQuery === '' ? '' : '?'.$canonicalQuery), $options);
    }

    /**
     * The answer when it is a success; otherwise the run fails with its code.
     *
     * @throws BackupFailed
     */
    private function accepted(ResponseInterface $response): ResponseInterface
    {
        $status = $response->getStatusCode();

        if ($status >= 200 && $status < 300) {
            return $response;
        }

        $body = (string) $response->getBody();
        $code = preg_match('#<Code>([A-Za-z]+)</Code>#', $body, $match) === 1 ? $match[1] : '';
        $diagnostic = 'HTTP '.$status.' '.$code;

        if (preg_match(self::CAP, $body) === 1) {
            throw new BackupFailed(BackupFailure::STORE_REFUSED, $diagnostic);
        }

        if ($status === 401 || ($status === 403 && ($body === '' || preg_match(self::AUTH_CODES, $body) === 1))) {
            throw new BackupFailed(BackupFailure::STORE_AUTH, $diagnostic);
        }

        throw new BackupFailed(BackupFailure::STORE_REFUSED, $diagnostic);
    }

    /**
     * A closing element: an answer cut short does not pass.
     *
     * @throws BackupFailed
     */
    private static function requireShape(string $body, string $element): void
    {
        if (! str_contains($body, $element)) {
            throw new BackupFailed(BackupFailure::STORE_REFUSED, 'An answer of another shape.');
        }
    }

    private static function truncated(string $body): bool
    {
        return str_contains($body, '<IsTruncated>true</IsTruncated>');
    }

    /**
     * The text of an element that a list must hold.
     *
     * @throws BackupFailed
     */
    private static function element(string $body, string $name): string
    {
        if (preg_match('#<'.$name.'>([^<]*)</'.$name.'>#', $body, $match) !== 1) {
            throw new BackupFailed(BackupFailure::STORE_REFUSED, 'A list without its element.');
        }

        return self::text($match[1]);
    }

    private static function text(string $escaped): string
    {
        return html_entity_decode($escaped, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
