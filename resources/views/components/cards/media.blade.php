@props(['entry'])

@php
    $meta = $entry->meta ?? [];
    $cover = $entry->cover;
    $rating = $entry->rating;
    $starPath = 'M11.525 2.295a.53.53 0 0 1 .95 0l2.31 4.679a2.123 2.123 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904l-3.736 3.638a2.123 2.123 0 0 0-.611 1.878l.882 5.14a.53.53 0 0 1-.771.56l-4.618-2.428a2.122 2.122 0 0 0-1.973 0L6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.122 2.122 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.122 2.122 0 0 0 1.597-1.16z';
@endphp

<article class="group relative">
    <div class="absolute -left-[52px] top-0 h-10 w-10 rounded-full flex items-center justify-center shrink-0"
         style="background: hsl(var(--color-media) / 0.12);">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
             stroke-linejoin="round" class="lucide lucide-film h-[18px] w-[18px]"
             style="color: hsl(var(--color-media));">
            <rect width="18" height="18" x="3" y="3" rx="2"></rect>
            <path d="M7 3v18"></path>
            <path d="M3 7.5h4"></path>
            <path d="M3 12h18"></path>
            <path d="M3 16.5h4"></path>
            <path d="M17 3v18"></path>
            <path d="M17 7.5h4"></path>
            <path d="M17 16.5h4"></path>
        </svg>
    </div>

    <div class="flex items-start justify-between gap-4 mb-1">
        <p class="text-lg font-medium text-foreground min-w-0 truncate">
            @if($entry->type === 'tv_episode')
                {{ $meta['show_title'] ?? $entry->title }}
            @else
                {{ $entry->title }}
            @endif
        </p>
        <div class="text-right shrink-0">
            <p class="text-sm font-medium text-muted-foreground leading-tight">{{ $entry->occurred_at->format('D g:ia') }}</p>
            <p class="text-xs text-muted-foreground/70 leading-tight">{{ $entry->occurred_at->format('j M Y') }}</p>
        </div>
    </div>

    <div class="flex gap-5">
        @if($cover)
            <img src="{{ $cover->path }}" alt="{{ $entry->title }}"
                 class="{{ $entry->type === 'book' ? 'w-20 h-[120px]' : 'w-28 h-[168px]' }} rounded-xl object-cover shrink-0 shadow-sm">
        @endif
        <div class="min-w-0">
            @if($entry->type === 'film')
                @php
                    $parts = array_filter([
                        $meta['year'] ?? null,
                        isset($meta['runtime']) ? $meta['runtime'] : null,
                    ]);
                @endphp
                @if($parts)
                    <p class="text-sm text-muted-foreground">{{ implode(' · ', $parts) }}</p>
                @endif
                @if(!empty($meta['genres']))
                    <div class="flex flex-wrap gap-1.5 mt-3">
                        @foreach($meta['genres'] as $genre)
                            <span class="text-xs px-2.5 py-1 rounded-full bg-secondary text-secondary-foreground">{{ $genre }}</span>
                        @endforeach
                    </div>
                @endif
            @elseif($entry->type === 'tv_episode')
                @php
                    $episodeLabel = isset($meta['season'], $meta['episode'])
                        ? sprintf('S%dE%d', $meta['season'], $meta['episode'])
                        : null;
                    $episodeTitle = $meta['episode_title'] ?? null;
                    $subtitle = implode(' · ', array_filter([$episodeLabel, $episodeTitle ? "\"{$episodeTitle}\"" : null]));
                @endphp
                @if($subtitle)
                    <p class="text-sm text-muted-foreground">{{ $subtitle }}</p>
                @endif
                @if($meta['runtime'] ?? null)
                    <p class="text-sm text-muted-foreground mt-1">{{ $meta['runtime'] }}</p>
                @endif
            @elseif($entry->type === 'book')
                @if($meta['author'] ?? null)
                    <p class="text-sm text-muted-foreground">{{ $meta['author'] }}</p>
                @endif
                @if($meta['status'] ?? null)
                    <span class="inline-block mt-3 text-xs px-2 py-0.5 rounded-full bg-secondary text-secondary-foreground capitalize">{{ $meta['status'] }}</span>
                @endif
            @endif

            @if($rating)
                <div class="flex items-center gap-0.5 mt-3">
                    @for($i = 1; $i <= 5; $i++)
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                             stroke-linejoin="round"
                             class="lucide lucide-star h-4 w-4 {{ $i <= ($rating / 2) ? 'fill-data-food text-data-food' : 'text-muted' }}">
                            <path d="{{ $starPath }}"></path>
                        </svg>
                    @endfor
                    <span class="text-sm text-muted-foreground ml-1.5">{{ $rating }}/10</span>
                </div>
            @endif
        </div>
    </div>
</article>
