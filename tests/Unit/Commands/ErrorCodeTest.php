<?php

namespace Tests\Unit\Commands;

use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use Illuminate\Support\Facades\Lang;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * The 32 error codes (AC-008-08; TECHNICAL_RULES section 4; COPY_DECK section
 * 13.1; ROUTE_CONTRACTS RT-07; TASK-008 scope item 7), read from the
 * documents: each code with the status of TECHNICAL_RULES section 4 and
 * every message key of COPY_DECK section 13.1, each a key of lang/id.
 */
final class ErrorCodeTest extends TestCase
{
    public function test_the_codes_and_message_keys_are_those_of_copy_deck_section_13_1(): void
    {
        $document = (string) file_get_contents(base_path('docs/07-ux-design/COPY_DECK.md'));
        $section = substr($document, (int) strpos($document, '### 13.1 Codes'), (int) strpos($document, '### 13.2') - (int) strpos($document, '### 13.1 Codes'));
        $codes = [];

        foreach (explode("\n", $section) as $line) {
            if (preg_match('/^\| `([A-Z_]+)` \| UXS-\d+ \| (.+?) \| /', $line, $match) === 1) {
                preg_match_all('/`(ux\.state\.[a-z_.]+?)(\.\*)?`/', $match[2], $keys);
                $codes[$match[1]] = $keys[1];
            }
        }

        $this->assertCount(32, $codes, 'COPY_DECK section 13.1 lists 32 codes.');
        $this->assertSame(array_keys($codes), array_map(static fn (ErrorCode $code): string => $code->value, ErrorCode::cases()));

        foreach (ErrorCode::cases() as $code) {
            $this->assertSame($codes[$code->value], $code->variants(), $code->value);

            foreach ($code->variants() as $key) {
                $this->assertTrue(Lang::has($key, 'id'), sprintf('%s is a key of lang/id.', $key));
            }
        }
    }

    public function test_each_code_has_the_status_of_technical_rules_section_4(): void
    {
        $document = (string) file_get_contents(base_path('docs/06-api-performance/TECHNICAL_RULES.md'));
        $section = substr($document, (int) strpos($document, '## 4. Error mapping'), (int) strpos($document, '## 5. Validation') - (int) strpos($document, '## 4. Error mapping'));
        $statuses = [];

        foreach (explode("\n", $section) as $line) {
            $cells = array_map(trim(...), explode('|', $line));

            if (count($cells) < 6 || preg_match('/^(\d{3})\b/', $cells[2], $status) !== 1) {
                continue;
            }

            preg_match_all('/`([A-Z_]+)`/', $cells[4], $codes);

            foreach ($codes[1] as $code) {
                $statuses[$code] = (int) $status[1];
            }
        }

        // CSRF_FAILED and RECENT_AUTH_REQUIRED are "the framework's response",
        // 419 and 423 (INTERACTION_CONTRACTS section 1.2).
        $statuses += ['CSRF_FAILED' => 419, 'RECENT_AUTH_REQUIRED' => 423];

        foreach (ErrorCode::cases() as $code) {
            $this->assertSame($statuses[$code->value] ?? null, $code->status(), $code->value);
        }

        $this->assertSame(['IDEMPOTENCY_CONFLICT', 'EPOCH_STALE'], array_values(array_map(
            static fn (ErrorCode $code): string => $code->value,
            array_filter(ErrorCode::cases(), static fn (ErrorCode $code): bool => $code->bodyOutcome() === 'HELD'),
        )));
    }

    public function test_a_refusal_names_only_a_message_key_of_its_code(): void
    {
        $this->assertSame('ux.state.session_closed.refund', (new CommandRefused(ErrorCode::SessionNotOpen, messageKey: 'ux.state.session_closed.refund'))->messageKey);
        $this->assertSame('ux.state.recount', CommandRefused::of(ErrorCode::RecountRequired)->messageKey);
        $this->assertThrows(fn () => new CommandRefused(ErrorCode::SessionNotOpen, messageKey: 'ux.state.forbidden'), InvalidArgumentException::class);
    }
}
