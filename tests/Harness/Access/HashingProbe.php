<?php

namespace Tests\Harness\Access;

use App\Domain\Access\Accounts;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Hashing\Hasher;
use ReflectionClass;
use ReflectionProperty;

/**
 * The application's hasher, recording the password work of a request
 * (TASK-006, the decoy hash; evidence section 13): how many hashes it makes
 * and which hashes it checks. install() puts it in the container in place of
 * the configured hasher, which still does the work; Accounts, resolved anew
 * for each request, receives it.
 */
final class HashingProbe implements Hasher
{
    public int $made = 0;

    /** @var list<string> the hashes checked, in order */
    public array $checked = [];

    private function __construct(private readonly Hasher $hasher) {}

    public static function install(Application $app): self
    {
        $probe = new self($app->make(Hasher::class));
        $app->instance(Hasher::class, $probe);

        return $probe;
    }

    /**
     * Forgets what was recorded and starts the next request as PHP-FPM starts
     * each one: every static property of Accounts back at its declared
     * default. The test process keeps static properties from one request to
     * the next, so a value cached there would hide work that every real
     * request does again.
     */
    public function newRequest(): void
    {
        $this->made = 0;
        $this->checked = [];

        foreach ((new ReflectionClass(Accounts::class))->getProperties(ReflectionProperty::IS_STATIC) as $property) {
            if ($property->hasDefaultValue()) {
                $property->setValue(null, $property->getDefaultValue());
            }
        }
    }

    /**
     * @param  string  $hashedValue
     * @return array<string, mixed>
     */
    public function info($hashedValue): array
    {
        return $this->hasher->info($hashedValue);
    }

    /**
     * @param  string  $value
     * @param  array<string, mixed>  $options
     */
    public function make(#[\SensitiveParameter] $value, array $options = []): string
    {
        $this->made++;

        return $this->hasher->make($value, $options);
    }

    /**
     * @param  string  $value
     * @param  string  $hashedValue
     * @param  array<string, mixed>  $options
     */
    public function check(#[\SensitiveParameter] $value, $hashedValue, array $options = []): bool
    {
        $this->checked[] = $hashedValue;

        return $this->hasher->check($value, $hashedValue, $options);
    }

    /**
     * @param  string  $hashedValue
     * @param  array<string, mixed>  $options
     */
    public function needsRehash($hashedValue, array $options = []): bool
    {
        return $this->hasher->needsRehash($hashedValue, $options);
    }
}
