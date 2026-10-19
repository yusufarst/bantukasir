<?php

namespace Tests\PHPStan;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Type;
use PHPStan\Type\UnionType;

/**
 * ADR-006 item 5: no floating-point type or operation in the money, quantity
 * and cost code of the shared kernel and the domain modules; exact values use
 * Brick Math. The directories come from the parameter exactValueDirectories.
 *
 * TASK-003 widened it after the class-A adversarial review (finding A-02,
 * reading R-33): besides float literals, casts, `/`, `**`, the float functions
 * and calls typed float, it refuses every expression whose type is or holds a
 * float; arithmetic, numeric comparison, increment and negation of a value
 * that is not an integer, through which PHP compares or computes numeric
 * strings as floats; the sort family without SORT_STRING and max() or min()
 * of values that are not integers; a printf format that is not a literal or
 * that converts through a float; settype(); json_decode(); and the name of a
 * float function passed as a callable.
 *
 * @implements Rule<Node>
 */
final class FloatInExactValuesRule implements Rule
{
    /**
     * Functions that take or return floats.
     */
    private const FUNCTIONS = [
        'ceil', 'doubleval', 'exp', 'expm1', 'fdiv', 'floatval', 'floor', 'fmod', 'hypot',
        'log', 'log10', 'log1p', 'microtime', 'number_format', 'pi', 'pow', 'round', 'sqrt',
    ];

    /**
     * Functions that sort with PHP's comparison unless SORT_STRING is given,
     * by the position of their flags argument.
     */
    private const SORTS = ['sort' => 1, 'rsort' => 1, 'asort' => 1, 'arsort' => 1, 'ksort' => 1, 'krsort' => 1, 'array_unique' => 1];

    /**
     * Functions of the printf family, by the position of their format.
     */
    private const PRINTF = ['sprintf' => 0, 'printf' => 0, 'vsprintf' => 0, 'vprintf' => 0, 'fprintf' => 1, 'vfprintf' => 1, 'sscanf' => 1];

    /**
     * @var list<string>
     */
    private array $directories;

    /**
     * @param  list<string>  $directories
     */
    public function __construct(array $directories)
    {
        $this->directories = array_map(
            fn (string $directory): string => rtrim(str_replace('\\', '/', $directory), '/').'/',
            $directories,
        );
    }

