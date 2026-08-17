<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title inertia>{{ config('app.name', 'Taylor Drayson') }}</title>

    @include('partials.theme')
    @include('partials.pwa')
    @include('partials.feeds')

    <link rel="me" href="https://github.com/tdrayson">

    @vite(['resources/js/app.js'])
    @inertiaHead
</head>
<body class="bg-canvas text-ink antialiased">
    @inertia
</body>
</html>
