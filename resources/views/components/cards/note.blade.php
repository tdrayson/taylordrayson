@props(['entry'])

<article class="group relative">
    <div class="absolute -left-[52px] top-0 h-10 w-10 rounded-full flex items-center justify-center shrink-0"
         style="background: hsl(var(--color-note) / 0.12);">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
             stroke-linejoin="round" class="lucide lucide-message-circle h-[18px] w-[18px]"
             style="color: hsl(var(--color-note));">
            <path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"></path>
        </svg>
    </div>

    <div class="flex items-start justify-between gap-4 mb-1">
        <div class="text-right shrink-0 ml-auto">
            <p class="text-sm font-medium text-muted-foreground leading-tight">{{ $entry->occurred_at->format('D g:ia') }}</p>
            <p class="text-xs text-muted-foreground/70 leading-tight">{{ $entry->occurred_at->format('j M Y') }}</p>
        </div>
    </div>

    <p class="text-sm text-foreground leading-relaxed">{{ $entry->content }}</p>
</article>
