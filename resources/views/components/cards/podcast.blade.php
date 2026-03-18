@props(['entry'])

@php
    $title = "S{$entry->season_number} E{$entry->episode_number}";
    $durationSeconds = $entry->duration_seconds ?? 0;

    if ($durationSeconds >= 3600) {
        $formattedDuration = gmdate('G\h i\m', $durationSeconds);
    } elseif ($durationSeconds > 0) {
        $formattedDuration = gmdate('i\m', $durationSeconds);
    } else {
        $formattedDuration = null;
    }
@endphp

<article class="group relative">
    <div class="absolute -left-[52px] top-0 h-10 w-10 rounded-full flex items-center justify-center shrink-0"
         style="background: hsl(var(--color-podcast) / 0.12);">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
             stroke-linejoin="round" class="lucide lucide-headphones h-[18px] w-[18px]"
             style="color: hsl(var(--color-podcast));">
            <path d="M3 14h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-7a9 9 0 0 1 18 0v7a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3"></path>
        </svg>
    </div>

    <div class="flex items-start justify-between gap-4 mb-1">
        <p class="text-lg font-medium text-foreground min-w-0 truncate">{{ $title }}</p>
        <div class="text-right shrink-0">
            <p class="text-sm font-medium text-muted-foreground leading-tight">{{ $entry->occurred_at->format('D g:ia') }}</p>
            <p class="text-xs text-muted-foreground/70 leading-tight">{{ $entry->occurred_at->format('j M Y') }}</p>
        </div>
    </div>

    @if($entry->topic)
        <p class="text-sm text-muted-foreground leading-relaxed">{{ $entry->topic }}</p>
    @endif

    @if($formattedDuration)
        <p class="text-sm text-muted-foreground mt-1">{{ $formattedDuration }}</p>
    @endif

    @if($entry->youtube_url)
        <div class="mt-4 rounded-2xl overflow-hidden border border-border">
            <iframe src="{{ str_replace('watch?v=', 'embed/', str_replace('youtu.be/', 'www.youtube-nocookie.com/embed/', $entry->youtube_url)) }}"
                    class="w-full h-[220px]" allow="autoplay" loading="lazy"
                    title="{{ $title }}"></iframe>
        </div>
    @endif

    @if($entry->audio_url || $entry->youtube_url)
        <div class="flex items-center gap-3 mt-4 pt-3 border-t border-border">
            @if($entry->audio_url)
                <a href="{{ $entry->audio_url }}" target="_blank" rel="noopener noreferrer"
                   class="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                         stroke-linejoin="round" class="lucide lucide-external-link h-3.5 w-3.5">
                        <path d="M15 3h6v6"></path>
                        <path d="M10 14 21 3"></path>
                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                    </svg>
                    <span>Listen</span>
                </a>
            @endif
        </div>
    @endif
</article>
