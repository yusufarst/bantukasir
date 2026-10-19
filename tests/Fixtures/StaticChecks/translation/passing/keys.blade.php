{{-- Compliant sample of the literal check (TASK-003): every text comes from a key. --}}
<!DOCTYPE html>
<html lang="id">
    <head>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @vite(['resources/js/app.tsx'])
    </head>
    <body>
        <p>{{ __('sample.greeting.title') }}</p>
        <p>@lang('sample.greeting.title')</p>
        <input placeholder="{{ __('sample.greeting.title') }}" aria-hidden="true">
    </body>
</html>
