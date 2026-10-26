<?php

namespace Tests\Feature\Audit;

use App\Domain\Audit\Actor;
use App\Domain\Audit\AuditCategory;
use App\Domain\Audit\AuditLog;
use App\Domain\Audit\SecurityActor;
use App\Domain\Audit\SecurityEvent;
use App\Domain\Audit\SecurityLog;
use App\Domain\Audit\SecurityOutcome;
use Illuminate\Http\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;

/**
 * The audit writer and the security log writer (AC-006-02; SECURITY_MODEL
 * SEC-LOG-01 to SEC-LOG-04, SEC-CH-08; SCHEMA sections 3.4, 3.5 and 5.4;
 * TECHNICAL_RULES TR-TX-02; suite AUDIT01). An audit event is written only
 * inside the transaction of its change and rolls back with it, freezes the
 * actor's display name and keeps owner-only data apart; it refuses a key that
 * would carry a secret. A security log entry is written only after the
 * transaction has ended, survives its rollback, is copied to the container's
 * output and holds nothing of the request but its identifier, route, outcome
 * and address.
 */
final class WritersTest extends DatabaseTestCase
{
    public function test_an_audit_event_is_written_in_its_transaction_and_rolls_back_with_it(): void
    {
        $rina = TokoContoh::person('Rina');
        $events = $this->observer()->table('audit_events')->count();
        $audit = app(AuditLog::class);

        try {
            DB::transaction(function () use ($audit, $rina): void {
                $audit->record(AuditCategory::Business, 'PRODUCT_CREATE', Actor::user($rina['id'], 'Rina'), ['name' => 'Pulpen']);

                throw new RuntimeException('rolled back');
            });
        } catch (RuntimeException) {
        }

        $this->assertSame($events, $this->observer()->table('audit_events')->count());

        $id = DB::transaction(fn (): int => $audit->record(
            AuditCategory::Business,
            'PRODUCT_CREATE',
            Actor::user($rina['id'], 'Rina'),
            ['name' => 'Pulpen gel'],
            ownerOnlyData: ['cost' => '1500.000000'],
            subject: ['type' => 'product', 'id' => 7],
            reference: 'BK-P-1',
            reason: 'Barang baru',
        ));

        $row = (array) $this->observer()->table('audit_events')->where('id', $id)->sole();
        $this->assertSame(['BUSINESS', 'PRODUCT_CREATE', 'USER', $rina['id'], null, 'Rina', 'product', 7, 'BK-P-1', 'Barang baru'], [
            $row['category'], $row['action'], $row['actor_kind'], $row['actor_user_id'], $row['actor_process'], $row['actor_display_name'], $row['subject_type'], $row['subject_id'], $row['reference'], $row['reason'],
        ]);
        $this->assertSame(['name' => 'Pulpen gel'], json_decode((string) $row['change_data'], true));
        $this->assertSame(['cost' => '1500.000000'], json_decode((string) $row['owner_only_data'], true));
    }

    public function test_the_audit_writer_refuses_outside_a_transaction_a_bad_action_a_secret_and_a_stray_note(): void
    {
        $audit = app(AuditLog::class);
        $actor = Actor::operatorProcedure('bootstrap');

        $this->assertThrows(fn () => $audit->record(AuditCategory::Security, 'USER_CREATE', $actor), LogicException::class);

        $refusals = [
            fn () => $audit->record(AuditCategory::Security, 'user_create', $actor),
            fn () => $audit->record(AuditCategory::Security, 'LINK_ISSUE', $actor, ['token' => 'x']),
            fn () => $audit->record(AuditCategory::Security, 'LINK_ISSUE', $actor, ['link' => ['plainToken' => 'x']]),
            fn () => $audit->record(AuditCategory::Security, 'PASSWORD_CHANGE', $actor, ownerOnlyData: ['password' => 'x']),
            fn () => $audit->record(AuditCategory::Security, 'DETAILS_CHANGE', Actor::user(1, 'Rina'), operatorNote: 'only a procedure notes'),
        ];

        foreach ($refusals as $refusal) {
            $this->assertThrows(fn () => DB::transaction($refusal), InvalidArgumentException::class);
        }

        $this->assertThrows(fn () => Actor::operatorProcedure('shell'), InvalidArgumentException::class);
        $this->assertThrows(fn () => Actor::scheduledCommand('bootstrap'), InvalidArgumentException::class);
        $this->assertThrows(fn () => SecurityActor::operatorProcedure('reconciliation'), InvalidArgumentException::class);

        DB::transaction(fn (): int => $audit->record(AuditCategory::Security, 'OWNER_RECOVERY', $actor, operatorNote: 'Pemilik lupa kata sandi'));
        $row = (array) $this->observer()->table('audit_events')->where('action', 'OWNER_RECOVERY')->sole();
        $this->assertSame(['OPERATOR_PROCEDURE', null, 'bootstrap', 'bootstrap', 'Pemilik lupa kata sandi'], [$row['actor_kind'], $row['actor_user_id'], $row['actor_process'], $row['actor_display_name'], $row['operator_note']]);
    }

