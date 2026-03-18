@props(['entry'])

@php
    $hours = intdiv($entry->duration_minutes, 60);
    $minutes = $entry->duration_minutes % 60;
    $title = $minutes > 0 ? "{$hours}h {$minutes}m sleep" : "{$hours}h sleep";

    $bedtime = $entry->bedtime?->format('H:i');
    $wakeTime = $entry->wake_time?->format('H:i');

    $stages = $entry->stages ?? [];
    $totalMinutes = $entry->duration_minutes ?: 1;

    $stageHeights = [
        'awake' => '100%',
        'light' => '75%',
        'rem' => '50%',
        'deep' => '30%',
    ];

    $stageVars = [
        'awake' => '--sleep-awake',
        'light' => '--sleep-light',
        'rem' => '--sleep-rem',
        'deep' => '--sleep-deep',
    ];

    $stageTotals = ['awake' => 0, 'rem' => 0, 'light' => 0, 'deep' => 0];
    foreach ($stages as $stage) {
        $type = $stage['stage'] ?? 'light';
        $stageTotals[$type] = ($stageTotals[$type] ?? 0) + ($stage['duration'] ?? 0);
    }
@endphp

<article class="group relative">
    <div class="absolute -left-[52px] top-0 h-10 w-10 rounded-full flex items-center justify-center shrink-0"
         style="background: hsl(var(--color-sleep) / 0.12);">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
             stroke-linejoin="round" class="lucide lucide-bed h-[18px] w-[18px]"
             style="color: hsl(var(--color-sleep));">
            <path d="M2 4v16"></path>
            <path d="M2 8h18a2 2 0 0 1 2 2v10"></path>
            <path d="M2 17h20"></path>
            <path d="M6 8v9"></path>
        </svg>
    </div>

    <div class="flex items-start justify-between gap-4 mb-1">
        <p class="text-lg font-medium text-foreground min-w-0 truncate">{{ $title }}</p>
        <div class="text-right shrink-0">
            <p class="text-sm font-medium text-muted-foreground leading-tight">{{ $entry->occurred_at->format('D g:ia') }}</p>
            <p class="text-xs text-muted-foreground/70 leading-tight">{{ $entry->occurred_at->format('j M Y') }}</p>
        </div>
    </div>

    @if($bedtime && $wakeTime)
        <div class="flex items-baseline gap-4">
            <span class="text-sm text-muted-foreground">{{ $bedtime }} &rarr; {{ $wakeTime }}</span>
        </div>
    @endif

    @if(count($stages) > 0)
        <div class="mt-3">
            <div class="flex items-end h-14 rounded-lg overflow-hidden bg-muted/15">
                @foreach($stages as $stage)
                    @php
                        $type = $stage['stage'] ?? 'light';
                        $duration = $stage['duration'] ?? 0;
                        $widthPercent = ($duration / $totalMinutes) * 100;
                        $height = $stageHeights[$type] ?? '75%';
                        $cssVar = $stageVars[$type] ?? '--sleep-light';
                        $opacity = $type === 'awake' ? '0.7' : '0.85';
                        $minWidth = $type === 'awake' ? 'min-width: 2px;' : '';
                    @endphp
                    <div class="shrink-0"
                         style="width: {{ $widthPercent }}%; height: {{ $height }}; background-color: hsl(var({{ $cssVar }})); opacity: {{ $opacity }}; {{ $minWidth }}">
                    </div>
                @endforeach
            </div>
            <div class="flex gap-4 mt-2">
                @foreach(['awake' => 'Awake', 'rem' => 'REM', 'light' => 'Light', 'deep' => 'Deep'] as $key => $label)
                    @if($stageTotals[$key] > 0)
                        @php
                            $h = intdiv($stageTotals[$key], 60);
                            $m = $stageTotals[$key] % 60;
                            $formatted = $h > 0 ? "{$h}h {$m}m" : "{$m}m";
                        @endphp
                        <span class="text-xs text-muted-foreground flex items-center gap-1.5">
                            <span class="inline-block h-2 w-2 rounded-full" style="background: hsl(var({{ $stageVars[$key] }}));"></span>
                            {{ $label }} {{ $formatted }}
                        </span>
                    @endif
                @endforeach
            </div>
        </div>
    @endif
</article>
