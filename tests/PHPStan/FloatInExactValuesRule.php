<?php

namespace Tests\PHPStan;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * ADR-006 item 5: no floating-point type or operation in the money, quantity
 * and cost code of the shared kernel and the domain modules; exact values use
 * Brick Math. The directories come from the parameter exactValueDirectories.
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
            $node instanceof Node\Expr\FuncCall => $this->floatFunction($node),
            $node instanceof Node\FunctionLike => $this->floatSignature($node),
            $node instanceof Node\Stmt\Property, $node instanceof Node\Stmt\ClassConst => $this->hasFloat($node->type) ? 'A float type' : null,
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

    private function floatFunction(Node\Expr\FuncCall $node): ?string
    {
        if (! $node->name instanceof Node\Name) {
            return null;
        }

        $function = strtolower($node->name->getLast());

        return in_array($function, self::FUNCTIONS, true) ? sprintf('%s()', $function) : null;
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
