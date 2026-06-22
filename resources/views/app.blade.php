<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500..800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

    <title inertia>{{ config('app.name', 'Taylor Drayson') }}</title>

    <link rel="alternate" type="application/atom+xml" title="Taylor Drayson (Atom)" href="/feed">
    <link rel="alternate" type="application/rss+xml" title="Taylor Drayson (RSS)" href="/feed/rss">
    <link rel="alternate" type="application/feed+json" title="Taylor Drayson (JSON)" href="/feed/json">
    <link rel="me" href="https://github.com/tdrayson">

    @vite(['resources/js/app.js'])
    @inertiaHead
</head>
<body class="bg-canvas text-ink antialiased">
    @inertia
</body>
</html>
