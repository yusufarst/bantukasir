<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Lang;

abstract class TestCase extends BaseTestCase
{
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
    }
}
