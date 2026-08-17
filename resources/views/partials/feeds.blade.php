<link rel="alternate" type="application/atom+xml" title="Taylor Drayson (Atom)" href="/feed">
<link rel="alternate" type="application/rss+xml" title="Taylor Drayson (RSS)" href="/feed/rss">
<link rel="alternate" type="application/feed+json" title="Taylor Drayson (JSON)" href="/feed/json">

@foreach ($contextualFeeds ?? [] as $feed)
    <link rel="alternate" type="{{ $feed['type'] }}" title="{{ $feed['title'] }}" href="{{ $feed['href'] }}">
@endforeach
