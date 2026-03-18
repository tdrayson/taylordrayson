@props(['entry'])

<article class="group relative">
    <div class="absolute -left-[52px] top-0 h-10 w-10 rounded-full flex items-center justify-center shrink-0"
         style="background: hsl(var(--color-appearance) / 0.12);">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
             stroke-linejoin="round" class="lucide lucide-mic h-[18px] w-[18px]"
             style="color: hsl(var(--color-appearance));">
            <path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"></path>
            <path d="M19 10v2a7 7 0 0 1-14 0v-2"></path>
            <line x1="12" x2="12" y1="19" y2="22"></line>
        </svg>
    </div>

    <div class="flex items-start justify-between gap-4 mb-1">
        <p class="text-lg font-medium text-foreground min-w-0 truncate">{{ $entry->title }}</p>
        <div class="text-right shrink-0">
            <p class="text-sm font-medium text-muted-foreground leading-tight">{{ $entry->occurred_at->format('D g:ia') }}</p>
            <p class="text-xs text-muted-foreground/70 leading-tight">{{ $entry->occurred_at->format('j M Y') }}</p>
        </div>
    </div>

    @if($entry->show_name)
        <p class="text-sm text-muted-foreground">{{ $entry->show_name }}</p>
    @endif

    @if($entry->description)
        <p class="text-sm text-muted-foreground mt-2 leading-relaxed">{{ $entry->description }}</p>
    @endif

    @if($entry->type)
        <div class="flex items-center gap-2 mt-3">
            <span class="text-xs px-2 py-0.5 rounded-full bg-secondary text-secondary-foreground capitalize">{{ $entry->type }}</span>
        </div>
    @endif

    @if($entry->url)
        <div class="flex items-center gap-3 mt-4 pt-3 border-t border-border">
            <a href="{{ $entry->url }}" target="_blank" rel="noopener noreferrer"
               class="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                     stroke-linejoin="round" class="lucide lucide-external-link h-3.5 w-3.5">
                    <path d="M15 3h6v6"></path>
                    <path d="M10 14 21 3"></path>
                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                </svg>
                <span>View on {{ parse_url($entry->url, PHP_URL_HOST) }}</span>
            </a>
        </div>
    @endif
</article>
