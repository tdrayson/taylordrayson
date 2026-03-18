@props(['entry'])

@php
    $cover = $entry->cover;
    $location = implode(' · ', array_filter([$entry->venue_name, $entry->city]));
@endphp

<article class="group relative">
    <div class="absolute -left-[52px] top-0 h-10 w-10 rounded-full flex items-center justify-center shrink-0"
         style="background: hsl(var(--color-event) / 0.12);">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
             stroke-linejoin="round" class="lucide lucide-music h-[18px] w-[18px]"
             style="color: hsl(var(--color-event));">
            <path d="M9 18V5l12-2v13"></path>
            <circle cx="6" cy="18" r="3"></circle>
            <circle cx="18" cy="16" r="3"></circle>
        </svg>
    </div>

    <div class="flex items-start justify-between gap-4 mb-1">
        <p class="text-lg font-medium text-foreground min-w-0 truncate">{{ $entry->name }}</p>
        <div class="text-right shrink-0">
            <p class="text-sm font-medium text-muted-foreground leading-tight">{{ $entry->occurred_at->format('D g:ia') }}</p>
            <p class="text-xs text-muted-foreground/70 leading-tight">{{ $entry->occurred_at->format('j M Y') }}</p>
        </div>
    </div>

    @if($location)
        <p class="text-sm text-muted-foreground">{{ $location }}</p>
    @endif

    @if($entry->type)
        <div class="flex items-center gap-2 mt-3">
            <span class="text-xs px-2 py-0.5 rounded-full bg-secondary text-secondary-foreground capitalize">{{ $entry->type }}</span>
        </div>
    @endif

    @if($cover)
        <div class="mt-3 rounded-2xl overflow-hidden">
            <img src="{{ $cover->path }}" alt="{{ $entry->name }}" class="w-full aspect-[3/2] object-cover">
        </div>
    @endif
</article>
