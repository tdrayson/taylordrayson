<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600..800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        html, body { width: 1200px; height: 630px; }

        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: #ffffff;
        }

        /* Full-bleed card; sharing platforms crop and round it themselves. */
        .inner {
            position: relative;
            width: 100%;
            height: 100%;
            overflow: hidden;
            background: #ffffff;
        }

        .bg { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }

        .bg--blur { filter: blur(34px) brightness(0.97); transform: scale(1.25); }

        .bg--gradient {
            background: radial-gradient(135% 130% at 100% 0%, color-mix(in srgb, #{{ $accent }} 28%, #fff) 0%, #ffffff 62%);
        }

        /* Lightens the left so the text stays legible over a map/photo. */
        .scrim {
            position: absolute;
            inset: 0;
            background: linear-gradient(95deg, rgba(255, 255, 255, 0.97) 0%, rgba(255, 255, 255, 0.86) 40%, rgba(255, 255, 255, 0) 72%);
        }

        .content {
            position: absolute;
            top: 84px;
            left: 84px;
            right: 510px;
        }

        /* The home card leads with the wordmark, lifted clear of the headshot. */
        .content--home { top: 150px; right: 460px; }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 14px;
            font-size: 25px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #{{ $accent }};
        }

        .eyebrow .dot { width: 22px; height: 22px; border-radius: 50%; background: #{{ $accent }}; }

        .title {
            margin-top: 26px;
            font-family: 'Bricolage Grotesque', system-ui, sans-serif;
            font-weight: 800;
            font-size: 74px;
            line-height: 1.03;
            letter-spacing: -0.025em;
            color: #16181c;
            overflow-wrap: anywhere;
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 3;
            overflow: hidden;
        }

        .content--home .title { font-size: 96px; margin-top: 0; }

        /* "When", muted and quiet, sitting directly under the title. */
        .date {
            margin-top: 24px;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            font-size: 27px;
            font-weight: 500;
            color: #9298a2;
        }

        .date svg { display: block; }

        /* Sleep stage breakdown: a stacked bar of the night's stages. */
        .stages { margin-top: 46px; }

        .stage-bar {
            display: flex;
            width: 100%;
            height: 30px;
            border-radius: 10px;
            overflow: hidden;
        }

        .stage-seg { height: 100%; }

        .stage-legend {
            margin-top: 22px;
            display: flex;
            flex-wrap: wrap;
            gap: 14px 28px;
        }

        .legend-item {
            display: inline-flex;
            align-items: center;
            gap: 11px;
            font-size: 24px;
            font-weight: 600;
            color: #6b7280;
        }

        .legend-dot { width: 18px; height: 18px; border-radius: 50%; }

        /* The home card's standfirst, set beneath the wordmark. */
        .tagline {
            margin-top: 28px;
            font-size: 31px;
            font-weight: 500;
            line-height: 1.32;
            color: #6b7280;
        }

        /* The transparent cutout, bleeding off the bottom-right. */
        .cutout {
            position: absolute;
            right: 36px;
            bottom: -8px;
            height: 540px;
            width: auto;
            filter: drop-shadow(0 16px 26px rgba(20, 22, 30, 0.22));
        }

        .brand {
            position: absolute;
            left: 84px;
            bottom: 40px;
            font-size: 26px;
            font-weight: 600;
            color: #9298a2;
        }
    </style>
</head>
<body>
    @php($layout = $layout ?? 'text')
    @php($eyebrow = $eyebrow ?? null)
    @php($date = $date ?? null)
    @php($image = $image ?? null)
    @php($subtitle = $subtitle ?? null)
    @php($stages = $stages ?? null)
    <div class="inner">
        @if ($layout === 'cover')
            <img class="bg bg--blur" src="{{ $image }}" alt="">
            <div class="scrim"></div>
        @elseif ($layout === 'media')
            <img class="bg" src="{{ $image }}" alt="">
            <div class="scrim"></div>
        @else
            <div class="bg bg--gradient"></div>
        @endif

        @if ($layout === 'home')
            <div class="content content--home">
                <div class="title">{{ $title }}</div>
                @if ($subtitle)
                    <div class="tagline">{{ $subtitle }}</div>
                @endif
            </div>
        @else
            <div class="content">
                @if ($eyebrow)
                    <div class="eyebrow"><span class="dot"></span>{{ $eyebrow }}</div>
                @endif
                <div class="title">{{ $title }}</div>
                @if ($date)
                    <div class="date">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#9298a2" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                        {{ $date }}
                    </div>
                @endif
                @if ($stages)
                    <div class="stages">
                        <div class="stage-bar">
                            @foreach ($stages as $segment)
                                <span class="stage-seg" style="width: {{ $segment['percent'] }}%; background: {{ $segment['color'] }};"></span>
                            @endforeach
                        </div>
                        <div class="stage-legend">
                            @foreach ($stages as $segment)
                                <span class="legend-item"><span class="legend-dot" style="background: {{ $segment['color'] }};"></span>{{ $segment['label'] }}</span>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endif

        <img class="cutout" src="{{ $cutout }}" alt="">

        <div class="brand">taylordrayson.com</div>
    </div>
</body>
</html>