    public function test_a_security_log_entry_is_written_after_the_transaction_and_survives_its_rollback(): void
    {
        $log = app(SecurityLog::class);
        $request = Request::create('/login', 'POST', server: ['REMOTE_ADDR' => '192.0.2.44', 'HTTP_USER_AGENT' => 'Harness/1.0']);

        $this->assertThrows(fn () => DB::transaction(fn () => $log->record(SecurityEvent::SignInFailed, SecurityActor::anonymous(), SecurityOutcome::Refused, $request)), LogicException::class);
        $this->assertSame(0, $this->observer()->table('security_log_entries')->count());

        try {
            DB::transaction(function (): void {
                DB::table('cache')->insert(['key' => 'harness', 'value' => 'v', 'expiration' => 1]);

                throw new RuntimeException('rolled back');
            });
        } catch (RuntimeException) {
            $log->record(SecurityEvent::LinkFailed, SecurityActor::anonymous(), SecurityOutcome::Refused, $request);
        }

        $this->assertSame(0, $this->observer()->table('cache')->count());
        $entry = (array) $this->observer()->table('security_log_entries')->sole();
        $this->assertSame(['LINK_FAILED', 'ANONYMOUS', null, null, null, 'REFUSED', '192.0.2.44'], [
            $entry['event_type'], $entry['actor_kind'], $entry['actor_user_id'], $entry['actor_process'], $entry['subject_user_id'], $entry['outcome_code'], $entry['client_address'],
        ]);
        $this->assertNull($entry['route_name']);
        $this->assertStringNotContainsString('Harness/1.0', (string) json_encode($entry));
    }

    public function test_each_entry_is_also_one_line_on_the_container_output_with_the_same_fields(): void
    {
        $lines = [];
        Log::listen(function (MessageLogged $message) use (&$lines): void {
            $lines[] = $message;
        });

        $request = Request::create('/user/confirm-password', 'POST', ['password' => 'Rahasia#1'], server: ['REMOTE_ADDR' => '192.0.2.45']);
        app(SecurityLog::class)->recordRun(SecurityEvent::OperatorProcedureRun, SecurityActor::operatorProcedure('owner_recovery'), SecurityOutcome::Succeeded, 'run-1');
        app(SecurityLog::class)->record(SecurityEvent::PasswordConfirmationFailed, SecurityActor::anonymous(), SecurityOutcome::Refused, $request);

        $this->assertCount(2, $lines);
        $this->assertSame(['info', 'security_log'], [$lines[0]->level, $lines[0]->message]);
        $this->assertSame('run-1', $lines[0]->context['request_id']);
        $this->assertSame(['OPERATOR_PROCEDURE_RUN', 'OPERATOR_PROCEDURE', 'owner_recovery', null], [$lines[0]->context['event_type'], $lines[0]->context['actor_kind'], $lines[0]->context['actor_process'], $lines[0]->context['client_address']]);
        $this->assertSame(
            ['occurred_at', 'event_type', 'actor_kind', 'actor_user_id', 'actor_process', 'subject_user_id', 'request_id', 'route_name', 'outcome_code', 'client_address'],
            array_keys($lines[1]->context),
        );
        $this->assertStringNotContainsString('Rahasia', (string) json_encode([$lines, $this->observer()->table('security_log_entries')->get()]));
    }
}
