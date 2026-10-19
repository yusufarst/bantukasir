<?php

namespace Tests\Fixtures\StaticChecks;

// Negative sample of the translation-key check: one key exists, two do not.
// Never executed.

/**
 * @return list<mixed>
 */
function missingTranslationKeys(): array
{
    return [__('sample.greeting.title'), __('sample.greeting.missing'), trans('unknown.key')];
}
