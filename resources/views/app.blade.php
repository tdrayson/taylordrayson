<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => \App\Support\Preferences::rendersDark(request())])>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title inertia>{{ config('identity.name') }}</title>

    @include('partials.theme')
    @include('partials.pwa')
    @include('partials.feeds')

    @foreach (config('identity.profiles') as $profile)
        <link rel="me" href="{{ $profile['href'] }}">
    @endforeach

    @vite(['resources/js/app.js'])
    @inertiaHead
</head>
<body class="bg-canvas text-ink antialiased">
    @inertia
</body>
</html>
