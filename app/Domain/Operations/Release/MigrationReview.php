<?php

namespace App\Domain\Operations\Release;

/**
 * What a migration's source says of itself (RELEASE_PROCESS REL-06, REL-20;
 * PRODUCTION_DATA_SAFETY section 1; TEST_STRATEGY section 6; TASK-014 scope items
 * 3 and 6), read the same way by the static check scripts/checks/migration-review.php
 * and by release:preflight: which statements remove or rewrite data, its class,
 * its approval references and which of the metadata of REL-20 it lacks. It needs
 * nothing of the framework, because the static check loads this file alone.
 *
 * The metadata is prose in the migration's own comment, which older migrations
 * already hold and a merged migration may never change (EXC-41):
 *
 *     Class: additive (RELEASE_PROCESS section 5). Expected duration: ...
 *     Lock impact: ... Verification (REL-20): ...
 *
 * The approval of a destructive step is its own line, naming the decision of
 * DECISIONS that records the Owner's approval of that exact operation:
 *
 *     // REL-06 approval: D-NNN
 */
final class MigrationReview
{
    public const array CLASSES = ['additive', 'expand', 'contract', 'destructive', 'privilege'];

    public const string DESTRUCTIVE_CLASS = 'destructive';

    /**
     * SQL statements and query-builder calls that remove or rewrite data. These are
     * text patterns, so they fail closed: a statement they mistake for one of these
     * needs its approval or another wording, and the reviewer of SCH-51 reads the rest.
     */
    private const array DESTRUCTIVE_STATEMENTS = [
        'DROP TABLE, SCHEMA, DATABASE or COLUMN' => '/\bDROP\s+(?:TABLE|SCHEMA|DATABASE|COLUMN)\b/i',
        'DROP of an object that holds data, or with CASCADE' => '/\bDROP\s+(?:MATERIALIZED\s+VIEW|FOREIGN\s+TABLE|SEQUENCE|DOMAIN|OWNED)\b|\bDROP\b[^;]*?\bCASCADE\b/is',
        'ALTER TABLE … DROP of a column' => '/\bALTER\s+TABLE\b[^;]*?\bDROP\s+(?!CONSTRAINT\b|NOT\b|DEFAULT\b|IDENTITY\b|EXPRESSION\b)/is',
        'TRUNCATE' => '/\bTRUNCATE\b/i',
        'DELETE FROM' => '/\bDELETE\s+FROM\b/i',
        'UPDATE … SET' => '/\bUPDATE\s+(?:ONLY\s+)?\S+(?:\s+(?:AS\s+)?\S+)?\s+SET\b|\bDO\s+UPDATE\s+SET\b/i',
        'MERGE INTO' => '/\bMERGE\s+INTO\b/i',
        'a change of a column\'s type' => '/\bALTER\s+(?:COLUMN\s+)?\S+\s+(?:SET\s+DATA\s+)?TYPE\b|->change\s*\(/i',
        'a schema-builder drop' => '/(?:Schema::|->)(?:drop|dropIfExists|dropAllTables|dropAllTypes|dropAllViews|dropDatabaseIfExists|dropColumn|dropColumns|dropConstrainedForeignId|dropConstrainedForeignIdFor|dropMorphs|dropNullableMorphs|dropTimestamps|dropTimestampsTz|dropSoftDeletes|dropSoftDeletesTz|dropRememberToken)\s*\(/i',
        'a query-builder delete or update' => '/(?:->|::)(?:delete|deleteQuietly|deleteOrFail|destroy|forceDelete|forceDeleteQuietly|forceDestroy|truncate|update|updateFrom|updateOrCreate|updateOrFail|updateOrInsert|updateQuietly|upsert|increment|decrement|incrementEach|decrementEach)\s*\(/i',
        'DB::delete() or DB::update()' => '/\bDB::(?:delete|update)\s*\(/i',
    ];

    /** A label of the metadata, or the end of the comment: what no value starts with. */
    private const string NO_VALUE = '(?!Class:|Expected duration:|Lock impact:|Verification(?: \(REL-20\))?:|\*\/)';

    /**
     * The descriptions of the statements of this source that remove or rewrite
     * data; every table is treated as retained data, and migrations are forward
     * only (EXC-41), so a down() that drops counts too.
     *
     * @return list<string>
     */
    public static function destructiveStatements(string $code): array
    {
        $found = [];

        foreach (self::DESTRUCTIVE_STATEMENTS as $description => $pattern) {
            if (preg_match($pattern, $code) === 1) {
                $found[] = $description;
            }
        }

        return $found;
    }

    /**
     * The class this migration states, when it is one of RELEASE_PROCESS section 5.
     */
    public static function classOf(string $code): ?string
    {
        if (preg_match('/\bClass:\s*('.implode('|', self::CLASSES).')\b/', self::prose($code), $match) !== 1) {
            return null;
        }

        return $match[1];
    }

    /**
     * Whether it needs an approval: a destructive statement, or the mark itself.
     */
    public static function isDestructive(string $code): bool
    {
        return self::classOf($code) === self::DESTRUCTIVE_CLASS || self::destructiveStatements($code) !== [];
    }

    /**
     * The decisions that its REL-06 approval lines name.
     *
     * @return list<string>
     */
    public static function approvals(string $code): array
    {
        preg_match_all('/^\s*\/\/\s*REL-06 approval:\s*(D-\d{3,})\s*$/m', $code, $references);

        return $references[1];
    }

    /**
     * Which of the metadata of REL-20 this migration lacks.
     *
     * @return list<string>
     */
    public static function missingMetadata(string $code): array
    {
        $prose = self::prose($code);
        $missing = [];

        if (self::classOf($code) === null) {
            $missing[] = 'class';
        }

        foreach ([
            'expected duration' => '/\bExpected duration:\s*'.self::NO_VALUE.'\S/',
            'lock impact' => '/\bLock impact:\s*'.self::NO_VALUE.'\S/',
            'verification queries' => '/\bVerification(?: \(REL-20\))?:\s*'.self::NO_VALUE.'\S/',
        ] as $name => $pattern) {
            if (preg_match($pattern, $prose) !== 1) {
                $missing[] = $name;
            }
        }

        return $missing;
    }

    /**
     * The source with its line breaks and comment margins as single spaces, so
     * that a label or a value may run over a line of a comment.
     */
    private static function prose(string $code): string
    {
        return preg_replace('/\s*\R\s*(?:\*(?!\/)|\/\/|#)?\s*/', ' ', $code) ?? $code;
    }
}
