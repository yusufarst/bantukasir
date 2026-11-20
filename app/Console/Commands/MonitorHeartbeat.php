<?php

namespace App\Console\Commands;

use App\Domain\Operations\Heartbeat;
use App\Domain\Operations\PublicHealth;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

/**
 * SC-04 `monitor:heartbeat` (ROUTE_CONTRACTS SC-04 and section 7; ENVIRONMENTS section 13; ADR-011 item 11;
 * OPERATIONS_RUNBOOK section 4; TASK-039 scope item 3), in the application container. Its scheduler runs it
 * every 5 minutes (App\Console\ProcessSchedule), without overlap; no HTTP route reaches it. It takes no
 * option and no argument: the public address is `APP_URL` and the ping address the web check's secret
 * setting (config/heartbeat.php), and neither is ever printed or logged.
 *
 * It fetches the environment's public `/up` and pings the web check only when that answer is 200; any
 * other answer, or none, sends no ping, so the external monitor alerts the operator on the missing ping
 * (RB-06). It reads and changes no data, so it writes neither an audit event nor a security log entry: the
 * monitor is its record. A failure goes to the container's log with its outcome alone, because the
 * scheduler keeps no output (ProcessSchedule). It alone of the scheduled commands runs in maintenance mode,
 * in which RH-01 still answers, so a release's window raises no web alert of its own (OPEN_ITEMS T-39 (b);
 * OPERATIONS_RUNBOOK section 4).
 */
final class MonitorHeartbeat extends Command
{
    protected $signature = 'monitor:heartbeat';

    protected $description = 'SC-04: fetch the public /up through Cloudflare and, when it answers 200, ping the web check';

    public function handle(PublicHealth $health, Heartbeat $heartbeat): int
    {
        $address = PublicHealth::address();

        if ($address === null) {
            return $this->failed('HEARTBEAT_REFUSED', 'no public address is configured');
        }

        $status = $health->status($address);

        if ($status !== 200) {
            return $this->failed('HEARTBEAT_PUBLIC_FAILED', $status === null ? 'no answer' : 'status='.$status);
        }

        if (! $heartbeat->ping(Heartbeat::WEB)) {
            return $this->failed('HEARTBEAT_PING_FAILED', 'the web check took no ping');
        }

        $this->line('HEARTBEAT_PINGED');

        return SymfonyCommand::SUCCESS;
    }

    private function failed(string $outcome, string $detail): int
    {
        $this->line($outcome.' '.$detail);
        Log::warning('monitor.heartbeat', ['outcome' => $outcome, 'detail' => $detail]);

        return SymfonyCommand::FAILURE;
    }
}
