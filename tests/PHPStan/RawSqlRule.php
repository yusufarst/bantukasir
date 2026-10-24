<?php

namespace Tests\PHPStan;

use App\Database\Identifier;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

/**
 * TR-VAL-07: SQL is never built from input, and every parameter is bound. The
 * SQL of a raw query method must be a constant string — a literal, a constant
 * or a concatenation of them — and a placeholder in it needs its bindings.
 * Since TASK-004 it may also concatenate, at the call, names quoted by
 * App\Database\Identifier::quote(), for the grants that name roles.
 *
 * @implements Rule<Node\Expr\CallLike>
 */
final class RawSqlRule implements Rule
{
    /**
     * Methods whose first argument is always SQL, with whether they take
     * bindings.
     */
    private const RAW_METHODS = [
        'raw' => false,
        'selectraw' => true,
        'whereraw' => true,
        'orwhereraw' => true,
        'havingraw' => true,
        'orhavingraw' => true,
        'orderbyraw' => true,
        'groupbyraw' => true,
        'fromraw' => true,
    ];

    /**
     * Methods of a database connection, and of the DB facade, whose first
     * argument is SQL, with whether they take bindings.
     */
    private const CONNECTION_METHODS = [
        'select' => true,
        'selectone' => true,
        'selectresultsets' => true,
        'scalar' => true,
        'cursor' => true,
        'insert' => true,
        'update' => true,
        'delete' => true,
        'statement' => true,
        'affectingstatement' => true,
        'unprepared' => false,
    ];

    public function getNodeType(): string
    {
        return Node\Expr\CallLike::class;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if ($node->isFirstClassCallable()) {
            return [];
        }

        $takesBindings = $this->takesBindings($node, $scope);

        if ($takesBindings === null) {
            return [];
        }

        $args = $node->getArgs();

        if ($args === []) {
            return [];
        }

        $sqlType = $scope->getType($args[0]->value);

        if ($sqlType->isString()->no()) {
            return [];
        }

        $strings = array_map(fn ($string): string => $string->getValue(), $sqlType->getConstantStrings());

        if ($strings === []) {
            $strings = $this->identifierConcatenation($args[0]->value, $scope);
        }

        if ($strings === null || $strings === []) {
            return [$this->error('SQL built from a value that is not a constant string (TR-VAL-07); pass input as a binding, or a name through Identifier::quote().')];
        }

        foreach ($strings as $string) {
            if ($this->hasPlaceholder($string) && (! $takesBindings || count($args) < 2)) {
                return [$this->error('SQL with a placeholder and no bindings (TR-VAL-07).')];
            }
        }

        return [];
    }

    /**
     * PostgreSQL takes no binding for a name — a role, a table, a column. SQL
     * may therefore also be a concatenation, written at the call, of constant
     * strings and of names passed through App\Database\Identifier::quote(),
     * which accepts only the identifiers of this schema and quotes them
     * (TASK-004). Returns the SQL with each quoted name replaced by a
     * neutral identifier, for the placeholder check; null for anything else.
     *
     * @return list<string>|null
     */
    private function identifierConcatenation(Node\Expr $expression, Scope $scope): ?array
    {
        if ($expression instanceof Node\Expr\BinaryOp\Concat) {
            $left = $this->identifierConcatenation($expression->left, $scope);
            $right = $this->identifierConcatenation($expression->right, $scope);

            if ($left === null || $right === null) {
                return null;
            }

            $joined = [];

            foreach ($left as $start) {
                foreach ($right as $end) {
                    $joined[] = $start.$end;
                }
            }

            return $joined;
        }

        if ($expression instanceof Node\Expr\StaticCall
            && $expression->class instanceof Node\Name
            && $scope->resolveName($expression->class) === Identifier::class
            && $expression->name instanceof Node\Identifier
            && $expression->name->toLowerString() === 'quote') {
            return ['"identifier"'];
        }

        $strings = $scope->getType($expression)->getConstantStrings();

        return $strings === [] ? null : array_map(fn ($string): string => $string->getValue(), $strings);
    }

    /**
     * Whether the call takes raw SQL, and if so whether it takes bindings.
     */
    private function takesBindings(Node\Expr\CallLike $node, Scope $scope): ?bool
    {
        if ($node instanceof Node\Expr\New_) {
            return $node->class instanceof Node\Name
                && $scope->resolveName($node->class) === Expression::class ? false : null;
        }

        $name = match (true) {
            $node instanceof Node\Expr\MethodCall,
            $node instanceof Node\Expr\NullsafeMethodCall,
            $node instanceof Node\Expr\StaticCall => $node->name instanceof Node\Identifier ? $node->name->toLowerString() : null,
            default => null,
        };

        if ($name === null) {
            return null;
        }

        if (array_key_exists($name, self::RAW_METHODS)) {
            return self::RAW_METHODS[$name];
        }

        if (array_key_exists($name, self::CONNECTION_METHODS) && $this->isConnection($node, $scope)) {
            return self::CONNECTION_METHODS[$name];
        }

        return null;
    }

    private function isConnection(Node\Expr\CallLike $node, Scope $scope): bool
    {
        if ($node instanceof Node\Expr\StaticCall) {
            return $node->class instanceof Node\Name
                && in_array($scope->resolveName($node->class), [DB::class, 'DB'], true);
        }

        if ($node instanceof Node\Expr\MethodCall || $node instanceof Node\Expr\NullsafeMethodCall) {
            return (new ObjectType(ConnectionInterface::class))->isSuperTypeOf($scope->getType($node->var))->yes();
        }

        return false;
    }

    private function hasPlaceholder(string $sql): bool
    {
        // Quoted SQL literals are not placeholders; nor is a PostgreSQL cast (::).
        $code = (string) preg_replace("/'(?:[^']|'')*'/", "''", $sql);

        return str_contains($code, '?') || preg_match('/(?<![:\w]):[A-Za-z_]\w*/', $code) === 1;
    }

    private function error(string $message): IdentifierRuleError
    {
        return RuleErrorBuilder::message($message)
            ->identifier('bantukasir.rawSql')
            ->nonIgnorable()
            ->build();
    }
}
