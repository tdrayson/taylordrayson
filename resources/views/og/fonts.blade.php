@php
    /**
     * Faces are inlined as data URIs rather than linked: Browsershot renders this
     * card from a temp file, so an http(s) font URL is cross-origin and Chrome
     * demands CORS headers the app doesn't send for static files.
     */
    $latin = 'U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD';
    $latinExt = 'U+0100-02BA,U+02BD-02C5,U+02C7-02CC,U+02CE-02D7,U+02DD-02FF,U+0304,U+0308,U+0329,U+1D00-1DBF,U+1E00-1E9F,U+1EF2-1EFF,U+2020,U+20A0-20AB,U+20AD-20C0,U+2113,U+2C60-2C7F,U+A720-A7FF';

    $faces = [
        ['Inter Variable', '100 900', 'inter-latin-wght-normal.woff2', $latin],
        ['Inter Variable', '100 900', 'inter-latin-ext-wght-normal.woff2', $latinExt],
        ['Bricolage Grotesque Variable', '200 800', 'bricolage-grotesque-latin-opsz-normal.woff2', $latin],
        ['Bricolage Grotesque Variable', '200 800', 'bricolage-grotesque-latin-ext-opsz-normal.woff2', $latinExt],
    ];
@endphp
<style>
@foreach ($faces as [$family, $weight, $file, $range])
    @font-face {
        font-family: '{{ $family }}';
        font-style: normal;
        font-weight: {{ $weight }};
        src: url(data:font/woff2;base64,{{ base64_encode(file_get_contents(public_path('fonts/'.$file))) }}) format('woff2-variations');
        unicode-range: {{ $range }};
    }
@endforeach
</style>