    public function getNodeType(): string
    {
        return Node::class;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (! $this->inExactValueCode($scope->getFile())) {
            return [];
        }

        $found = match (true) {
            $node instanceof Node\Scalar\Float_ => 'A float literal',
            $node instanceof Node\Expr\Cast\Double => 'A (float) cast',
            $node instanceof Node\Expr\BinaryOp\Div, $node instanceof Node\Expr\AssignOp\Div => 'Division with /',
            $node instanceof Node\Expr\BinaryOp\Pow, $node instanceof Node\Expr\AssignOp\Pow => 'Exponentiation with **',
            $node instanceof Node\Expr\BinaryOp\Plus, $node instanceof Node\Expr\BinaryOp\Minus,
            $node instanceof Node\Expr\BinaryOp\Mul, $node instanceof Node\Expr\BinaryOp\Mod => $this->notIntegers([$node->left, $node->right], $scope, 'Arithmetic')
                ?? $this->mayOverflow([$node->left, $node->right], $scope),
            $node instanceof Node\Expr\AssignOp\Plus, $node instanceof Node\Expr\AssignOp\Minus,
            $node instanceof Node\Expr\AssignOp\Mul, $node instanceof Node\Expr\AssignOp\Mod => $this->notIntegers([$node->var, $node->expr], $scope, 'Arithmetic')
                ?? $this->mayOverflow([$node->var, $node->expr], $scope),
            $node instanceof Node\Expr\Cast\String_, $node instanceof Node\Expr\Cast\Int_ => $scope->getType($node->expr)->isFloat()->no()
                ? null
                : 'A cast of a value that may be a float',
            $node instanceof Node\Expr\BinaryOp\Smaller, $node instanceof Node\Expr\BinaryOp\SmallerOrEqual,
            $node instanceof Node\Expr\BinaryOp\Greater, $node instanceof Node\Expr\BinaryOp\GreaterOrEqual,
            $node instanceof Node\Expr\BinaryOp\Spaceship, $node instanceof Node\Expr\BinaryOp\Equal,
            $node instanceof Node\Expr\BinaryOp\NotEqual => $this->notIntegers([$node->left, $node->right], $scope, 'A numeric comparison'),
            $node instanceof Node\Expr\PreInc, $node instanceof Node\Expr\PostInc,
            $node instanceof Node\Expr\PreDec, $node instanceof Node\Expr\PostDec => $this->notIntegers([$node->var], $scope, 'An increment or decrement'),
            $node instanceof Node\Expr\UnaryMinus, $node instanceof Node\Expr\UnaryPlus => $this->notIntegers([$node->expr], $scope, 'A sign'),
            $node instanceof Node\Expr\FuncCall => $this->floatFunction($node, $scope) ?? $this->floatValue($node, $scope),
            $node instanceof Node\Arg => $this->floatCallable($node),
            $node instanceof Node\FunctionLike => $this->floatSignature($node),
            $node instanceof Node\Stmt\Property, $node instanceof Node\Stmt\ClassConst => $this->hasFloat($node->type) ? 'A float type' : null,
            $node instanceof Node\Expr\Assign, $node instanceof Node\Expr\AssignRef, $node instanceof Node\Expr\AssignOp => null,
            $node instanceof Node\Expr => $this->floatValue($node, $scope),
            default => null,
        };

        if ($found === null) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf('%s in exact-value code (ADR-006 item 5); use Brick Math.', $found))
                ->identifier('bantukasir.floatInExactValue')
                ->nonIgnorable()
                ->build(),
        ];
    }

    private function inExactValueCode(string $file): bool
    {
        $file = str_replace('\\', '/', $file);

        foreach ($this->directories as $directory) {
            if (str_starts_with($file, $directory)) {
                return true;
            }
        }

        return false;
    }

    /**
     * An operation on an operand that is not an integer: PHP computes and
     * compares numeric strings as numbers, through a float for a decimal.
     *
     * @param  list<Node\Expr>  $operands
     */
    private function notIntegers(array $operands, Scope $scope, string $operation): ?string
    {
        foreach ($operands as $operand) {
            if (! $scope->getType($operand)->isInteger()->yes()) {
                return sprintf('%s of a value that is not an integer', $operation);
            }
        }

        return null;
    }

    /**
     * Integer arithmetic next to the ends of the integer range, such as
     * PHP_INT_MAX + $units, which PHP turns into a float on overflow.
     *
     * @param  list<Node\Expr>  $operands
     */
    private function mayOverflow(array $operands, Scope $scope): ?string
    {
        foreach ($operands as $operand) {
            foreach ($scope->getType($operand)->getConstantScalarValues() as $value) {
                if (is_int($value) && ($value >= 2 ** 62 || $value <= -(2 ** 62))) {
                    return 'Integer arithmetic that may overflow into a float';
                }
            }
        }

        return null;
    }

    /**
     * A named function that is a float operation in exact-value code.
     */
    private function floatFunction(Node\Expr\FuncCall $node, Scope $scope): ?string
    {
        if (! $node->name instanceof Node\Name) {
            return null;
        }

        $function = strtolower($node->name->getLast());
        $arguments = array_values(array_filter($node->args, fn (Node $argument): bool => $argument instanceof Node\Arg));

        return match (true) {
            in_array($function, self::FUNCTIONS, true) => sprintf('%s()', $function),
            in_array($function, ['settype', 'json_decode'], true) => sprintf('%s(), which may give a float', $function),
            array_key_exists($function, self::SORTS) => $this->sortsAsStrings($arguments[self::SORTS[$function]] ?? null, $scope, $function === 'array_unique')
                ? null
                : sprintf('%s() without SORT_STRING', $function),
            in_array($function, ['max', 'min'], true) => $this->allIntegers($arguments, $scope) ? null : sprintf('%s() of values that are not integers', $function),
            array_key_exists($function, self::PRINTF) => $this->floatFormat($arguments[self::PRINTF[$function]] ?? null, $scope),
            default => null,
        };
    }

    /**
     * Whether a sort flag is SORT_STRING, alone or with SORT_FLAG_CASE; an
     * absent flag sorts as strings only for array_unique().
     */
    private function sortsAsStrings(?Node\Arg $flag, Scope $scope, bool $absentIsString): bool
    {
        if ($flag === null) {
            return $absentIsString;
        }

        $values = array_map(fn (Type $value): mixed => $value->getValue(), $scope->getType($flag->value)->getConstantScalarTypes());

        return $values !== [] && array_all($values, fn (mixed $value): bool => in_array($value, [SORT_STRING, SORT_STRING | SORT_FLAG_CASE], true));
    }

    /**
     * Whether every argument, or every value of an array argument, is an integer.
     *
     * @param  list<Node\Arg>  $arguments
     */
    private function allIntegers(array $arguments, Scope $scope): bool
    {
        return $arguments !== [] && array_all($arguments, function (Node\Arg $argument) use ($scope): bool {
            $type = $scope->getType($argument->value);

            return $type->isInteger()->yes() || ($type->isArray()->yes() && $type->getIterableValueType()->isInteger()->yes());
        });
    }

    /**
     * A printf format that is not a literal, or that converts through a
     * float (%e, %E, %f, %F, %g, %G, %h, %H).
     */
    private function floatFormat(?Node\Arg $format, Scope $scope): ?string
    {
        $formats = $format === null ? [] : $scope->getType($format->value)->getConstantStrings();

        if ($formats === []) {
            return 'A printf format that is not a literal';
        }

        foreach ($formats as $text) {
            if (preg_match('/%(?:[0-9]+\$)?[-+ 0]*(?:\'.)?[-+ 0]*[0-9]*(?:\.[0-9]+)?[eEfFgGhH]/', str_replace('%%', '', $text->getValue())) === 1) {
                return 'A printf format that converts through a float';
            }
        }

        return null;
    }

    /**
     * The name of a float function passed as a callable, such as
     * array_map('floatval', …).
     */
    private function floatCallable(Node\Arg $argument): ?string
    {
        return $argument->value instanceof Node\Scalar\String_ && in_array(strtolower($argument->value->value), self::FUNCTIONS, true)
            ? sprintf('The float function %s() as a callable', strtolower($argument->value->value))
            : null;
    }

    /**
     * An expression whose type is a float, may be one, or holds one in an
     * array — a call typed float or int|float such as Brick Math's toFloat()
     * or Carbon's differences, a float constant, a numeric-string sum.
     */
    private function floatValue(Node\Expr $node, Scope $scope): ?string
    {
        return $this->holdsFloat($scope->getType($node)) ? 'A value that is or holds a float' : null;
    }

    private function holdsFloat(Type $type, int $depth = 0): bool
    {
        $members = $type instanceof UnionType ? $type->getTypes() : [$type];

        if (array_any($members, fn (Type $member): bool => $member->isFloat()->yes())) {
            return true;
        }

        return $depth < 3 && $type->isArray()->yes() && $this->holdsFloat($type->getIterableValueType(), $depth + 1);
    }

    private function floatSignature(Node\FunctionLike $node): ?string
    {
        foreach ($node->getParams() as $param) {
            if ($this->hasFloat($param->type)) {
                return 'A float parameter';
            }
        }

        return $this->hasFloat($node->getReturnType()) ? 'A float return type' : null;
    }

    private function hasFloat(?Node $type): bool
    {
        return match (true) {
            $type instanceof Node\Identifier => $type->toLowerString() === 'float',
            $type instanceof Node\NullableType => $this->hasFloat($type->type),
            $type instanceof Node\UnionType, $type instanceof Node\IntersectionType => array_any(
                $type->types,
                fn (Node $member): bool => $this->hasFloat($member),
            ),
            default => false,
        };
    }
}
