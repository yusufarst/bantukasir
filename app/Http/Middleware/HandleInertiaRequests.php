<?php

namespace App\Http\Middleware;

use App\Support\Localization\Dictionary;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Inertia\OnceProp;

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
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            //
        ];
    }

    /**
     * Define the props that are shared once and remembered across visits.
     * The interface dictionary reaches React once per full page load; the
     * client sends it back as already loaded and keeps it (ADR-005 item 4).
     *
     * @return array<string, callable|OnceProp>
     */
    public function shareOnce(Request $request): array
    {
        return [
            ...parent::shareOnce($request),
            'dictionary' => fn (): array => Dictionary::forInterface(),
        ];
    }
}
