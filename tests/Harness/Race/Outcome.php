<?php

namespace Tests\Harness\Race;

use RuntimeException;

/**
 * How a worker ended (TS-20): its result, or its failure with the SQLSTATE of
 * a database refusal, and every point it passed or reached, in order.
 */
final class Outcome
{
    /**
     * @param  array<string, mixed>|null  $result
     * @param  array{class: string, sqlstate: string|null, message: string}|null  $failure
     * @param  list<string>  $points
     */
    private function __construct(
        public readonly ?array $result,
        public readonly ?array $failure,
        public readonly array $points,
    ) {}

    /**
     * @param  list<array{string, string}>  $lines
     */
    public static function of(array $lines, string $errors): self
    {
        $points = [];
        $result = null;
        $failure = null;

        foreach ($lines as [$word, $payload]) {
            match ($word) {
                'PASSED', 'REACHED' => $points[] = $payload,
                'DONE' => $result = self::object($payload),
                'FAILED' => $failure = self::failure($payload),
                default => null,
            };
        }

        if ($result === null && $failure === null) {
            throw new RuntimeException('The worker ended without a result: '.json_encode(['lines' => $lines, 'stderr' => $errors]));
        }

        return new self($result, $failure, $points);
    }

    public function succeeded(): bool
    {
        return $this->result !== null;
    }

    public function sqlstate(): ?string
    {
        return $this->failure['sqlstate'] ?? null;
    }

    public function failureClass(): ?string
    {
        return $this->failure['class'] ?? null;
    }

    public function failureMessage(): ?string
    {
        return $this->failure['message'] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function object(string $payload): array
    {
        $value = json_decode($payload, true, 16, JSON_THROW_ON_ERROR);

        if (! is_array($value)) {
            throw new RuntimeException('The worker returned a result that is not an object: '.$payload);
        }

        $object = [];

        foreach ($value as $key => $item) {
            $object[(string) $key] = $item;
        }

        return $object;
    }

    /**
     * @return array{class: string, sqlstate: string|null, message: string}
     */
    private static function failure(string $payload): array
    {
        $value = self::object($payload);

        return [
            'class' => is_string($value['class'] ?? null) ? $value['class'] : '',
            'sqlstate' => is_string($value['sqlstate'] ?? null) ? $value['sqlstate'] : null,
            'message' => is_string($value['message'] ?? null) ? $value['message'] : '',
        ];
    }
}
