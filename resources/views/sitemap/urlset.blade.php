<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($urls as $url)
    <url>
        <loc>{{ rtrim(url('/'), '/').$url['loc'] }}</loc>
@if (! empty($url['lastmod']))
        <lastmod>{{ $url['lastmod'] }}</lastmod>
@endif
    </url>
@endforeach
</urlset>
