<?php

namespace Tests\Unit\Http;

use Illuminate\Foundation\Vite;
use Illuminate\Foundation\ViteManifestNotFoundException;
use Tests\TestCase;
use Throwable;

/**
 * The PHP suites never read the frontend build (TASK-006, the CI run
 * 40277833443). CI runs them before `npm run build` (ci.yml), so a test that
 * renders the root view without withoutVite() fails there; on a machine where
 * a local build left public/build, or a dev server left public/hot, the same
 * test passed. Tests\TestCase points the application's Vite at a directory
 * that never exists, so the test fails locally as it does in CI.
 */
final class FrontendBuildIsolationTest extends TestCase
{
    public function test_the_applications_vite_reads_neither_a_build_nor_a_dev_server(): void
    {
        $vite = $this->app->make(Vite::class);

        $this->assertDirectoryDoesNotExist(public_path(TestCase::ABSENT_FRONTEND_BUILD));
        $this->assertSame(public_path(TestCase::ABSENT_FRONTEND_BUILD.'/hot'), $vite->hotFile());
        $this->assertFalse($vite->isRunningHot());

        $this->expectException(ViteManifestNotFoundException::class);
        $this->expectExceptionMessage(public_path(TestCase::ABSENT_FRONTEND_BUILD.'/manifest.json'));

        $vite(['resources/js/app.tsx']);
    }

    public function test_a_page_rendered_without_without_vite_fails_as_in_ci(): void
    {
        $this->withoutExceptionHandling();
        $failure = null;

        try {
            $this->get('/testing/specimen');
        } catch (Throwable $thrown) {
            $failure = $thrown;
        }

        while ($failure !== null && ! $failure instanceof ViteManifestNotFoundException) {
            $failure = $failure->getPrevious();
        }

        $this->assertInstanceOf(ViteManifestNotFoundException::class, $failure, 'The root view read a manifest.');
    }

    public function test_without_vite_lets_the_same_page_render(): void
    {
        $this->withoutVite();

        $this->get('/testing/specimen')->assertOk();
    }
}
