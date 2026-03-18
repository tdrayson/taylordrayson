@props(['data' => []])

@php
    $defaults = [
        ['label' => 'Running', 'value' => '5.2 km avg', 'icon' => 'footprints', 'color' => 'text-data-activity', 'points' => '0,21.999999999999996 7.6923076923076925,13.000000000000004 15.384615384615385,26 23.076923076923077,9 30.76923076923077,14.999999999999996 38.46153846153847,2 46.15384615384615,6 53.84615384615385,14 61.53846153846154,19 69.23076923076923,3.0000000000000036 76.92307692307693,11.000000000000004 84.61538461538461,12 92.3076923076923,16 100,4.9999999999999964'],
        ['label' => 'Calories', 'value' => '2,172 avg', 'icon' => 'utensils', 'color' => 'text-data-food', 'points' => '0,19.142857142857142 7.6923076923076925,10.57142857142857 15.384615384615385,26 23.076923076923077,7.714285714285715 30.76923076923077,16.285714285714285 38.46153846153847,2 46.15384615384615,22 53.84615384615385,14.571428571428573 61.53846153846154,6.571428571428569 69.23076923076923,19.142857142857142 76.92307692307693,10.57142857142857 84.61538461538461,20.285714285714285 92.3076923076923,13.428571428571427 100,4.857142857142858'],
        ['label' => 'Sleep', 'value' => '7.2h avg', 'icon' => 'bed', 'color' => 'text-data-sleep', 'points' => '0,13.076923076923073 7.6923076923076925,20.461538461538463 15.384615384615385,7.538461538461533 23.076923076923077,26 30.76923076923077,2 38.46153846153847,16.769230769230766 46.15384615384615,18.615384615384606 53.84615384615385,11.230769230769234 61.53846153846154,14.923076923076927 69.23076923076923,22.307692307692303 76.92307692307693,9.384615384615376 84.61538461538461,5.692307692307693 92.3076923076923,16.769230769230766 100,7.538461538461533'],
    ];
    $rows = !empty($data) ? $data : $defaults;
@endphp

<div>
    <h3 class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground mb-2">Last
        14
        Days</h3>
    <div class="space-y-3">
        @foreach($rows as $row)
            <div>
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs text-muted-foreground flex items-center gap-1.5">
                        @if($row['icon'] === 'footprints')
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round"
                                class="lucide lucide-footprints h-3 w-3 {{ $row['color'] }}">
                                <path d="M4 16v-2.38C4 11.5 2.97 10.5 3 8c.03-2.72 1.49-6 4.5-6C9.37 2 10 3.8 10 5.5c0 3.11-2 5.66-2 8.68V16a2 2 0 1 1-4 0Z"></path>
                                <path d="M20 20v-2.38c0-2.12 1.03-3.12 1-5.62-.03-2.72-1.49-6-4.5-6C14.63 6 14 7.8 14 9.5c0 3.11 2 5.66 2 8.68V20a2 2 0 1 0 4 0Z"></path>
                                <path d="M16 17h4"></path>
                                <path d="M4 13h4"></path>
                            </svg>
                        @elseif($row['icon'] === 'utensils')
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round"
                                class="lucide lucide-utensils h-3 w-3 {{ $row['color'] }}">
                                <path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"></path>
                                <path d="M7 2v20"></path>
                                <path d="M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"></path>
                            </svg>
                        @elseif($row['icon'] === 'bed')
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round"
                                class="lucide lucide-bed h-3 w-3 {{ $row['color'] }}">
                                <path d="M2 4v16"></path>
                                <path d="M2 8h18a2 2 0 0 1 2 2v10"></path>
                                <path d="M2 17h20"></path>
                                <path d="M6 8v9"></path>
                            </svg>
                        @endif
                        {{ $row['label'] }}
                    </span>
                    <span class="text-xs font-medium text-foreground tabular-nums">{{ $row['value'] }}</span>
                </div>
                <svg viewBox="0 0 100 28" class="w-full h-5" preserveAspectRatio="none">
                    <polyline
                        points="{{ $row['points'] }}"
                        fill="none" stroke="hsl(var(--muted-foreground))" opacity="0.3" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round"></polyline>
                </svg>
            </div>
        @endforeach
    </div>
</div>
