<?php

namespace Tests\Unit\Operations\Backup;

use App\Domain\Operations\Backup\BackupFailed;
use App\Domain\Operations\Backup\BackupFailure;
use App\Domain\Operations\Backup\BackupSettings;
use App\Domain\Operations\Backup\Deadline;
use App\Domain\Operations\Backup\ObjectStore;
use Closure;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

/**
 * How the store's client reads what the store answers (AC-013-01, AC-013-06;
 * OPERATIONS_RUNBOOK RB-09): which code each refusal gets, a failure that never
 * holds the store's message, lists read to their end, an answer of another shape or
 * cut short refused, and what is tried again.
 */
final class ObjectStoreAnswersTest extends TestCase
{
    /**
     * @return array<string, array{int, string, BackupFailure}>
     */
    public static function refusals(): array
    {
        return [
            'the cap, with a status of authorization' => [403, '<Error><Code>AccessDenied</Code><Message>Cap exceeded on the path /secret</Message></Error>', BackupFailure::STORE_REFUSED],
            'the cap by its name' => [403, 'cap_exceeded', BackupFailure::STORE_REFUSED],
            'access denied' => [403, '<Error><Code>AccessDenied</Code><Message>/secret</Message></Error>', BackupFailure::STORE_AUTH],
            'an unknown key' => [403, '<Error><Code>InvalidAccessKeyId</Code></Error>', BackupFailure::STORE_AUTH],
            'a wrong signature' => [403, '<Error><Code>SignatureDoesNotMatch</Code></Error>', BackupFailure::STORE_AUTH],
            'unauthorized' => [401, '', BackupFailure::STORE_AUTH],
            'a forbidden answer to HEAD, without a body' => [403, '', BackupFailure::STORE_AUTH],
            'another 403' => [403, '<Error><Code>RequestTimeTooSkewed</Code></Error>', BackupFailure::STORE_REFUSED],
            'a bad request' => [400, '<Error><Code>InvalidRequest</Code></Error>', BackupFailure::STORE_REFUSED],
            'a server error' => [503, '', BackupFailure::STORE_REFUSED],
        ];
    }

    #[DataProvider('refusals')]
    public function test_each_refusal_has_its_code_and_holds_no_message_of_the_store(int $status, string $body, BackupFailure $expected): void
    {
        $failure = $this->failureOf(fn (ObjectStore $store) => $store->getText('state/x'), new Response($status, [], $body));

        $this->assertSame($expected, $failure->failure);
        $this->assertSame($expected->value, $failure->getMessage());
        $this->assertStringNotContainsString('/secret', $failure->diagnostic);
    }

    public function test_a_connection_that_fails_is_store_refused(): void
    {
        $failure = $this->failureOf(fn (ObjectStore $store) => $store->sizes('files/'), new ConnectException('at the address of the store', new Request('GET', 'http://store.test')));

        $this->assertSame(BackupFailure::STORE_REFUSED, $failure->failure);
        $this->assertStringNotContainsString('address of the store', $failure->diagnostic);
    }

    public function test_a_list_is_read_to_its_end_with_its_token_decoded(): void
    {
        $requests = [];
        $store = $this->store([
            new Response(200, [], '<ListBucketResult><Contents><Key>files/a.age</Key><ETag>&#34;x&#34;</ETag><Size>10</Size></Contents><IsTruncated>true</IsTruncated><NextContinuationToken>one&amp;two</NextContinuationToken></ListBucketResult>'),
            new Response(200, [], '<ListBucketResult><Contents><Size>7</Size><Key>files/b&amp;c.age</Key></Contents><IsTruncated>false</IsTruncated></ListBucketResult>'),
        ], $requests);

        $this->assertSame(['files/a.age' => 10, 'files/b&c.age' => 7], $store->sizes('files/'));
        $this->assertCount(2, $requests);
        $this->assertSame('list-type=2&prefix=files%2F', $requests[0]->getUri()->getQuery());
        $this->assertSame('continuation-token=one%26two&list-type=2&prefix=files%2F', $requests[1]->getUri()->getQuery());
    }

