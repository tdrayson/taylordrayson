<x-layouts.app>
    <p><a href="/">&larr; Back</a></p>

    <h1>{{ $date->format('l j F Y') }}</h1>

    <ul>
        @foreach ($entries as $entry)
            @if ($entry->timelineable)
                @php $card = $entry->timelineable->card(); @endphp
                <li>
                    <a href="{{ $entry->timelineable->url() }}">
                        <strong>{{ $card->title }}</strong>
                    </a>
                    @if ($card->subtitle)
                        &mdash; {{ $card->subtitle }}
                    @endif
                    <em>({{ $card->type }})</em>
                </li>
            @endif
        @endforeach
    </ul>

    @if ($entries->isEmpty())
        <p>No entries for this day.</p>
    @endif
</x-layouts.app>
