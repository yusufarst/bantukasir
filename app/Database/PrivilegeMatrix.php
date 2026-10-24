<?php

namespace App\Database;

use InvalidArgumentException;

/**
 * The privilege matrix of database/privileges.php (SCHEMA section 10, EXC-43),
 * read and checked: every table names its class of SCHEMA section 4, and the
 * runtime role's privileges stay within what that class allows — a retained
 * fact is never updated or deleted by the runtime role (SCH-02), the security
 * log is never updated, and only a projection or ephemeral data is deleted.
 *
 * @phpstan-type Runtime array{select: bool, insert: bool, update: bool|list<string>, delete: bool}
 * @phpstan-type Backup array{select: true, insert: bool}
 * @phpstan-type Table array{class: string|null, runtime: Runtime, backup: Backup}
 */
final class PrivilegeMatrix
{
    /**
     * What the runtime role may hold per class of SCHEMA section 4: update
     * "columns" means a list of columns only, "any" a list or every column.
     */
    private const array CLASS_BOUNDS = [
        'F' => ['select' => true, 'insert' => true, 'update' => false, 'delete' => false],
        'FS' => ['select' => true, 'insert' => true, 'update' => 'columns', 'delete' => false],
        'M' => ['select' => true, 'insert' => true, 'update' => 'columns', 'delete' => false],
        'S' => ['select' => true, 'insert' => true, 'update' => 'columns', 'delete' => false],
        'P' => ['select' => true, 'insert' => true, 'update' => 'columns', 'delete' => true],
        'L' => ['select' => true, 'insert' => true, 'update' => false, 'delete' => true],
        'E' => ['select' => true, 'insert' => true, 'update' => 'any', 'delete' => true],
    ];

    /**
     * @param  array<string, Table>  $tables
     * @param  array<string, bool>  $sequenceUsage
     */
    private function __construct(
        private readonly array $tables,
        private readonly array $sequenceUsage,
    ) {}

    public static function load(?string $path = null): self
    {
        $path ??= database_path('privileges.php');
        $matrix = require $path;

        if (! is_array($matrix) || array_keys($matrix) !== ['tables', 'sequences'] || ! is_array($matrix['tables']) || ! is_array($matrix['sequences'])) {
            throw new InvalidArgumentException('The privilege matrix holds exactly "tables" and "sequences".');
        }

        $tables = [];

        foreach ($matrix['tables'] as $name => $entry) {
            $tables[self::name($name)] = self::parseTable(self::name($name), $entry);
        }

        $sequences = [];

        foreach ($matrix['sequences'] as $name => $entry) {
            if (! is_array($entry) || array_keys($entry) !== ['runtime'] || ! is_array($entry['runtime']) || ! in_array(array_keys($entry['runtime']), [[], ['usage']], true)) {
                throw new InvalidArgumentException(sprintf('The sequence %s needs "runtime" with at most "usage".', self::name($name)));
            }

            $sequences[self::name($name)] = ($entry['runtime']['usage'] ?? false) === true;
        }

        ksort($tables);
        ksort($sequences);

        return new self($tables, $sequences);
    }

    /**
     * @return array<string, Table>
     */
    public function tables(): array
    {
        return $this->tables;
    }

    public function has(string $table): bool
    {
        return array_key_exists($table, $this->tables);
    }

    /**
     * @return Table
     */
    public function table(string $name): array
    {
        return $this->tables[$name] ?? throw new InvalidArgumentException(sprintf('The privilege matrix has no table %s.', $name));
    }

    /**
     * The sequences the matrix lists, with whether the runtime role uses them.
     *
     * @return array<string, bool>
     */
    public function sequences(): array
    {
        return $this->sequenceUsage;
    }

    /**
     * The table-level privileges that a logical role holds on a table, as
     * PostgreSQL names them (aclexplode), in a fixed order.
     *
     * @return list<string>
     */
    public function tablePrivileges(string $table, string $kind): array
    {
        $entry = $this->table($table);
        $privileges = match ($kind) {
            'runtime' => $entry['runtime'],
            'backup' => $entry['backup'] + ['update' => false, 'delete' => false],
            default => throw new InvalidArgumentException(sprintf('No table privileges for the %s role.', $kind)),
        };

        $names = [];

        foreach (['select' => 'SELECT', 'insert' => 'INSERT', 'update' => 'UPDATE', 'delete' => 'DELETE'] as $key => $privilege) {
            if ($privileges[$key] === true) {
                $names[] = $privilege;
            }
        }

        return $names;
    }