    public function test_the_usage_adds_every_version_of_every_page(): void
    {
        $requests = [];
        $store = $this->store([
            new Response(200, [], '<ListVersionsResult><Version><Size>10</Size></Version><Version><Size>5</Size></Version><IsTruncated>true</IsTruncated><NextKeyMarker>k</NextKeyMarker><NextVersionIdMarker>v</NextVersionIdMarker></ListVersionsResult>'),
            new Response(200, [], '<ListVersionsResult><Version><Size>7</Size></Version><IsTruncated>false</IsTruncated></ListVersionsResult>'),
        ], $requests);

        $this->assertSame(22, $store->usageBytes());
        $this->assertSame('key-marker=k&version-id-marker=v&versions=', $requests[1]->getUri()->getQuery());
    }

    /**
     * @return array<string, array{Closure(ObjectStore): mixed, string}>
     */
    public static function shapes(): array
    {
        return [
            'a list of another shape' => [fn (ObjectStore $store) => $store->sizes('files/'), '<Other/>'],
            'a list cut short' => [fn (ObjectStore $store) => $store->sizes('files/'), '<ListBucketResult><Contents><Key>files/a.age</Key><Size>1</Size></Contents>'],
            'an entry without its size' => [fn (ObjectStore $store) => $store->sizes('files/'), '<ListBucketResult><Contents><Key>files/a.age</Key></Contents></ListBucketResult>'],
            'a truncated list without its token' => [fn (ObjectStore $store) => $store->sizes('files/'), '<ListBucketResult><IsTruncated>true</IsTruncated></ListBucketResult>'],
            'a usage of another shape' => [fn (ObjectStore $store) => $store->usageBytes(), '<Other/>'],
            'a truncated usage without its marker' => [fn (ObjectStore $store) => $store->usageBytes(), '<ListVersionsResult><IsTruncated>true</IsTruncated></ListVersionsResult>'],
        ];
    }

    /**
     * @param  Closure(ObjectStore): mixed  $call
     */
    #[DataProvider('shapes')]
    public function test_an_answer_of_another_shape_is_refused(Closure $call, string $body): void
    {
        $this->assertSame(BackupFailure::STORE_REFUSED, $this->failureOf($call, new Response(200, [], $body))->failure);
    }

