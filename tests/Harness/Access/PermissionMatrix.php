<?php

namespace Tests\Harness\Access;

use InvalidArgumentException;

/**
 * The permission matrix of SECURITY_MODEL section 6, read from the document
 * itself (TASK-007): for each capability, the cell of the Owner, of
 * OPERATIONS_ADMIN and of CASHIER, as written. The tests compare the
 * application's capability map (App\Domain\Access\Capabilities) and the
 * answers of its gates with it, so that the code can never drift from the
 * approved matrix without a test failing.
 *
 * A cell is read by its words: "N" refuses; "Y", "Y, any" and "Y" with what
 * the Owner sees in addition ("Y, with …") allow fully; "Y, own" allows on the
 * actor's own objects; any other cell — "Y" with a narrowing ("Y, in the
 * till; …", "Y, never …") or a sentence of its own ("Only …") — is a
 * restricted form, which opens no gate by itself.
 */
final class PermissionMatrix
{
    public const string OWNER = 'owner';

    public const string OPS = 'ops';

    public const string CASHIER = 'cashier';

    /**
     * Every row: the capability and its three cells.
     *
     * @return array<string, array{owner: string, ops: string, cashier: string}>
     */
    public static function cells(): array
    {
        $document = file_get_contents(base_path('docs/05-security/SECURITY_MODEL.md'));

        if ($document === false) {
            throw new InvalidArgumentException('SECURITY_MODEL is missing.');
        }

        $section = strstr(strstr($document, '## 6. Permission matrix') ?: '', '## 7.', true);

        if ($section === false || $section === '') {
            throw new InvalidArgumentException('SECURITY_MODEL section 6 is missing.');
        }

        $rows = [];

        foreach (explode("\n", $section) as $line) {
            if (preg_match('/^\| (CAP-[0-9]{2}) \| (.*)$/', $line, $match) !== 1) {
                continue;
            }

            $cells = array_map(trim(...), explode(' | ', $match[2]));
            $rows[$match[1]] = ['owner' => $cells[1] ?? '', 'ops' => $cells[2] ?? '', 'cashier' => $cells[3] ?? ''];
        }

        return $rows;
    }

    /** What a cell grants: full, own, restricted or none (App\Domain\Access\Grant). */
    public static function grantOf(string $cell): string
    {
        return match (true) {
            $cell === 'N' => 'none',
            $cell === 'Y', $cell === 'Y, any', str_starts_with($cell, 'Y, with ') => 'full',
            $cell === 'Y, own' => 'own',
            default => 'restricted',
        };
    }

    /**
     * What a kind of account is granted: the Owner's cell, a staff role's
     * cell, or for the combined account the stronger of the cashier's and
     * Operations' (SEC-AZ-02).
     *
     * @param  array{owner: string, ops: string, cashier: string}  $cells
     */
    public static function grantFor(array $cells, string $actor): string
    {
        if ($actor === 'combined') {
            foreach (['full', 'own', 'restricted'] as $grant) {
                if (self::grantOf($cells['ops']) === $grant || self::grantOf($cells['cashier']) === $grant) {
                    return $grant;
                }
            }

            return 'none';
        }

        return match ($actor) {
            self::OWNER => self::grantOf($cells['owner']),
            self::OPS => self::grantOf($cells['ops']),
            self::CASHIER => self::grantOf($cells['cashier']),
            default => 'none',
        };
    }
}
