<?php

namespace Tests\Unit\Commands;

use App\Domain\Commands\Operations;
use App\Support\Localization\Dictionary;
use Tests\TestCase;

/**
 * The client's table of families (TASK-009; INTERACTION_CONTRACTS section
 * 1.4; NAVIGATION_CONTRACTS NC-13) equals the server's list of operations,
 * so that the block of a family and the labels of UXP-006 name what the
 * server names; and every family but print has its label and its text of
 * what must not happen meanwhile in lang/id (COPY_DECK section 14.1).
 */
final class ClientFamiliesTest extends TestCase
{
    public function test_the_clients_families_equal_the_servers(): void
    {
        $source = (string) file_get_contents(base_path('resources/js/lib/commands/families.ts'));
        $this->assertSame(1, preg_match('/export const OPERATION_FAMILIES = \{(.*?)\} as const;/s', $source, $table));
        preg_match_all("/^\\s*'([a-z_.]+)': '([a-z_]+)',$/m", $table[1] ?? '', $rows, PREG_SET_ORDER);

        $client = [];

        foreach ($rows as $row) {
            $client[$row[1]] = $row[2];
        }

        $server = Operations::FAMILIES;
        ksort($client);
        ksort($server);

        $this->assertSame($server, $client);
    }

    public function test_every_family_but_print_has_its_label_and_panel_text(): void
    {
        $dictionary = Dictionary::forInterface();
        $families = array_values(array_unique(array_filter(Operations::FAMILIES, static fn (string $family): bool => $family !== Operations::EXEMPT_FAMILY)));
        $source = (string) file_get_contents(base_path('resources/js/lib/commands/families.ts'));

        $this->assertCount(22, $families);

        foreach ($families as $family) {
            $this->assertArrayHasKey('commands.family.'.$family, $dictionary, $family);
            $this->assertStringContainsString(sprintf("%s: 'commands.family.%s',", $family, $family), $source, $family);
            $this->assertMatchesRegularExpression(sprintf("/^\\s*%s: 'commands\\.family\\.[a-z_]+\\.must_not',$/m", $family), $source, $family);
        }

        preg_match_all("/'(commands\\.family\\.[a-z_]+\\.must_not)'/", $source, $texts);

        foreach (array_unique($texts[1]) as $key) {
            $this->assertArrayHasKey($key, $dictionary, $key);
        }
    }
}
