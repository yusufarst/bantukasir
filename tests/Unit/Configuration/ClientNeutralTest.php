<?php

namespace Tests\Unit\Configuration;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

/**
 * The product is client-neutral (AC-010-07; REQ-SCOPE-03; BR-DOM-11; D-010,
 * D-038; suite IDENTITY01; TASK-010 scope item 7): no client's name in the
 * code, the configuration, the interface dictionary or the assets, and no
 * logo or other picture bundled with the application; the codename
 * "BantuKasir" is never a text the user reads. The shop's identity comes
 * only from its BusinessProfile. The built assets are scanned as well where
 * a build exists; the browser suite scans the build of CI
 * (tests/Browser/configuration/profile.spec.ts).
 */
final class ClientNeutralTest extends TestCase
{
    /** The places the application is made of. */
    private const array SOURCES = ['app', 'bootstrap/app.php', 'config', 'database', 'docker', 'lang', 'public', 'resources', 'routes'];

    /** The pictures a bundled logo could be. */
    private const array PICTURES = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg', 'ico', 'bmp', 'avif'];

    public function test_no_client_name_is_in_the_code_the_configuration_or_the_assets(): void
    {
        // The name is written reversed here, so that this test is not a match.
        $client = strrev('ASNATAL');
        $found = [];

        foreach (self::files() as $file) {
            $content = file_get_contents($file->getPathname());

            if (is_string($content) && stripos($content, $client) !== false) {
                $found[] = $file->getPathname();
            }
        }

        $this->assertSame([], $found, 'A client name is in the application (REQ-SCOPE-03).');
    }

    public function test_no_picture_is_bundled_and_the_codename_is_never_a_text_of_the_interface(): void
    {
        $pictures = [];

        foreach (self::files() as $file) {
            if (in_array(strtolower($file->getExtension()), self::PICTURES, true) && ! str_contains(str_replace('\\', '/', $file->getPathname()), '/public/build/')) {
                $pictures[] = $file->getPathname();
            }
        }

        $this->assertSame([], $pictures, 'No logo or picture is bundled: the logo is the BusinessProfile\'s (REQ-SCOPE-03).');

        foreach (glob(base_path('lang/id/*.php')) ?: [] as $path) {
            $this->assertStringNotContainsStringIgnoringCase('bantukasir', $this->texts(require $path), $path);
        }
    }

    /**
     * Every file of the sources, the built assets included where they exist.
     *
     * @return list<SplFileInfo>
     */
    private static function files(): array
    {
        $files = [];

        foreach (self::SOURCES as $source) {
            $path = base_path($source);

            if (is_file($path)) {
                $files[] = new SplFileInfo($path);

                continue;
            }

            if (! is_dir($path)) {
                continue;
            }

            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
                if ($file instanceof SplFileInfo && $file->isFile()) {
                    $files[] = $file;
                }
            }
        }

        return $files;
    }

    /**
     * Every text of a dictionary file, joined.
     */
    private function texts(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        $texts = '';

        foreach (is_array($value) ? $value : [] as $item) {
            $texts .= $this->texts($item)."\n";
        }

        return $texts;
    }
}
