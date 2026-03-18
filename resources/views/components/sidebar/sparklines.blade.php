@props(['data' => []])

@php
    if (!function_exists('sparklinePoints')) {
    function sparklinePoints(array $values): string
    {
        if (empty($values) || max($values) === 0) {
            return '';
        }
        $max = max($values);
        $min = min($values);
        $range = $max - $min ?: 1;
        $count = count($values);
        $points = [];
        foreach ($values as $i => $v) {
            $x = $count > 1 ? round($i / ($count - 1) * 100, 2) : 50;
            $y = round(26 - (($v - $min) / $range) * 24 + 2, 2);
            $points[] = "$x,$y";
        }
        return implode(' ', $points);
    }
    }

    $rows = [];

    if (!empty($data)) {
        if (isset($data['running'])) {
            $rows[] = [
                'label' => 'Running',
                'value' => $data['running']['avg'] . ' km avg',
                'icon' => 'footprints',
                'color' => 'text-data-activity',
                'points' => sparklinePoints($data['running']['values']),
            ];
        }
        if (isset($data['calories'])) {
            $rows[] = [
                'label' => 'Calories',
                'value' => number_format($data['calories']['avg']) . ' avg',
                'icon' => 'utensils',
                'color' => 'text-data-food',
                'points' => sparklinePoints($data['calories']['values']),
            ];
        }
        if (isset($data['sleep'])) {
            $rows[] = [
                'label' => 'Sleep',
                'value' => $data['sleep']['avg'] . 'h avg',
                'icon' => 'bed',
                'color' => 'text-data-sleep',
                'points' => sparklinePoints($data['sleep']['values']),
            ];
        }
    }

    if (empty($rows)) {
        $rows = [
            ['label' => 'Running', 'value' => '0 km avg', 'icon' => 'footprints', 'color' => 'text-data-activity', 'points' => ''],
            ['label' => 'Calories', 'value' => '0 avg', 'icon' => 'utensils', 'color' => 'text-data-food', 'points' => ''],
            ['label' => 'Sleep', 'value' => '0h avg', 'icon' => 'bed', 'color' => 'text-data-sleep', 'points' => ''],
        ];
    }
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
