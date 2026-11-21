<?php

namespace Tests\Unit\Audit;

use App\Domain\Audit\AuditActions;
use App\Domain\Audit\SecurityEvent;
use App\Domain\Audit\SecurityOutcome;
use App\Support\Localization\Dictionary;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * AC-042-03 (EXC-51; TASK-042 section 10, an action code without an approved label is a stop): the action codes
 * and their label keys are exactly those of INTERACTION_CONTRACTS section 8, a code that section marks
 * "Proposed (section 11)" taking the key that COPY_DECK section 11.14 gives it (TASK-002); every key is in the
 * dictionary; every code that an Action or a document writes is one of them; and every event type and outcome
 * of the security log has its label (COPY_DECK section 11.13).
 */
final class AuditActionsTest extends TestCase
{
    public function test_the_codes_and_their_labels_are_those_of_interaction_contracts_section_8(): void
    {
        $root = dirname(__DIR__, 3);
        $contracts = (string) file_get_contents($root.'/docs/03-workflows/INTERACTION_CONTRACTS.md');
        $deck = (string) file_get_contents($root.'/docs/07-ux-design/COPY_DECK.md');
        $start = strpos($contracts, '## 8. Audit action codes');
        $this->assertIsInt($start);
        $end = strpos($contracts, '## 9. ', $start);
        $this->assertIsInt($end);
        $expected = [];

        foreach (preg_split('/\R/', substr($contracts, $start, $end - $start)) ?: [] as $line) {
            if (! str_starts_with($line, '| `')) {
                continue;
            }

            $cells = array_map(trim(...), explode('|', trim($line, ' |')));
            preg_match_all('/`([A-Z][A-Z0-9_]*)`/', $cells[0], $codes);
            preg_match_all('/`(audit\.action\.[a-z_]+)`/', $cells[2] ?? '', $keys);

            foreach ($codes[1] as $at => $code) {
                if (count($keys[1]) === count($codes[1])) {
                    $expected[$code] = $keys[1][$at] ?? '';

                    continue;
                }

                // A proposed label: the row of COPY_DECK section 11.14 that names the code.
                $this->assertStringStartsWith('Proposed', $cells[2] ?? '', $code);
                $this->assertSame(1, preg_match('/^\| `(audit\.action\.[a-z_]+)` \| [^|]+ \| `'.$code.'`/m', $deck, $found), "COPY_DECK section 11.14 labels {$code}.");
                $expected[$code] = $found[1] ?? '';
            }
        }

        $this->assertCount(55, $expected);
        $this->assertSame($expected, AuditActions::LABELS);

        $dictionary = Dictionary::fromDirectory($root.'/lang/id');

        foreach (AuditActions::LABELS as $code => $key) {
            $this->assertArrayHasKey($key, $dictionary, $code);
        }
    }

    public function test_every_code_that_the_application_writes_has_its_label(): void
    {
        $written = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 3).'/app'));

        foreach ($files as $file) {
            if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $source = (string) file_get_contents($file->getPathname());
            preg_match_all("/AuditCategory::(?:Business|Security),\\s*'([A-Z][A-Z0-9_]*)'/", $source, $direct);
            preg_match_all("/->audit\\(\\s*'([A-Z][A-Z0-9_]*)'/", $source, $photos);
            preg_match_all("/const string (?:AUDIT_ACTION|PRINT|EXPORT) = '([A-Z][A-Z0-9_]*)'/", $source, $constants);
            array_push($written, ...$direct[1], ...$photos[1], ...(str_contains($file->getPathname(), 'Documents') ? $constants[1] : []));
        }

        $written = array_values(array_unique($written));
        sort($written);
        $this->assertGreaterThanOrEqual(50, count($written), 'The scan finds the codes the Actions write.');

        foreach ($written as $code) {
            $this->assertTrue(AuditActions::isCode($code), "{$code} has its label (INTERACTION_CONTRACTS section 8).");
        }

        $this->assertFalse(AuditActions::isCode('NOT_A_CODE'));
        $this->assertFalse(AuditActions::isCode(null));
    }

    public function test_every_event_type_and_outcome_of_the_security_log_has_its_label(): void
    {
        $dictionary = Dictionary::fromDirectory(dirname(__DIR__, 3).'/lang/id');

        $this->assertCount(9, SecurityEvent::cases());

        foreach (SecurityEvent::cases() as $event) {
            $this->assertArrayHasKey('audit.security.type.'.strtolower($event->value), $dictionary, $event->value);
        }

        foreach (SecurityOutcome::cases() as $outcome) {
            $this->assertArrayHasKey('audit.security.outcome.'.strtolower($outcome->value), $dictionary, $outcome->value);
        }
    }
}
