<x-layouts.app>
    <h1>Timeline</h1>

    @php $lastDate = null; @endphp
    @foreach ($entries as $entry)
        @if ($entry->timelineable)
            @php
                $currentDate = $entry->occurred_at->format('Y-m-d');
                $card = $entry->timelineable->toTimelineCard();
            @endphp

            @if ($currentDate !== $lastDate)
                @if ($lastDate !== null)
                    </ul>
                @endif
                <h2>{{ $entry->occurred_at->format('l j F Y') }}</h2>
                <ul>
                @php $lastDate = $currentDate; @endphp
            @endif

            <li>
                <strong>{{ $card['title'] }}</strong>
                @if ($card['subtitle'])
                    &mdash; {{ $card['subtitle'] }}
                @endif
                <em>({{ $card['type'] }})</em>
            </li>
        @endif
    @endforeach
    @if ($lastDate !== null)
        </ul>
    @endif

    <hr>
    @if ($entries->hasPages())
        <p>
            @if ($entries->previousPageUrl())
                <a href="{{ $entries->previousPageUrl() }}">Previous</a>
            @endif
            @if ($entries->previousPageUrl() && $entries->nextPageUrl())
                |
            @endif
            @if ($entries->nextPageUrl())
                <a href="{{ $entries->nextPageUrl() }}">Next</a>
            @endif
        </p>
    @endif
</x-layouts.app>
