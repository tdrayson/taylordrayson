<x-layouts.app>
    <h1>Timeline</h1>

    @php $lastDate = null; @endphp
    @foreach ($entries as $entry)
        @if ($entry->timelineable)
            @php
                $currentDate = $entry->occurred_at->format('Y-m-d');
                $card = $entry->timelineable->card();
            @endphp

            @if ($currentDate !== $lastDate)
                @if ($lastDate !== null)
                    </ul>
                @endif
                <h2><a href="/{{ $entry->occurred_at->format('Y/m/d') }}">{{ $entry->occurred_at->format('l j F Y') }}</a></h2>
                <ul>
                @php $lastDate = $currentDate; @endphp
            @endif

            <li>
                <a href="{{ $entry->timelineable->url() }}">
                    <strong>{{ $card['title'] }}</strong>
                </a>
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
            Page {{ $entries->currentPage() }} of {{ $entries->lastPage() }}
            <br>
            @if ($entries->onFirstPage())
                <span>Previous</span>
            @else
                <a href="{{ $entries->previousPageUrl() }}">Previous</a>
            @endif

            @php
                $start = max(1, $entries->currentPage() - 3);
                $end = min($entries->lastPage(), $entries->currentPage() + 3);
            @endphp

            @if ($start > 1)
                <a href="{{ $entries->url(1) }}">1</a>
                @if ($start > 2)
                    ...
                @endif
            @endif

            @for ($i = $start; $i <= $end; $i++)
                @if ($i == $entries->currentPage())
                    <strong>{{ $i }}</strong>
                @else
                    <a href="{{ $entries->url($i) }}">{{ $i }}</a>
                @endif
                @if ($i < $end)
                    &nbsp;
                @endif
            @endfor

            @if ($end < $entries->lastPage())
                @if ($end < $entries->lastPage() - 1)
                    ...
                @endif
                <a href="{{ $entries->url($entries->lastPage()) }}">{{ $entries->lastPage() }}</a>
            @endif

            @if ($entries->hasMorePages())
                <a href="{{ $entries->nextPageUrl() }}">Next</a>
            @else
                <span>Next</span>
            @endif
        </p>
    @endif
</x-layouts.app>
