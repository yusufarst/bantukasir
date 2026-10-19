<?php

namespace Tests\Unit\Localization;

use App\Support\Localization\Dictionary;
use PHPUnit\Framework\TestCase;

/**
 * L10N01 (AC-003-06; EXC-51): every key of lang/id is named by COPY_DECK, as
 * approved at gate P7-G2 (D-086), and holds its text verbatim. The month and
 * day names are the rows "Months" and "Days" of COPY_DECK section 1.3, under
 * `ux.format.month.*` and `ux.format.day.*` with English leaves.
 */
final class CopyDeckTest extends TestCase
{
    private const MONTHS = ['january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december'];

    private const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    public function test_every_text_of_lang_id_is_the_copy_deck_text_of_its_key(): void
    {
        $deck = (string) file_get_contents(dirname(__DIR__, 3).'/docs/07-ux-design/COPY_DECK.md');
        $expected = [];

        foreach (Dictionary::fromDirectory(dirname(__DIR__, 3).'/lang/id') as $key => $text) {
            $expected[$key] = match (true) {
                str_starts_with($key, 'ux.format.month.') => $this->listItem($deck, 'Months', self::MONTHS, substr($key, strlen('ux.format.month.'))),
                str_starts_with($key, 'ux.format.day.') => $this->listItem($deck, 'Days', self::DAYS, substr($key, strlen('ux.format.day.'))),
                default => $this->row($deck, $key),
            };
        }

        $this->assertSame($expected, Dictionary::fromDirectory(dirname(__DIR__, 3).'/lang/id'));
        $this->assertNotEmpty($expected);
    }

    /**
     * The copy cell of the row `| `key` | copy | ... |`.
     */
    private function row(string $deck, string $key): string
    {
        $found = preg_match_all('/^\| `'.preg_quote($key, '/').'` \| ([^|]*) \|/m', $deck, $matches);

        $this->assertSame(1, $found, "COPY_DECK names {$key} in exactly one row.");

        return trim($matches[1][0]);
    }

    /**
     * The name at the leaf's place in a comma-separated row of section 1.3.
     *
     * @param  list<string>  $leaves
     */
    private function listItem(string $deck, string $row, array $leaves, string $leaf): string
    {
        $this->assertSame(1, preg_match('/^\| '.$row.' \| ([^|]*) \|/m', $deck, $matches), "COPY_DECK has the row {$row}.");
        $names = array_map(trim(...), explode(',', $matches[1]));
        $index = array_search($leaf, $leaves, true);

        $this->assertCount(count($leaves), $names);
        $this->assertIsInt($index, "{$leaf} is one of the {$row}.");

        return $names[$index];
    }
}
