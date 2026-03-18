@props(['entry'])

@php
    $vehicle = $entry->vehicle;
    $vehicleName = is_array($vehicle) ? ($vehicle['name'] ?? null) : ($vehicle->name ?? null);
@endphp

<article class="group relative">
    <div class="absolute -left-[52px] top-0 h-10 w-10 rounded-full flex items-center justify-center shrink-0"
         style="background: hsl(var(--color-fuel) / 0.12);">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
             stroke-linejoin="round" class="lucide lucide-fuel h-[18px] w-[18px]"
             style="color: hsl(var(--color-fuel));">
            <line x1="3" x2="15" y1="22" y2="22"></line>
            <line x1="4" x2="14" y1="9" y2="9"></line>
            <path d="M14 22V4a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v18"></path>
            <path d="M14 13h2a2 2 0 0 1 2 2v2a2 2 0 0 0 2 2a2 2 0 0 0 2-2V9.83a2 2 0 0 0-.59-1.42L18 5"></path>
        </svg>
    </div>

    <div class="flex items-start justify-between gap-4 mb-1">
        <p class="text-lg font-medium text-foreground min-w-0 truncate">{{ $vehicleName ?? 'Fuel' }}</p>
        <div class="text-right shrink-0">
            <p class="text-sm font-medium text-muted-foreground leading-tight">{{ $entry->occurred_at->format('D g:ia') }}</p>
            <p class="text-xs text-muted-foreground/70 leading-tight">{{ $entry->occurred_at->format('j M Y') }}</p>
        </div>
    </div>

    <div class="flex flex-wrap gap-x-6 gap-y-1.5 mt-4">
        @if($entry->litres)
            <div class="flex items-baseline gap-1.5">
                <span class="text-base font-bold tabular-nums text-foreground">{{ number_format($entry->litres, 1) }}L</span>
            </div>
        @endif
        @if($entry->cost)
            <div class="flex items-baseline gap-1.5">
                <span class="text-base font-bold tabular-nums text-foreground">&pound;{{ number_format($entry->cost, 2) }}</span>
            </div>
        @endif
        @if($entry->price_per_litre)
            <div class="flex items-baseline gap-1.5">
                <span class="text-base font-bold tabular-nums text-foreground">{{ number_format($entry->price_per_litre * 100, 1) }}p/L</span>
            </div>
        @endif
        @if($entry->odometer)
            <div class="flex items-baseline gap-1.5">
                <span class="text-base font-bold tabular-nums text-foreground">{{ number_format($entry->odometer) }}</span>
                <span class="text-sm text-muted-foreground">mi</span>
            </div>
        @endif
    </div>
</article>
