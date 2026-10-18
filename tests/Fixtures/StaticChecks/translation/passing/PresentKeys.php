<?php

namespace Tests\Fixtures\StaticChecks;

// Compliant sample of the translation-key check. Never executed.

function presentTranslationKey(): mixed
{
    return __('sample.greeting.title');
}
