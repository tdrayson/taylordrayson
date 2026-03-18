@props(['entry'])

@php
    $readTime = $entry->content ? ceil(str_word_count(strip_tags($entry->content)) / 200) : null;
    $tags = $entry->tags ?? [];
@endphp

<article class="group relative">
    <div class="absolute -left-[52px] top-0 h-10 w-10 rounded-full flex items-center justify-center shrink-0"
         style="background: hsl(var(--color-article) / 0.12);">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
             stroke-linejoin="round" class="lucide lucide-file-text h-[18px] w-[18px]"
             style="color: hsl(var(--color-article));">
            <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"></path>
            <path d="M14 2v4a2 2 0 0 0 2 2h4"></path>
            <path d="M10 9H8"></path>
            <path d="M16 13H8"></path>
            <path d="M16 17H8"></path>
        </svg>
    </div>

    <div class="flex items-start justify-between gap-4 mb-1">
        <p class="text-lg font-medium text-foreground min-w-0 truncate">{{ $entry->title }}</p>
        <div class="text-right shrink-0">
            <p class="text-sm font-medium text-muted-foreground leading-tight">{{ $entry->occurred_at->format('D g:ia') }}</p>
            <p class="text-xs text-muted-foreground/70 leading-tight">{{ $entry->occurred_at->format('j M Y') }}</p>
        </div>
    </div>

    @if($entry->excerpt)
        <p class="text-sm text-muted-foreground mt-2 leading-relaxed">{{ $entry->excerpt }}</p>
    @endif

    @if($readTime)
        <p class="text-xs text-muted-foreground mt-2">{{ $readTime }} min read</p>
    @endif

    @if(count($tags) > 0)
        <div class="flex gap-2 mt-3">
            @foreach($tags as $tag)
                <span class="text-xs px-2 py-0.5 rounded-full bg-secondary text-secondary-foreground">#{{ $tag }}</span>
            @endforeach
        </div>
    @endif
</article>
