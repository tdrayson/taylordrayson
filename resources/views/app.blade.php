<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => \App\Support\Preferences::rendersDark(request())])>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title inertia>{{ config('app.name', 'Taylor Drayson') }}</title>

    @include('partials.theme')
    @include('partials.pwa')
    @include('partials.feeds')

    <link rel="me" href="https://github.com/tdrayson">

    {{-- Webmention discovery. In the Blade layout, not a Vue component: a
         sender fetches the HTML and never runs the JS. --}}
    <link rel="webmention" href="{{ route('webmention') }}">

    @vite(['resources/js/app.js'])
    @inertiaHead
</head>
<body class="bg-canvas text-ink antialiased">
    @inertia
</body>
</html>
