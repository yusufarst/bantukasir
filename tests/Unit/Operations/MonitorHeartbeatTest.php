<?php

namespace Tests\Unit\Operations;

use App\Domain\Operations\Heartbeat;
use App\Domain\Operations\PublicHealth;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Tests\TestCase;
use Throwable;

/**
 * SC-04 `monitor:heartbeat` against a local stand-in of the public address (AC-039-03; ROUTE_CONTRACTS SC-04
 * and RH-01; ENVIRONMENTS section 13; ADR-011 item 11; TASK-039 section 7, L2 SC-04): it fetches the configured
 * public `/up` and pings the web check only when that answers 200; any other answer, no answer, or no usable
 * address sends no ping and fails; a ping the monitor does not take fails; no line and no log entry holds an
 * address; it takes no option or argument (THR-10).
 */
final class MonitorHeartbeatTest extends TestCase
{
    private const string PUBLIC = 'https://toko.stand-in.test';

    private const string PING = 'https://heartbeat.stand-in.test/ping/web-secret';

    /** @var list<string> */
    private array $requests = [];

    /** @var list<array{string, string, string}> each entry of the log: its level, its message and its context */
    private array $logged = [];

    /** @var int|null the stand-in's answer to /up; null for no answer */
    private ?int $answer = 200;

    private int $pingAnswer = 200;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => self::PUBLIC, 'heartbeat.checks.web' => self::PING]);
        $this->app->instance(PublicHealth::class, new PublicHealth($this->standIn()));
        $this->app->instance(Heartbeat::class, new Heartbeat($this->standIn()));
    }

    public function test_a_public_200_pings_the_web_check_after_the_public_check(): void
    {
        $this->assertSame(0, Artisan::call('monitor:heartbeat'));
        $this->assertSame([self::PUBLIC.'/up', self::PING], $this->requests);
        $this->assertSame("HEARTBEAT_PINGED\n", Artisan::output());
    }

    /**
     * @return array<string, array{int|null, string}>
     */
    public static function failedAnswers(): array
    {
        return [
            'maintenance or a failed database' => [503, 'HEARTBEAT_PUBLIC_FAILED status=503'],
            'a server error' => [500, 'HEARTBEAT_PUBLIC_FAILED status=500'],
            'a redirect, never followed' => [301, 'HEARTBEAT_PUBLIC_FAILED status=301'],
            'another success than 200' => [204, 'HEARTBEAT_PUBLIC_FAILED status=204'],
            'no answer' => [null, 'HEARTBEAT_PUBLIC_FAILED no answer'],
        ];
    }

    #[DataProvider('failedAnswers')]
    public function test_any_other_public_answer_sends_no_ping(?int $answer, string $line): void
    {
        $this->answer = $answer;
        $this->app['events']->listen(MessageLogged::class, function (MessageLogged $event): void {
            $this->logged[] = [$event->level, $event->message, (string) json_encode($event->context)];
        });

        $this->assertSame(1, Artisan::call('monitor:heartbeat'));
        $this->assertSame([self::PUBLIC.'/up'], $this->requests);
        $this->assertSame($line."\n", Artisan::output());
        $this->assertCount(1, $this->logged);
        [$level, $message, $context] = $this->logged[0] ?? ['', '', ''];
        $this->assertSame(['warning', 'monitor.heartbeat'], [$level, $message]);
        $this->assertStringNotContainsString('stand-in', $context);
    }

    public function test_a_ping_the_monitor_does_not_take_fails_without_its_address(): void
    {
        $this->pingAnswer = 404;

        $this->assertSame(1, Artisan::call('monitor:heartbeat'));
        $this->assertSame([self::PUBLIC.'/up', self::PING], $this->requests);
        $this->assertSame("HEARTBEAT_PING_FAILED the web check took no ping\n", Artisan::output());

        // Without a ping address, nothing is pinged.
        $this->requests = [];
        config(['heartbeat.checks.web' => null]);
        $this->assertSame(1, Artisan::call('monitor:heartbeat'));
        $this->assertSame([self::PUBLIC.'/up'], $this->requests);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function unusableAddresses(): array
    {
        return [
            'none' => [null],
            'empty' => [''],
            'relative' => ['/toko'],
            'another scheme' => ['ftp://toko.stand-in.test'],
            'with a user' => ['https://user:pass@toko.stand-in.test'],
            'with a query' => ['https://toko.stand-in.test/?a=1'],
        ];
    }

    #[DataProvider('unusableAddresses')]
    public function test_without_a_usable_public_address_nothing_is_fetched_or_pinged(mixed $address): void
    {
        config(['app.url' => $address]);

        $this->assertSame(1, Artisan::call('monitor:heartbeat'));
        $this->assertSame([], $this->requests);
        $this->assertSame("HEARTBEAT_REFUSED no public address is configured\n", Artisan::output());
    }

    public function test_the_address_of_rh_01_keeps_the_public_address_and_its_path(): void
    {
        config(['app.url' => 'https://toko.stand-in.test/']);
        $this->assertSame('https://toko.stand-in.test/up', PublicHealth::address());

        config(['app.url' => 'http://127.0.0.1:18080']);
        $this->assertSame('http://127.0.0.1:18080/up', PublicHealth::address());
    }

    public function test_it_takes_no_option_or_argument(): void
    {
        $command = Artisan::all()['monitor:heartbeat'] ?? $this->fail('monitor:heartbeat is not registered.');

        $this->assertSame([], $command->getNativeDefinition()->getArguments());
        $this->assertSame([], $command->getNativeDefinition()->getOptions());

        $refusal = null;

        try {
            Artisan::call('monitor:heartbeat', ['--url' => 'https://elsewhere.stand-in.test']);
        } catch (Throwable $thrown) {
            $refusal = $thrown;
        }

        $this->assertInstanceOf(InvalidOptionException::class, $refusal);
        $this->assertSame([], $this->requests);
    }

    /**
     * A Guzzle client whose handler is the stand-in of both addresses: it records each request and answers
     * /up with the test's answer, or none, and the ping with the test's.
     */
    private function standIn(): Client
    {
        return new Client(['handler' => function (RequestInterface $request): PromiseInterface {
            $uri = (string) $request->getUri();
            $this->requests[] = $uri;

            if ($uri === self::PING) {
                return Create::promiseFor(new Response($this->pingAnswer));
            }

            if ($this->answer === null) {
                return Create::rejectionFor(new ConnectException('Could not reach '.$uri, $request));
            }

            return Create::promiseFor(new Response($this->answer));
        }]);
    }
}
