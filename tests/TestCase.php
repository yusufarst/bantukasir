<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\Lang;

abstract class TestCase extends BaseTestCase
{
    /**
     * A directory of public/ that never exists: the application's Vite looks
     * there for the build's manifest and the dev server's hot file in every
     * PHP test.
     */
    public const string ABSENT_FRONTEND_BUILD = 'absent-in-php-tests';

    protected function setUp(): void
    {
        parent::setUp();

        // ADR-005 item 5: in tests, a server-side lookup of a key that lang/id
        // lacks fails instead of printing the key; the framework's own probes
        // of optional translations are let through (MissingTranslationKey).
        Lang::handleMissingKeysUsing(static function (string $key): ?string {
            if (MissingTranslationKey::isFrameworkProbe($key)) {
                return null;
            }

            throw new MissingTranslationKey($key);
        });

        // CI runs the PHP suites before the frontend build (ci.yml), so no
        // PHP test may read public/build or public/hot, which a local build or
        // dev server leaves behind: a page that renders the root view without
        // withoutVite() fails here as it does in CI (TASK-006, the CI run
        // 40277833443).
        $this->app->make(Vite::class)
            ->useBuildDirectory(self::ABSENT_FRONTEND_BUILD)
            ->useHotFile(public_path(self::ABSENT_FRONTEND_BUILD.'/hot'));
    }
}
