@props(['entry'])

@php
    $title = $entry->name ?? ucfirst($entry->type);
    $isCardio = in_array($entry->type, ['run', 'cycle', 'swim', 'walk', 'hike']);
    $durationSeconds = $entry->duration_seconds ?? 0;

    if ($durationSeconds >= 3600) {
        $formattedDuration = gmdate('G\h i\m', $durationSeconds);
    } elseif ($durationSeconds > 0) {
        $formattedDuration = gmdate('i\m', $durationSeconds);
    } else {
        $formattedDuration = null;
    }

    $distanceKm = $entry->distance_km ? round($entry->distance_km, 2) : null;
    $photo = $entry->photos->first();
@endphp

<article class="group relative">
    <div class="absolute -left-[52px] top-0 h-10 w-10 rounded-full flex items-center justify-center shrink-0"
         style="background: hsl(var(--color-activity) / 0.12);">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
             stroke-linejoin="round" class="lucide lucide-footprints h-[18px] w-[18px]"
             style="color: hsl(var(--color-activity));">
            <path d="M4 16v-2.38C4 11.5 2.97 10.5 3 8c.03-2.72 1.49-6 4.5-6C9.37 2 10 3.8 10 5.5c0 3.11-2 5.66-2 8.68V16a2 2 0 1 1-4 0Z"></path>
            <path d="M20 20v-2.38c0-2.12 1.03-3.12 1-5.62-.03-2.72-1.49-6-4.5-6C14.63 6 14 7.8 14 9.5c0 3.11 2 5.66 2 8.68V20a2 2 0 1 0 4 0Z"></path>
            <path d="M16 17h4"></path>
            <path d="M4 13h4"></path>
        </svg>
    </div>

    <div class="flex items-start justify-between gap-4 mb-1">
        <p class="text-lg font-medium text-foreground min-w-0 truncate">{{ $title }}</p>
        <div class="text-right shrink-0">
            <p class="text-sm font-medium text-muted-foreground leading-tight">{{ $entry->occurred_at->format('D g:ia') }}</p>
            <p class="text-xs text-muted-foreground/70 leading-tight">{{ $entry->occurred_at->format('j M Y') }}</p>
        </div>
    </div>

    @if($photo)
        <div class="mt-3 rounded-2xl overflow-hidden">
            <img src="{{ $photo->path }}" alt="" class="w-full aspect-[3/2] object-cover">
        </div>
    @endif

    @if($entry->polyline)
        <div class="mt-3 rounded-2xl overflow-hidden border border-border bg-muted/30 h-[320px] flex items-center justify-center">
            <p class="text-sm text-muted-foreground">Map</p>
        </div>
    @endif

    @if($distanceKm || $formattedDuration || $entry->calories)
        <div class="flex flex-wrap gap-x-6 gap-y-1.5 mt-4">
            @if($isCardio && $distanceKm)
                <div class="flex items-baseline gap-1.5">
                    <span class="text-base font-bold tabular-nums text-foreground">{{ number_format($distanceKm, 2) }}</span>
                    <span class="text-sm text-muted-foreground">km</span>
                </div>
            @endif
            @if($formattedDuration)
                <div class="flex items-baseline gap-1.5">
                    <span class="text-base font-bold tabular-nums text-foreground">{{ $formattedDuration }}</span>
                    <span class="text-sm text-muted-foreground">time</span>
                </div>
            @endif
            @if($entry->meta['elevation_gain'] ?? null)
                <div class="flex items-baseline gap-1.5">
                    <span class="text-base font-bold tabular-nums text-foreground">{{ $entry->meta['elevation_gain'] }}m</span>
                    <span class="text-sm text-muted-foreground">elev</span>
                </div>
            @endif
            @if($entry->calories)
                <div class="flex items-baseline gap-1.5">
                    <span class="text-base font-bold tabular-nums text-foreground">{{ number_format($entry->calories) }}</span>
                    <span class="text-sm text-muted-foreground">kcal</span>
                </div>
            @endif
        </div>
    @endif

    @if($entry->meta['exercises'] ?? null)
        <div class="mt-4 space-y-2.5">
            @foreach($entry->meta['exercises'] as $exercise)
                <div class="flex items-baseline gap-2">
                    @if(is_array($exercise))
                        <span class="text-sm font-medium text-foreground">{{ $exercise['name'] }}</span>
                        <span class="text-muted-foreground text-xs">{{ $exercise['sets'] ?? '' }}</span>
                    @else
                        <span class="text-sm font-medium text-foreground">{{ $exercise }}</span>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    @if($entry->platform_url)
        <div class="flex items-center gap-3 mt-4 pt-3 border-t border-border">
            <a href="{{ $entry->platform_url }}" target="_blank" rel="noopener noreferrer"
               class="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                     stroke-linejoin="round" class="lucide lucide-external-link h-3.5 w-3.5">
                    <path d="M15 3h6v6"></path>
                    <path d="M10 14 21 3"></path>
                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                </svg>
                <span>View on Strava</span>
            </a>
        </div>
    @endif
</article>
