<script>
    // Resolve the theme before first paint so there's no flash of the wrong
    // colour scheme. This has to be inline and blocking: the app is
    // client-rendered, so nothing else runs this early. resources/js/useTheme.js
    // owns the same preference once Vue has hydrated.
    (function () {
        try {
            var stored = localStorage.getItem('theme'); // 'system' | 'light' | 'dark' | null
            var systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            var dark = stored === 'dark' || (stored !== 'light' && systemDark);
            if (dark) {
                document.documentElement.classList.add('dark');
            }
        } catch (e) {}
    })();
</script>
