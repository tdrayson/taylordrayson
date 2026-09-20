{{-- Declared rather than left to the root-path convention, so the icon is known
     from the markup instead of a 404 and a retry. The tab icons are circular
     with transparent corners; the home-screen ones are square, since iOS and
     Android apply their own mask. --}}
<link rel="icon" href="{{ \App\Support\PublicAsset::url('/favicon.ico') }}" sizes="16x16 32x32 48x48">
<link rel="icon" href="{{ \App\Support\PublicAsset::url('/icons/favicon-192.png') }}" type="image/png" sizes="192x192">

<link rel="manifest" href="/manifest.webmanifest">
<meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#141a23" media="(prefers-color-scheme: dark)">

<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Drayson">
<link rel="apple-touch-icon" href="{{ \App\Support\PublicAsset::url('/icons/apple-touch-icon.png') }}">
