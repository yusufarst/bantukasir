<?php

namespace Tests\PHPStan;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * THR-10: no user input ever reaches a shell. The functions that run a shell
 * command, a string-command proc_open() and backticks are refused; an external
 * program is started through the framework's process API with an argument list.
 *
 * @implements Rule<Node\Expr>
 */
final class ForbiddenProcessFunctionRule implements Rule
{
    private const SHELL_FUNCTIONS = ['exec', 'shell_exec', 'system', 'passthru', 'popen'];

    private const CALLBACK_FUNCTIONS = ['call_user_func', 'call_user_func_array'];

    public function getNodeType(): string
    {
        return Node\Expr::class;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if ($node instanceof Node\Expr\ShellExec) {
            return [$this->error('Backticks run a shell command (THR-10).')];
        }

        if (! $node instanceof Node\Expr\FuncCall) {
            return [];
        }

        foreach ($this->calledFunctions($node, $scope) as $function) {
            if (in_array($function, self::SHELL_FUNCTIONS, true)) {
                return [$this->error(sprintf('%s() runs a shell command (THR-10).', $function))];
            }

            if ($function === 'proc_open' && ! $this->commandIsList($node, $scope)) {
                return [$this->error('proc_open() with a string command runs a shell (THR-10).')];
            }
        }

        return [];
    }

    /**
     * The lower-case names of the functions the call may run: its own name, or
     * the constant name it is given through a variable or a callback function.
     *
     * @return list<string>
     */
    private function calledFunctions(Node\Expr\FuncCall $node, Scope $scope): array
    {
        if (! $node->name instanceof Node\Name) {
            return $this->constantNames($node->name, $scope);
        }

        $function = strtolower($node->name->getLast());

        if (in_array($function, self::CALLBACK_FUNCTIONS, true) && ! $node->isFirstClassCallable()) {
            $args = $node->getArgs();

            return $args === [] ? [] : $this->constantNames($args[0]->value, $scope);
        }

        return [$function];
    }

    /**
     * @return list<string>
     */
    private function constantNames(Node\Expr $expr, Scope $scope): array
    {
        $names = [];

        foreach ($scope->getType($expr)->getConstantStrings() as $string) {
            $name = explode('\\', strtolower($string->getValue()));
            $names[] = end($name);
        }

        return $names;
    }

    private function commandIsList(Node\Expr\FuncCall $node, Scope $scope): bool
    {
        if ($node->isFirstClassCallable()) {
            return false;
        }

        $args = $node->getArgs();

        return $args !== [] && $scope->getType($args[0]->value)->isArray()->yes();
    }

    private function error(string $message): IdentifierRuleError
    {
        return RuleErrorBuilder::message($message)
            ->identifier('bantukasir.processFunction')
            ->nonIgnorable()
            ->build();
    }
}