    public function test_an_upload_is_one_request_with_the_store_s_integrity_check(): void
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'bk-unit');
        file_put_contents($path, 'four');
        $requests = [];

        try {
            $this->store([new Response(200)], $requests)->put('db/6h/x.age', $path);
        } finally {
            unlink($path);
        }

        // It costs no read of the object, and the body's hashes are in the signed headers.
        $this->assertCount(1, $requests);
        $this->assertSame(hash('sha256', 'four'), $requests[0]->getHeaderLine('x-amz-content-sha256'));
        $this->assertSame(base64_encode(hash('md5', 'four', true)), $requests[0]->getHeaderLine('content-md5'));
        $this->assertStringContainsString('content-md5;host;x-amz-content-sha256;x-amz-date', $requests[0]->getHeaderLine('authorization'));
    }

    public function test_a_completion_that_answers_an_error_in_a_success_is_refused_and_the_upload_abandoned(): void
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'bk-unit');
        file_put_contents($path, 'more than the four bytes of a part');
        $requests = [];

        try {
            $this->store([
                new Response(200, [], '<InitiateMultipartUploadResult><UploadId>the-upload</UploadId></InitiateMultipartUploadResult>'),
                new Response(200, ['ETag' => '"a"']),
                new Response(200, ['ETag' => '"b"']),
                new Response(200, ['ETag' => '"c"']),
                new Response(200, ['ETag' => '"d"']),
                new Response(200, ['ETag' => '"e"']),
                new Response(200, ['ETag' => '"f"']),
                new Response(200, ['ETag' => '"g"']),
                new Response(200, ['ETag' => '"h"']),
                new Response(200, ['ETag' => '"i"']),
                new Response(200, [], '<Error><Code>InternalError</Code></Error>'),
                new Response(204),
            ], $requests, 4)->put('db/6h/x.age', $path);
            $this->fail('A completion with an error was accepted.');
        } catch (BackupFailed $failed) {
            $this->assertSame(BackupFailure::STORE_REFUSED, $failed->failure);
        } finally {
            unlink($path);
        }

        $last = $requests[count($requests) - 1];
        $this->assertSame('DELETE', $last->getMethod());
        $this->assertSame('uploadId=the-upload', $last->getUri()->getQuery());
        $this->assertStringContainsString('<PartNumber>9</PartNumber><ETag>&quot;i&quot;</ETag>', (string) $requests[count($requests) - 2]->getBody());
    }

    public function test_a_transient_answer_or_a_failed_connection_is_tried_again_up_to_its_limit(): void
    {
        $requests = [];
        $connection = new ConnectException('reset', new Request('GET', 'http://store.test'));
        $store = $this->store([new Response(503), $connection, new Response(200, [], 'the text')], $requests);

        $this->assertSame('the text', $store->getText('state/x'));
        $this->assertCount(3, $requests);

        $requests = [];
        $store = $this->store([new Response(500), new Response(500), new Response(500), new Response(200)], $requests);

        try {
            $store->getText('state/x');
            $this->fail('A lasting failure was accepted.');
        } catch (BackupFailed $failed) {
            $this->assertSame(BackupFailure::STORE_REFUSED, $failed->failure);
        }

        $this->assertCount(3, $requests);

        // A refusal is not tried again.
        $requests = [];
        $store = $this->store([new Response(403, [], '<Error><Code>AccessDenied</Code></Error>'), new Response(200)], $requests);

        try {
            $store->getText('state/x');
            $this->fail('A refusal was accepted.');
        } catch (BackupFailed $failed) {
            $this->assertSame(BackupFailure::STORE_AUTH, $failed->failure);
        }

        $this->assertCount(1, $requests);
    }

    public function test_a_default_port_is_not_part_of_the_signed_host(): void
    {
        $this->assertSame('store.test', ObjectStore::hostOf('https://store.test:443'));
        $this->assertSame('store.test', ObjectStore::hostOf('http://store.test:80'));
        $this->assertSame('store.test', ObjectStore::hostOf('https://STORE.test'));
        $this->assertSame('store.test:9000', ObjectStore::hostOf('http://store.test:9000'));
        $this->assertSame('store.test:80', ObjectStore::hostOf('https://store.test:80'));
    }

    public function test_the_time_limit_stops_a_request_before_it_leaves(): void
    {
        $requests = [];
        $store = new ObjectStore($this->client([], $requests), self::settings(), Deadline::after(0), 0);

        try {
            $store->sizes('files/');
            $this->fail('A request left after the time limit.');
        } catch (BackupFailed $failed) {
            $this->assertSame(BackupFailure::TIMEOUT, $failed->failure);
        }

        $this->assertSame([], $requests);
    }

    /**
     * @param  Closure(ObjectStore): mixed  $call
     */
    private function failureOf(Closure $call, Response|ConnectException $answer): BackupFailed
    {
        $requests = [];

        try {
            // As many as a transient answer is tried; a refusal uses only the first.
            $call($this->store([$answer, $answer, $answer], $requests));
        } catch (BackupFailed $failed) {
            return $failed;
        }

        $this->fail('The answer was accepted.');
    }

    /**
     * @param  list<Response|ConnectException>  $answers
     * @param  list<RequestInterface>  $requests
     */
    private function store(array $answers, array &$requests, int $partBytes = 1024): ObjectStore
    {
        return new ObjectStore($this->client($answers, $requests), self::settings($partBytes), Deadline::after(60), 0);
    }

    /**
     * @param  list<Response|ConnectException>  $answers
     * @param  list<RequestInterface>  $requests
     */
    private function client(array $answers, array &$requests): Client
    {
        $stack = HandlerStack::create(new MockHandler($answers));
        $stack->push(Middleware::mapRequest(function (RequestInterface $request) use (&$requests): RequestInterface {
            $requests[] = $request;

            return $request;
        }));

        return new Client(['handler' => $stack, 'http_errors' => false]);
    }

    private static function settings(int $partBytes = 1024): BackupSettings
    {
        return new BackupSettings(
            'http://store.test:9000',
            'us-east-1',
            'bucket',
            'key-id',
            'key',
            'age1'.str_repeat('q', 58),
            sys_get_temp_dir(),
            60,
            $partBytes,
            'pg_dump',
            'pg_restore',
            'age',
            ['6h' => 7, 'weekly' => 35, 'monthly' => 93],
            1000,
            1000,
            ['version' => null, 'commit' => null, 'imageDigest' => null],
        );
    }
}
