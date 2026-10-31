<?php

namespace App\Domain\Operations\Backup;

use SensitiveParameter;

/**
 * AWS Signature Version 4 for the S3 API of the backup store (ADR-010 item 4). The
 * signed payload hash is also the store's integrity check of each request's body:
 * the store refuses a body that differs from it.
 */
final class SigV4
{
    private const string ALGORITHM = 'AWS4-HMAC-SHA256';

    /**
     * The query string in its canonical form, sorted by name, which is also the
     * form that the request sends.
     *
     * @param  array<string, string>  $query
     */
    public static function query(array $query): string
    {
        ksort($query, SORT_STRING);

        return http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * The request's headers with its signature: the given ones in lower case, the
     * date, the payload hash and the authorization; host is signed and not
     * returned, since the HTTP client sends it from the address.
     *
     * @param  array<string, string>  $query
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    public static function sign(
        string $method,
        string $host,
        string $path,
        array $query,
        array $headers,
        string $payloadHash,
        string $region,
        string $keyId,
        #[SensitiveParameter]
        string $key,
        string $amzDate,
    ): array {
        $date = substr($amzDate, 0, 8);
        $signed = ['host' => $host, 'x-amz-content-sha256' => $payloadHash, 'x-amz-date' => $amzDate];

        foreach ($headers as $name => $value) {
            $signed[strtolower($name)] = trim($value);
        }

        ksort($signed, SORT_STRING);

        $canonicalHeaders = '';

        foreach ($signed as $name => $value) {
            $canonicalHeaders .= $name.':'.$value."\n";
        }

        $names = implode(';', array_keys($signed));
        $canonical = implode("\n", [$method, $path, self::query($query), $canonicalHeaders, $names, $payloadHash]);
        $scope = $date.'/'.$region.'/s3/aws4_request';
        $toSign = implode("\n", [self::ALGORITHM, $amzDate, $scope, hash('sha256', $canonical)]);

        $signingKey = hash_hmac('sha256', $date, 'AWS4'.$key, true);
        $signingKey = hash_hmac('sha256', $region, $signingKey, true);
        $signingKey = hash_hmac('sha256', 's3', $signingKey, true);
        $signingKey = hash_hmac('sha256', 'aws4_request', $signingKey, true);

        unset($signed['host']);
        $signed['authorization'] = self::ALGORITHM.' Credential='.$keyId.'/'.$scope.', SignedHeaders='.$names.', Signature='.hash_hmac('sha256', $toSign, $signingKey);

        return $signed;
    }
}
