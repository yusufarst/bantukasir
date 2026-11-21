<?php

namespace Tests\Performance\Dataset;

use LogicException;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * The choices of a dataset, made from its seed alone (TASK-043 AC-043-01; TEST_STRATEGY TS-08): the
 * same seed gives the same names, prices, quantities, carts and order of documents on every run.
 * Mersenne Twister is PHP's seedable engine; nothing here is a secret or a key.
 */
final class Seeded
{
    private readonly Randomizer $random;

    public function __construct(public readonly int $seed)
    {
        $this->random = new Randomizer(new Mt19937($seed));
    }

    public function between(int $min, int $max): int
    {
        return $this->random->getInt($min, $max);
    }

    /**
     * Whether a choice of this percentage falls.
     */
    public function chance(int $percent): bool
    {
        return $this->random->getInt(1, 100) <= $percent;
    }

    /**
     * One of the items.
     *
     * @template T
     *
     * @param  non-empty-list<T>  $items
     * @return T
     */
    public function pick(array $items): mixed
    {
        return $items[$this->random->getInt(0, count($items) - 1)];
    }

    /**
     * Distinct items, as many as asked for, in the order the list holds them.
     *
     * @param  list<int>  $items
     * @return list<int>
     */
    public function sample(array $items, int $count): array
    {
        if ($count < 1 || $count > count($items)) {
            throw new LogicException('A sample takes at least one item and at most the whole list.');
        }

        $keys = $this->random->pickArrayKeys($items, $count);

        return array_map(static fn (int|string $key): int => $items[(int) $key], $keys);
    }

    /**
     * The items in a new order.
     *
     * @template T
     *
     * @param  list<T>  $items
     * @return list<T>
     */
    public function shuffle(array $items): array
    {
        return array_values($this->random->shuffleArray($items));
    }
}
