User-agent: *
Allow: /

# Signed-in tooling and endpoints that mint a new URL per request. Nothing here
# is content, and /lucky and /random would burn crawl budget forever.
Disallow: /new
Disallow: /drafts
Disallow: /search
Disallow: /lookup
Disallow: /lucky
Disallow: /random
Disallow: /design-system
Disallow: /og-gallery

Sitemap: {{ url('/sitemap.xml') }}
