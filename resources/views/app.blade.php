<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title inertia>{{ __('ui.studio.name') }} · 10 Event</title>
    <meta name="theme-color" content="#F7F7F7">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    @viteReactRefresh
    @vite(['resources/js/app.tsx'])
    @routes
    <x-inertia::head />
</head>
<body>
    <x-inertia::app />
</body>
</html>
