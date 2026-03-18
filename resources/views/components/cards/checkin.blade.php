@props(['entry'])

@php
    $photo = $entry->photos->first();
    $locationParts = array_filter([$entry->city, $entry->category]);
    $locationText = implode(' · ', $locationParts);
@endphp

<article class="group relative">
    <div class="absolute -left-[52px] top-0 h-10 w-10 rounded-full flex items-center justify-center shrink-0"
         style="background: hsl(var(--color-checkin) / 0.12);">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
             stroke-linejoin="round" class="lucide lucide-map-pin h-[18px] w-[18px]"
             style="color: hsl(var(--color-checkin));">
            <path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"></path>
            <circle cx="12" cy="10" r="3"></circle>
        </svg>
    </div>

    <div class="flex items-start justify-between gap-4 mb-1">
        <p class="text-lg font-medium text-foreground min-w-0 truncate">{{ $entry->venue_name }}</p>
        <div class="text-right shrink-0">
            <p class="text-sm font-medium text-muted-foreground leading-tight">{{ $entry->occurred_at->format('D g:ia') }}</p>
            <p class="text-xs text-muted-foreground/70 leading-tight">{{ $entry->occurred_at->format('j M Y') }}</p>
        </div>
    </div>

    @if($locationText)
        <p class="text-sm text-muted-foreground">{{ $locationText }}</p>
    @endif

    @if($photo)
        <div class="relative mt-3 mb-4">
            <div class="rounded-2xl overflow-hidden">
                <img src="{{ $photo->path }}" alt="" class="w-full aspect-[3/2] object-cover">
            </div>
        </div>
    @endif

    @if($entry->description)
        <p class="text-sm text-muted-foreground mt-3 italic">"{{ $entry->description }}"</p>
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
                <span>View on Swarm</span>
            </a>
        </div>
    @endif
</article>
