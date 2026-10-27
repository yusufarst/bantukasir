<?php

namespace Tests\Harness\Channels;

use Illuminate\Testing\TestResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * The payload inspection of the sensitive channels, per role (SECURITY_MODEL
 * SEC-CH-01 to SEC-CH-03, THR-08; suite AUTH02; TASK-007 scope item 3): an
 * Inertia page's shared props and its own props, and a JSON body, are
 * compared field by field with the allowlists of Allowlists, for the kind of
 * account that received them; and nothing of the given secrets — a password,
 * a one-time link's token, another person's email — nor anything shaped like
 * a password hash appears anywhere in the answer. Every later Task inspects
 * the pages and reads it adds with these helpers.
 */
trait ChannelInspector
{
    /**
     * @param  TestResponse<Response>  $response
     * @param  list<string>  $secrets
     */
    protected function assertPageChannel(TestResponse $response, string $actor, array $secrets = []): void
    {
        $page = $this->inertiaPage($response);
        $component = $page['component'] ?? null;
        $this->assertIsString($component);

        $allowed = Allowlists::page($component, $actor);
        $this->assertNotNull($allowed, sprintf('%s is not a page that %s may open (Allowlists::PAGES).', $component, $actor));

        $props = $page['props'] ?? null;
        $this->assertIsArray($props);
        $sharedKeys = $page['sharedProps'] ?? [];
        $this->assertIsArray($sharedKeys);

        // The dictionary is the once prop of ADR-005: texts only, by key.
        if (array_key_exists('dictionary', $props)) {
            $this->assertIsArray($props['dictionary']);

            foreach ($props['dictionary'] as $key => $text) {
                $this->assertIsString($key);
                $this->assertIsString($text, 'The dictionary holds texts only.');
            }

            unset($props['dictionary']);
        }

        $shared = array_intersect_key($props, array_flip(array_map(static fn (mixed $key): string => is_string($key) ? $key : '', $sharedKeys)));
        $own = array_diff_key($props, $shared);

        // Inertia's validation-error channel carries the same user's field
        // messages only (SEC-CH-03): its fields are the form's, not a leak.
        if (array_key_exists('errors', $shared)) {
            $shared['errors'] = true;
        }

        $this->assertSame(self::sorted($actor === 'guest' ? Allowlists::SHARED_GUEST : Allowlists::SHARED_SIGNED_IN), self::sorted(self::paths($shared)), sprintf('The shared props of %s for %s (SEC-CH-02).', $component, $actor));
        $this->assertSame(self::sorted($allowed), self::sorted(self::paths($own)), sprintf('The props of %s for %s (SEC-CH-01).', $component, $actor));

        $this->assertNoSecret($response, $secrets);
    }

    /**
     * @param  TestResponse<Response>  $response
     * @param  list<string>  $secrets
     */
    protected function assertJsonChannel(TestResponse $response, string $route, string $actor, array $secrets = []): void
    {
        $allowed = Allowlists::json($route, $actor);
        $this->assertNotNull($allowed, sprintf('%s is not a read that %s may make (Allowlists::JSON).', $route, $actor));

        $body = $response->json();
        $this->assertIsArray($body);
        $this->assertSame(self::sorted($allowed), self::sorted(self::paths($body)), sprintf('The JSON of %s for %s (SEC-CH-01).', $route, $actor));

        $this->assertNoSecret($response, $secrets);
    }

    /**
     * @param  TestResponse<Response>  $response
     * @param  list<string>  $secrets
     */
    protected function assertNoSecret(TestResponse $response, array $secrets): void
    {
        $content = (string) $response->getContent();
        $page = $response->original instanceof View ? json_encode($response->viewData('page')) : null;
        $everything = $content.(is_string($page) ? $page : '');

        foreach ($secrets as $secret) {
            $this->assertNotSame('', $secret);
            $this->assertStringNotContainsString($secret, $everything, 'A secret reached the browser.');
            $this->assertStringNotContainsString((string) json_encode($secret), $everything, 'A secret reached the browser.');
        }

        $this->assertDoesNotMatchRegularExpression('/\$argon2id?\$|\$2y\$\d\d\$/', $everything, 'A password hash reached the browser.');
    }

    /**
     * The Inertia page of an answer: the root view's for a full page load, the
     * body's for an Inertia visit.
     *
     * @param  TestResponse<Response>  $response
     * @return array<array-key, mixed>
     */
    protected function inertiaPage(TestResponse $response): array
    {
        if ($response->headers->has('X-Inertia')) {
            $page = $response->json();
        } else {
            $page = $response->viewData('page');
        }

        $this->assertIsArray($page);

        return $page;
    }

    /**
     * The paths of every field of a value: a map's keys, a list's items as `*`.
     *
     * @param  array<array-key, mixed>  $value
     * @return list<string>
     */
    protected static function paths(array $value, string $prefix = ''): array
    {
        $paths = [];
        $list = array_is_list($value);

        foreach ($value as $key => $item) {
            $path = $prefix.($list ? '*' : (string) $key);

            if (! in_array($path, $paths, true)) {
                $paths[] = $path;
            }

            if (is_array($item) && $item !== []) {
                foreach (self::paths($item, $path.'.') as $inner) {
                    if (! in_array($inner, $paths, true)) {
                        $paths[] = $inner;
                    }
                }
            }
        }

        return $paths;
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private static function sorted(array $values): array
    {
        sort($values);

        return $values;
    }
}
