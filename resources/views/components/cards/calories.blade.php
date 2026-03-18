@props(['entry'])

@php
    $dailyCalories = \App\Models\Calorie::whereDate('occurred_at', $entry->occurred_at->toDateString())->get();

    $totalCalories = $dailyCalories->sum('calories');
    $totalProtein = $dailyCalories->sum('protein');
    $totalCarbs = $dailyCalories->sum('carbs');
    $totalFat = $dailyCalories->sum('fat');

    $meals = $dailyCalories->groupBy('meal')->sortKeys();
@endphp

<article class="group relative">
    <div class="absolute -left-[52px] top-0 h-10 w-10 rounded-full flex items-center justify-center shrink-0"
         style="background: hsl(var(--color-food) / 0.12);">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
             stroke-linejoin="round" class="lucide lucide-utensils h-[18px] w-[18px]"
             style="color: hsl(var(--color-food));">
            <path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"></path>
            <path d="M7 2v20"></path>
            <path d="M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"></path>
        </svg>
    </div>

    <div class="flex items-start justify-between gap-4 mb-1">
        <p class="text-lg font-medium text-foreground min-w-0 truncate">{{ number_format($totalCalories) }} calories</p>
        <div class="text-right shrink-0">
            <p class="text-sm font-medium text-muted-foreground leading-tight">{{ $entry->occurred_at->format('D g:ia') }}</p>
            <p class="text-xs text-muted-foreground/70 leading-tight">{{ $entry->occurred_at->format('j M Y') }}</p>
        </div>
    </div>

    <div class="flex gap-4">
        <span class="text-sm text-muted-foreground"><b class="text-foreground">{{ round($totalProtein) }}g</b> protein</span>
        <span class="text-sm text-muted-foreground"><b class="text-foreground">{{ round($totalCarbs) }}g</b> carbs</span>
        <span class="text-sm text-muted-foreground"><b class="text-foreground">{{ round($totalFat) }}g</b> fat</span>
    </div>

    @if($meals->isNotEmpty())
        <div class="mt-4 space-y-1">
            @foreach($meals as $meal => $items)
                <div>
                    <div class="flex items-center gap-2 py-1.5 px-3 -mx-3 rounded-lg bg-muted/50">
                        <span class="text-sm font-semibold capitalize text-foreground">{{ $meal }}:</span>
                        <span class="text-sm font-semibold tabular-nums text-foreground">{{ $items->sum('calories') }}</span>
                    </div>
                    <div class="divide-y divide-border">
                        @foreach($items as $item)
                            <div class="flex items-center justify-between py-2.5 gap-4">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-foreground">{{ $item->name }}</p>
                                    @if($item->quantity && $item->units)
                                        <p class="text-xs text-muted-foreground">{{ $item->quantity }} {{ $item->units }}</p>
                                    @endif
                                </div>
                                <span class="text-sm tabular-nums font-medium text-foreground shrink-0">{{ $item->calories }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</article>
