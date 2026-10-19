<?php

namespace Tests\Feature\Localization;

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Tests\TestCase;

/**
 * The interface dictionary reaches React once per full page load (ADR-005
 * item 4, AC-003-06): HandleInertiaRequests shares it with Inertia::shareOnce,
 * the first page of a full page load carries it, and a later visit whose
 * client already holds it does not receive it again. The route exists only in
 * this test; no route of ROUTE_CONTRACTS is added.
 */
final class DictionaryOncePropTest extends TestCase
{
    private const URI = '/__tests/dictionary';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Route::middleware('web')->get(self::URI, fn () => Inertia::render('Tests/Dictionary'));
    }

    public function test_a_full_page_load_carries_the_dictionary(): void
    {
        $page = $this->fullPageLoad();
        $dictionary = $this->arrayIn($this->arrayIn($page, 'props'), 'dictionary');

        $this->assertSame('Oktober', $dictionary['ux.format.month.october'] ?? null);
        $this->assertSame(':uang (:keterangan)', $dictionary['ux.format.variance'] ?? null);
        $this->assertSame('dictionary', $this->arrayIn($this->arrayIn($page, 'onceProps'), 'dictionary')['prop'] ?? null);
    }

    public function test_a_later_visit_of_a_client_that_holds_it_does_not_receive_it_again(): void
    {
        $visit = $this->get(self::URI, [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $this->version(),
            'X-Inertia-Except-Once-Props' => 'dictionary',
        ]);

        $visit->assertOk()->assertHeader('X-Inertia', 'true');
        $page = $this->asArray($visit->json());

        $this->assertArrayNotHasKey('dictionary', $this->arrayIn($page, 'props'));
        $this->assertSame('dictionary', $this->arrayIn($this->arrayIn($page, 'onceProps'), 'dictionary')['prop'] ?? null);
    }

    public function test_a_visit_of_a_client_that_does_not_hold_it_receives_it(): void
    {
        $visit = $this->get(self::URI, ['X-Inertia' => 'true', 'X-Inertia-Version' => $this->version()]);

        $visit->assertOk();
        $dictionary = $this->arrayIn($this->arrayIn($this->asArray($visit->json()), 'props'), 'dictionary');

        $this->assertSame('Oktober', $dictionary['ux.format.month.october'] ?? null);
    }

    /**
     * The page object that the root view embeds for the first page.
     *
     * @return array<mixed>
     */
    private function fullPageLoad(): array
    {
        $html = (string) $this->get(self::URI)->assertOk()->getContent();

        if (preg_match('#<script data-page="app" type="application/json">(.*?)</script>#s', $html, $matches) !== 1) {
            $this->fail('The root view embeds no page object.');
        }

        return $this->asArray(json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR));
    }

    private function version(): string
    {
        $version = $this->fullPageLoad()['version'] ?? null;

        return is_string($version) ? $version : '';
    }

    /**
     * @param  array<mixed>  $array
     * @return array<mixed>
     */
    private function arrayIn(array $array, string $key): array
    {
        return $this->asArray($array[$key] ?? null);
    }

    /**
     * @return array<mixed>
     */
    private function asArray(mixed $value): array
    {
        return is_array($value) ? $value : $this->fail('An object was expected in the page.');
    }
}
