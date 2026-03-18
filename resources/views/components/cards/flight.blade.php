@props(['entry'])

@php
    $meta = $entry->meta ?? [];
    $originCity = $meta['origin_city'] ?? null;
    $destinationCity = $meta['destination_city'] ?? null;
    $airline = $meta['airline'] ?? null;
    $flightNumber = $entry->flight_number;

    $subtitleParts = array_filter([
        ($originCity && $destinationCity) ? "{$originCity} → {$destinationCity}" : null,
        $airline && $flightNumber ? "{$airline} {$flightNumber}" : ($flightNumber ? $flightNumber : null),
    ]);
    $subtitle = implode(' · ', $subtitleParts);

    $mapAsset = $entry->map;
@endphp

<article class="group relative">
    <div class="absolute -left-[52px] top-0 h-10 w-10 rounded-full flex items-center justify-center shrink-0"
         style="background: hsl(var(--color-flight) / 0.12);">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
             stroke-linejoin="round" class="lucide lucide-plane h-[18px] w-[18px]"
             style="color: hsl(var(--color-flight));">
            <path d="M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 5.3c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z"></path>
        </svg>
    </div>

    <div class="flex items-start justify-between gap-4 mb-1">
        <p class="text-lg font-medium text-foreground min-w-0 truncate">{{ $entry->origin_iata }} &rarr; {{ $entry->destination_iata }}</p>
        <div class="text-right shrink-0">
            <p class="text-sm font-medium text-muted-foreground leading-tight">{{ $entry->occurred_at->format('D g:ia') }}</p>
            <p class="text-xs text-muted-foreground/70 leading-tight">{{ $entry->occurred_at->format('j M Y') }}</p>
        </div>
    </div>

    @if($subtitle)
        <p class="text-sm text-muted-foreground">{{ $subtitle }}</p>
    @endif

    @if($mapAsset)
        <div class="mt-4 rounded-2xl overflow-hidden border border-border">
            <img src="{{ $mapAsset->path }}" alt="Flight map" class="w-full h-[320px] object-cover">
        </div>
    @endif

    @if($entry->distance_miles || $meta['duration'] ?? null || $meta['depart_time'] ?? null || $meta['arrive_time'] ?? null)
        <div class="flex flex-wrap gap-x-6 gap-y-1.5 mt-4">
            @if($entry->distance_miles)
                <div class="flex items-baseline gap-1.5">
                    <span class="text-base font-bold tabular-nums text-foreground">{{ number_format($entry->distance_miles) }}</span>
                    <span class="text-sm text-muted-foreground">km</span>
                </div>
            @endif
            @if($meta['duration'] ?? null)
                <div class="flex items-baseline gap-1.5">
                    <span class="text-base font-bold tabular-nums text-foreground">{{ $meta['duration'] }}</span>
                    <span class="text-sm text-muted-foreground">duration</span>
                </div>
            @endif
            @if($meta['depart_time'] ?? null)
                <div class="flex items-baseline gap-1.5">
                    <span class="text-base font-bold tabular-nums text-foreground">{{ $meta['depart_time'] }}</span>
                    <span class="text-sm text-muted-foreground">depart</span>
                </div>
            @endif
            @if($meta['arrive_time'] ?? null)
                <div class="flex items-baseline gap-1.5">
                    <span class="text-base font-bold tabular-nums text-foreground">{{ $meta['arrive_time'] }}</span>
                    <span class="text-sm text-muted-foreground">arrive</span>
                </div>
            @endif
        </div>
    @endif
</article>
