<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Queries\Access\SignedIn;
use App\Support\Localization\Dictionary;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Middleware;
use Inertia\OnceProp;
use Inertia\Support\Header;

/**
 * Inertia's middleware with the shared props of SECURITY_MODEL SEC-CH-02
 * (TASK-007): `auth`, the signed-in user and the limits of the sign-in session
 * (App\Queries\Access\SignedIn), or null before sign-in; the interface
 * dictionary as a once prop (ADR-005); and Inertia's own validation-error
 * channel, which carries only the same user's field messages (SEC-CH-03).
 * Nothing else is shared: no business data, no other user's data, no cost.
 */
class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default. `auth` reaches every
     * answer, partial reloads included, so that the shell's timers and its
     * check of a change of user always read the sign-in session's own values.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $signedIn = app(SignedIn::class)($user instanceof User ? $user : null, $request->hasSession() ? $request->session() : null);

        return [
            ...parent::share($request),
            'auth' => Inertia::always($signedIn?->toArray()),
        ];
    }

    /**
     * Inertia keeps shared props on its response factory for the life of the
     * process, so a dictionary shared for an earlier request of the same
     * process — in tests, or in a long-running worker — is dropped first, and
     * shareOnce() alone decides whether this request carries it.
     */
    public function handle(Request $request, Closure $next)
    {
        self::dropEarlierDictionary();

        return parent::handle($request, $next);
    }

    /**
     * Shares what handle() shares, for an answer made outside this
     * middleware: an error page for a request that never reached it — an
     * unknown path, a refusal of a middleware that runs before it (App\Http\
     * Errors\ErrorAnswer).
     */
    public static function prepare(Request $request): void
    {
        self::dropEarlierDictionary();

        $middleware = app(static::class);
        Inertia::version(fn (): ?string => $middleware->version($request));
        Inertia::share($middleware->share($request));

        foreach ($middleware->shareOnce($request) as $key => $value) {
            if ($value instanceof OnceProp) {
                Inertia::share($key, $value);
            } else {
                Inertia::shareOnce($key, $value);
            }
        }

        Inertia::setRootView($middleware->rootView($request));
    }

    /**
     * Define the props that are shared once and remembered across visits.
     * The interface dictionary reaches React once per full page load; the
     * client sends it back as already loaded and keeps it (ADR-005 item 4).
     * Inertia leaves a loaded once prop out of full visits only, so a partial
     * reload — router.reload() with `only`, `except` or `reset` — gets it only
     * when its `only` names it (TASK-003 finding A-10, reading R-37).
     *
     * @return array<string, callable|OnceProp>
     */
    public function shareOnce(Request $request): array
    {
        $once = parent::shareOnce($request);

        if ($request->headers->has(Header::PARTIAL_COMPONENT) && ! in_array('dictionary', self::partialOnly($request), true)) {
            return $once;
        }

        return [
            ...$once,
            'dictionary' => fn (): array => Dictionary::forInterface(),
        ];
    }

    private static function dropEarlierDictionary(): void
    {
        $shared = Inertia::getShared();

        if (is_array($shared) && array_key_exists('dictionary', $shared)) {
            Inertia::flushShared();
            Inertia::share(Arr::except($shared, ['dictionary']));
        }
    }

    /**
     * The props a partial reload names in its `only`, as Inertia reads them.
     *
     * @return list<string>
     */
    private static function partialOnly(Request $request): array
    {
        $only = $request->header(Header::PARTIAL_ONLY);

        return is_string($only) ? array_values(array_filter(array_map(trim(...), explode(',', $only)), fn (string $prop): bool => $prop !== '')) : [];
    }
}
