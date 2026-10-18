<?php

namespace Tests\PHPStan;

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

        $strings = $sqlType->getConstantStrings();

        if ($strings === []) {
            return [$this->error('SQL built from a value that is not a constant string (TR-VAL-07); pass input as a binding.')];
        }

        foreach ($strings as $string) {
            if ($this->hasPlaceholder($string->getValue()) && (! $takesBindings || count($args) < 2)) {
                return [$this->error('SQL with a placeholder and no bindings (TR-VAL-07).')];
            }
        }

        return [];
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