    /**
     * The columns whose UPDATE the runtime role holds column by column, in
     * the matrix's order; empty when it updates every column or none.
     *
     * @return list<string>
     */
    public function updateColumns(string $table): array
    {
        $update = $this->table($table)['runtime']['update'];

        return is_array($update) ? $update : [];
    }

    /**
     * @return Table
     */
    private static function parseTable(string $name, mixed $entry): array
    {
        if (! is_array($entry) || ! array_key_exists('class', $entry) || ! array_key_exists('runtime', $entry)
            || array_diff(array_keys($entry), ['class', 'runtime', 'backup']) !== []) {
            throw new InvalidArgumentException(sprintf('The table %s needs "class" and "runtime", and at most "backup" besides.', $name));
        }

        $class = $entry['class'];

        if ($class !== null && ! array_key_exists($class, self::CLASS_BOUNDS)) {
            throw new InvalidArgumentException(sprintf('The table %s has the class %s, not one of SCHEMA section 4.', $name, json_encode($class)));
        }

        $runtime = self::runtime($name, $entry['runtime']);
        $backup = self::backup($name, $entry['backup'] ?? []);

        self::within($name, $class, $runtime);

        return ['class' => $class, 'runtime' => $runtime, 'backup' => $backup];
    }

    /**
     * @return Runtime
     */
    private static function runtime(string $table, mixed $runtime): array
    {
        if (! is_array($runtime) || array_diff(array_keys($runtime), ['select', 'insert', 'update', 'delete']) !== []) {
            throw new InvalidArgumentException(sprintf('The runtime privileges of %s are select, insert, update and delete only.', $table));
        }

        foreach (['select', 'insert', 'delete'] as $key) {
            if (array_key_exists($key, $runtime) && $runtime[$key] !== true) {
                throw new InvalidArgumentException(sprintf('The runtime privilege %s of %s is true or absent.', $key, $table));
            }
        }

        $update = $runtime['update'] ?? false;

        if (is_array($update)) {
            if ($update === [] || ! array_is_list($update) || count(array_unique($update)) !== count($update)) {
                throw new InvalidArgumentException(sprintf('The update columns of %s are a non-empty list without repeats.', $table));
            }

            $update = array_map(self::name(...), $update);
        } elseif ($update !== true && $update !== false) {
            throw new InvalidArgumentException(sprintf('The runtime privilege update of %s is true, absent or a list of columns.', $table));
        }

        return [
            'select' => ($runtime['select'] ?? false) === true,
            'insert' => ($runtime['insert'] ?? false) === true,
            'update' => $update,
            'delete' => ($runtime['delete'] ?? false) === true,
        ];
    }

    /**
     * @return Backup
     */
    private static function backup(string $table, mixed $backup): array
    {
        if (! is_array($backup) || array_diff(array_keys($backup), ['insert']) !== [] || (array_key_exists('insert', $backup) && $backup['insert'] !== true)) {
            throw new InvalidArgumentException(sprintf('The backup privileges of %s are at most insert (SCH-42); it always reads.', $table));
        }

        if (array_key_exists('insert', $backup) && $table !== 'backup_runs') {
            throw new InvalidArgumentException(sprintf('The backup role inserts into backup_runs only, not into %s (SCH-42).', $table));
        }

        return ['select' => true, 'insert' => array_key_exists('insert', $backup)];
    }

    /**
     * @param  Runtime  $runtime
     */
    private static function within(string $table, ?string $class, array $runtime): void
    {
        if ($class === null) {
            if ($runtime !== ['select' => false, 'insert' => false, 'update' => false, 'delete' => false]) {
                throw new InvalidArgumentException(sprintf('The table %s has no class, so the runtime role holds nothing on it.', $table));
            }

            return;
        }

        $bounds = self::CLASS_BOUNDS[$class];
        $update = $runtime['update'];

        $exceeds = ($runtime['delete'] && ! $bounds['delete'])
            || ($update !== false && $bounds['update'] === false)
            || ($update === true && $bounds['update'] === 'columns');

        if ($exceeds) {
            throw new InvalidArgumentException(sprintf('The runtime privileges of %s exceed what its class %s allows (SCHEMA section 4).', $table, $class));
        }
    }

    private static function name(mixed $name): string
    {
        if (! is_string($name) || preg_match('/^[a-z_][a-z0-9_]{0,62}$/D', $name) !== 1) {
            throw new InvalidArgumentException(sprintf('Not a name of this schema: %s', json_encode($name)));
        }

        return $name;
    }
}
