<?php

namespace App\Http\Requests\Commands;

use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\Envelope;
use App\Domain\Commands\ErrorCode;
use App\Domain\Commands\Operations;
use App\Domain\Commands\RecoveryEpoch;
use Illuminate\Contracts\Validation\Validator;
use LogicException;

/**
 * The FormRequest base of every command (INTERACTION_CONTRACTS section 1.1
 * step 3; ADR-007 section 1; TR-VAL-01 to TR-VAL-07; TASK-008 scope item 2):
 * one JSON body of exactly the envelope's seven fields — `operation`, equal
 * to the operation of the path; `version`, the integer 1; `sourceIntentId`
 * and `idempotencyKey`, UUIDs; `recoveryEpoch`, as the page received it;
 * `expected`, the versions the user reviewed; `payload`, the business fields
 * — and nothing else: an unknown or missing field is refused, never trimmed.
 * The body is at most 1 MiB (ROUTE_CONTRACTS RT-12).
 *
 * Each command's request names the rules of its payload and its expected
 * versions, the message keys of its rules, and checks its object scope
 * (SEC-AZ-03, answering NOT_FOUND). A refusal here takes no lock and writes
 * no fact; the pipeline marks the request's receipt REJECTED.
 */
abstract class CommandRequest extends RefusingRequest
{
    /** RT-12: a JSON body of at most 1 MiB. */
    public const int MAX_BODY_BYTES = 1048576;

    /** The envelope's fields (INTERACTION_CONTRACTS section 1.1). */
    public const array ENVELOPE = ['operation', 'version', 'sourceIntentId', 'idempotencyKey', 'recoveryEpoch', 'expected', 'payload'];

    /**
     * The operation of the route: the envelope's operation must equal it.
     */
    abstract public function operation(): string;

    /**
     * The rules of the payload, by path below `payload`.
     *
     * @return array<string, mixed>
     */
    abstract protected function payloadRules(): array;

    /**
     * The rules of the expected versions, by path below `expected`; none by
     * default, where a command names none (an empty object).
     *
     * @return array<string, mixed>
     */
    protected function expectedRules(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'operation' => ['required', 'string', 'in:'.$this->operation()],
            'version' => ['required', 'integer', 'in:'.Operations::VERSION],
            'sourceIntentId' => ['required', 'string', 'uuid'],
            'idempotencyKey' => ['required', 'string', 'uuid'],
            'recoveryEpoch' => ['required', 'string', 'regex:/^(?:[0-9a-f]{2}){'.RecoveryEpoch::MIN_BYTES.','.RecoveryEpoch::MAX_BYTES.'}$/'],
            'expected' => ['present', 'array'],
            'payload' => ['present', 'array'],
        ];

        foreach ($this->expectedRules() as $path => $rule) {
            $rules['expected.'.$path] = $rule;
        }

        foreach ($this->payloadRules() as $path => $rule) {
            $rules['payload.'.$path] = $rule;
        }

        return $rules;
    }

    /**
     * Every path of the rules is accepted, and nothing else.
     *
     * @return list<string>
     */
    protected function allowed(): array
    {
        return array_map(strval(...), array_keys($this->rules()));
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            ...parent::after(),
            function (Validator $validator): void {
                // The version is the JSON number 1, never a text or a decimal.
                // No floating point anywhere: numbers travel as canonical
                // strings or integers (TR-VAL-06, BR-NUM-04; review finding R-08).
                if ($this->input('version') !== Operations::VERSION
                    || ! is_array($this->input('expected')) || ! is_array($this->input('payload'))
                    || self::holdsFloat($this->input('expected')) || self::holdsFloat($this->input('payload'))
                    || strlen($this->getContent()) > self::MAX_BODY_BYTES) {
                    $validator->errors()->add('', '');
                }
            },
        ];
    }

    /**
     * The validated and normalized envelope: the UUIDs in lower case, the
     * payload and the expected versions as validated.
     */
    public function envelope(): Envelope
    {
        $input = $this->validatedInput();
        $expected = $input['expected'] ?? [];
        $payload = $input['payload'] ?? [];

        if (! is_array($expected) || ! is_array($payload) || ! is_string($input['sourceIntentId'] ?? null)
            || ! is_string($input['idempotencyKey'] ?? null) || ! is_string($input['recoveryEpoch'] ?? null)) {
            throw new LogicException('The envelope was validated before it is read.');
        }

        return new Envelope(
            $this->operation(),
            Operations::VERSION,
            strtolower($input['sourceIntentId']),
            strtolower($input['idempotencyKey']),
            $input['recoveryEpoch'],
            $expected,
            $payload,
        );
    }

    private static function holdsFloat(mixed $value): bool
    {
        if (is_float($value)) {
            return true;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if (self::holdsFloat($item)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Refuses NOT_FOUND an object outside the actor's scope or missing
     * (TR-ERR-03); for each command's own checks.
     */
    protected static function notFound(): CommandRefused
    {
        return CommandRefused::of(ErrorCode::NotFound);
    }
}
