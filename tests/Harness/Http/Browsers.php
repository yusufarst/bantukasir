<?php

namespace Tests\Harness\Http;

use Illuminate\Support\ViewErrorBag;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Separate browsers in an HTTP test (TASK-006): the framework's test client
 * keeps one session store in memory for the whole test, which is one browser.
 * With the database session driver, browse() forgets that store and the
 * signed-in guard before a request and sends only the session cookie it is
 * given, so that each request reads its sign-in session from the session
 * store, as a request of that browser would — a session revoked meanwhile is
 * gone. A test of a revocation, a second device or an ended sign-in session
 * uses these helpers; others use the framework's single browser.
 */
trait Browsers
{
    protected function useDatabaseSessions(): void
    {
        config(['session.driver' => 'database']);
        $this->app['session']->forgetDrivers();
    }

    /**
     * The next request comes from the browser that holds this session
     * cookie, or from a browser without one.
     */
    protected function browse(?string $session): void
    {
        $this->app['session']->forgetDrivers();
        $this->app['auth']->forgetGuards();
        // The session handler finds the signed-in user through this alias of
        // the guard, which the container would otherwise keep from an earlier
        // request of the test.
        $this->app->forgetInstance('auth.driver');
        $this->app->forgetInstance('session.store');
        // The redirector flashes into the session store it was given when it
        // was made.
        $this->app->forgetInstance('redirect');
        $this->defaultCookies = [];
        $this->defaultHeaders = [];

        if ($session !== null) {
            // A browser sends its cookies with every request, JSON included.
            $this->withCredentials();
            $this->withCookie(config()->string('session.cookie'), $session);
        }
    }

    /**
     * Signs in a new browser and returns its session cookie.
     */
    protected function signInBrowser(string $email, string $password): string
    {
        $this->browse(null);
        $response = $this->post('/login', ['email' => $email, 'password' => $password]);
        $response->assertRedirect('/');

        return $this->sessionOf($response);
    }

    /**
     * The validation errors that the last request flashed, by field. The
     * session serializes as JSON (config/session.php), so after a response the
     * store holds the error bag as an array, which the next request turns back
     * into a ViewErrorBag.
     *
     * @return array<string, list<string>>
     */
    protected function flashedErrors(): array
    {
        $errors = session('errors');

        if ($errors instanceof ViewErrorBag) {
            /** @var array<string, list<string>> */
            return $errors->getBag('default')->toArray();
        }

        $this->assertIsArray($errors, 'No errors were flashed.');
        $default = $errors['default'] ?? null;
        $this->assertIsArray($default);
        $this->assertIsArray($default['messages'] ?? null);

        /** @var array<string, list<string>> */
        return $default['messages'];
    }

    /**
     * The names of an Inertia page's props, sorted, without the interface
     * dictionary that every full page load carries (ADR-005 item 4).
     *
     * @param  array<array-key, mixed>  $props
     * @return list<string>
     */
    protected static function propNames(array $props): array
    {
        $names = array_values(array_filter(array_map(strval(...), array_keys($props)), fn (string $name): bool => $name !== 'dictionary'));
        sort($names);

        return $names;
    }

    /**
     * The session cookie a response set.
     *
     * @param  TestResponse<Response>  $response
     */
    protected function sessionOf(TestResponse $response): string
    {
        $cookie = $response->getCookie(config()->string('session.cookie'));
        $value = $cookie?->getValue();
        $this->assertIsString($value, 'The response set no session cookie.');

        return $value;
    }
}
