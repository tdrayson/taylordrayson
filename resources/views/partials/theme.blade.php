<script>
    // The server already set the class from the `theme` and `scheme` cookies.
    // This only covers the two cases it cannot: a first visit with no cookie
    // yet, and a visitor on `system` whose OS scheme has changed since the
    // cookie was written. Inline and blocking, so it lands before first paint.
    // resources/js/useTheme.js owns the preference once Vue has hydrated.
    (function () {
        try {
            var root = document.documentElement;
            var theme = document.cookie.match(/(?:^|;\s*)theme=([^;]*)/);
            var choice = theme ? decodeURIComponent(theme[1]) : 'system';

            if (choice === 'light' || choice === 'dark') {
                root.classList.toggle('dark', choice === 'dark');
                return;
            }

            var dark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            root.classList.toggle('dark', dark);
            document.cookie = 'scheme=' + (dark ? 'dark' : 'light') + ';path=/;max-age=31536000;samesite=lax';
        } catch (e) {}
    })();
</script>
