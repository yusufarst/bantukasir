<?php

namespace Tests\Harness\Race;

use Illuminate\Database\ConnectionInterface;
use RuntimeException;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

/**
 * One competing command of a race test, in its own process with its own
 * connection (TEST_STRATEGY TS-20; process.php). A test starts every worker
 * first, so that they boot side by side, waits until all are ready with
 * Worker::ready(), then runs each command with run().
 * It chooses barriers — after_identity, after_lock:<step>, after_write:<k>,
 * before_commit — and so the interleaving: it waits until a worker has reached
 * a barrier, until it waits for a lock held by another, and then lets it go on
 * or aborts it.
 *
 * Every wait ends on an event — a line from the worker or a lock in
 * pg_locks — never after a sleep that assumes an order. A wait that sees no
 * event within its limit fails the test: a race test is never retried (TS-08).
 * The limit stays below the runtime role's idle-in-transaction and transaction
 * timeouts of 30 seconds, and no worker boots while another holds a
 * transaction open, so a worker waiting at a barrier is not ended by the
 * database first.
 */
final class Worker
{
    public const float WAIT_SECONDS = 20.0;

    private readonly InputStream $input;

    private string $buffer = '';

    /** @var list<array{string, string}> */
    private array $lines = [];

    private int $read = 0;

    private ?int $backendPid = null;

    private function __construct(private readonly Process $process)
    {
        $this->input = new InputStream;
        $this->process->setInput($this->input);
        $this->process->setTimeout(null);
        $this->process->start();
    }

    /**
     * @param  class-string<Scenario>  $scenario
     * @param  array<string, mixed>  $arguments
     * @param  list<string>  $barriers
     * @param  array<string, string>  $environment  values that replace the test process's, for the worker's own refusals
     */
    public static function start(string $scenario, array $arguments = [], array $barriers = [], ?int $failAfter = null, array $environment = []): self
    {
        $root = dirname(__DIR__, 3);

        return new self(new Process(
            [PHP_BINARY, $root.'/tests/Harness/Race/process.php', $scenario, base64_encode(json_encode($arguments, JSON_THROW_ON_ERROR)), $barriers === [] ? '-' : implode(',', $barriers), $failAfter === null ? '-' : (string) $failAfter],
            $root,
            $environment + self::environment(),
        ));
    }

    /**
     * The PostgreSQL backend of the worker's connection, once it is connected.
     */
    public function backendPid(): int
    {
        if ($this->backendPid === null) {
            $this->backendPid = (int) $this->await('READY');
        }

        return $this->backendPid;
    }

    /**
     * Waits until every worker has booted and connected; a test calls it
     * before it runs the first command, so that no transaction stays open
     * while another worker boots.
     */
    public static function ready(self ...$workers): void
    {
        foreach ($workers as $worker) {
            $worker->backendPid();
        }
    }

    /**
     * Starts the command, once the worker has booted and connected.
     */
    public function run(): self
    {
        $this->backendPid();
        $this->input->write("RUN\n");

        return $this;
    }

    /**
     * Waits until the worker stops at the barrier.
     */
    public function awaitBarrier(string $point): void
    {
        $reached = $this->await('REACHED');

        if ($reached !== $point) {
            throw new RuntimeException(sprintf('The worker reached %s, not the barrier %s.', $reached, $point));
        }
    }

    public function release(): void
    {
        $this->input->write("GO\n");
    }

    public function abort(): void
    {
        $this->input->write("ABORT\n");
    }

    /**
     * Waits until the worker's session waits for a lock that another holds.
     */
    public function awaitBlocked(ConnectionInterface $observer): void
    {
        $pid = $this->backendPid();
        $deadline = microtime(true) + self::WAIT_SECONDS;

        while (microtime(true) < $deadline) {
            if ($observer->scalar('select exists (select from pg_catalog.pg_locks where pid = ? and not granted)', [$pid]) === true) {
                return;
            }

            $this->collect();

            if (! $this->process->isRunning()) {
                throw new RuntimeException('The worker ended instead of waiting for a lock: '.$this->transcript());
            }

            usleep(2000);
        }

        throw new RuntimeException(sprintf('The worker did not wait for a lock within %.0f s: %s', self::WAIT_SECONDS, $this->transcript()));
    }

    /**
     * Waits for the worker's end and returns its outcome.
     */
    public function finish(): Outcome
    {
        $this->input->close();
        $deadline = microtime(true) + self::WAIT_SECONDS;

        while ($this->process->isRunning() && microtime(true) < $deadline) {
            usleep(2000);
        }

        if ($this->process->isRunning()) {
            $this->stop();

            throw new RuntimeException(sprintf('The worker did not end within %.0f s: %s', self::WAIT_SECONDS, $this->transcript()));
        }

        $this->collect();

        return Outcome::of($this->lines, $this->process->getErrorOutput());
    }

    /**
     * Ends the worker at once, when a test fails before its end.
     */
    public function stop(): void
    {
        if ($this->process->isRunning()) {
            $this->process->stop(0);
        }
    }

    /**
     * The payload of the next unread line of this kind; earlier lines of other
     * kinds stay recorded for the outcome.
     */
    private function await(string $word): string
    {
        $deadline = microtime(true) + self::WAIT_SECONDS;

        while (true) {
            while ($this->read < count($this->lines)) {
                [$kind, $payload] = $this->lines[$this->read++];

                if ($kind === $word) {
                    return $payload;
                }

                if ($kind === 'DONE' || $kind === 'FAILED') {
                    throw new RuntimeException(sprintf('The worker ended (%s %s) before %s.', $kind, $payload, $word));
                }
            }

            if (microtime(true) >= $deadline) {
                throw new RuntimeException(sprintf('No %s from the worker within %.0f s: %s', $word, self::WAIT_SECONDS, $this->transcript()));
            }

            if (! $this->process->isRunning()) {
                $this->collect();

                if ($this->read >= count($this->lines)) {
                    throw new RuntimeException(sprintf('The worker ended before %s: %s', $word, $this->transcript()));
                }

                continue;
            }

            usleep(2000);
            $this->collect();
        }
    }

    private function collect(): void
    {
        $this->buffer .= $this->process->getIncrementalOutput();

        while (($end = strpos($this->buffer, "\n")) !== false) {
            $line = substr($this->buffer, 0, $end);
            $this->buffer = substr($this->buffer, $end + 1);
            [$word, $payload] = array_pad(explode(' ', $line, 2), 2, '');
            $this->lines[] = [$word, $payload];
        }
    }

    private function transcript(): string
    {
        return json_encode(['lines' => $this->lines, 'stderr' => $this->process->getErrorOutput()], JSON_UNESCAPED_SLASHES) ?: '';
    }

    /**
     * The worker's environment: the test process's, so that it reaches the
     * same test database as the same roles.
     *
     * @return array<string, string>
     */
    private static function environment(): array
    {
        $environment = [];

        foreach (['APP_ENV', 'APP_KEY', 'APP_ENVIRONMENT_NAME', 'DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'DB_MIGRATION_USERNAME', 'DB_MIGRATION_PASSWORD', 'BACKUP_DB_USERNAME', 'BACKUP_DB_PASSWORD', 'CACHE_STORE', 'SESSION_DRIVER'] as $name) {
            $value = getenv($name);

            if (is_string($value)) {
                $environment[$name] = $value;
            }
        }

        return $environment;
    }
}
