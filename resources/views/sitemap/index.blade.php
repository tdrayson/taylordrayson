<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($sitemaps as $sitemap)
    <sitemap>
        <loc>{{ $sitemap['loc'] }}</loc>
@if (! empty($sitemap['lastmod']))
        <lastmod>{{ $sitemap['lastmod'] }}</lastmod>
@endif
    </sitemap>
@endforeach
</sitemapindex>
