<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>OG image variations</title>
    <style>
        :root { color-scheme: light; }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 48px;
            font-family: system-ui, sans-serif;
            background: #f4f4f5;
            color: #16181c;
        }

        h1 { font-size: 28px; margin: 0 0 6px; }

        .intro { margin: 0 0 40px; color: #6b7280; }

        h2 {
            font-size: 18px;
            margin: 44px 0 18px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e4e4e7;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(420px, 1fr));
            gap: 28px;
        }

        figure { margin: 0; }

        img {
            width: 100%;
            height: auto;
            aspect-ratio: 1200 / 630;
            border-radius: 10px;
            background: #e4e4e7;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
        }

        .img.failed {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            aspect-ratio: 1200 / 630;
            border-radius: 10px;
            background: #fee2e2;
            color: #b91c1c;
            font-size: 14px;
            font-weight: 600;
        }

        figcaption {
            margin-top: 10px;
            font-size: 14px;
            font-weight: 600;
            color: #52525b;
        }
    </style>
</head>
<body>
    <h1>OG image variations</h1>
    <p class="intro">Every card in one place. Each loads on its own (and caches), so cards fill in as you scroll; the very first view of an uncached card takes a second or two.</p>

    @foreach ($sections as $section => $cards)
        <h2>{{ $section }}</h2>
        <div class="grid">
            @foreach ($cards as $card)
                <figure>
                    <img src="{{ $card['url'] }}" alt="{{ $card['label'] }} OG card" loading="lazy">
                    <figcaption>{{ $card['label'] }}</figcaption>
                </figure>
            @endforeach
        </div>
    @endforeach
</body>
</html>
