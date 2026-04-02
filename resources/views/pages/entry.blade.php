<x-layouts.app>
    <p><a href="/">&larr; Back</a></p>

    <h1>{{ $card['title'] }}</h1>

    <p>
        <strong>{{ $card['type'] }}</strong>
        &mdash; {{ $date->format('l j F Y') }}
    </p>

    @if ($card['subtitle'])
        <p>{{ $card['subtitle'] }}</p>
    @endif

    <hr>

    <dl>
        @foreach ($entry->getAttributes() as $key => $value)
            @if (!in_array($key, ['id', 'created_at', 'updated_at']) && $value !== null)
                <dt><strong>{{ $key }}</strong></dt>
                <dd>{{ is_array($value) ? json_encode($value) : $value }}</dd>
            @endif
        @endforeach
    </dl>
</x-layouts.app>
