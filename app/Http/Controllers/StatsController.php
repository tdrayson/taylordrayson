<?php

namespace App\Http\Controllers;

use App\Queries\StatsForType;
use App\Support\OgMeta;
use App\Support\TypeColors;
use App\Timeline\TypeRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class StatsController extends Controller
{
    public function __construct(private readonly StatsForType $stats) {}

    /**
     * Per-type analytics dashboard at /stats/{type}. Everything is computed for
     * the requested range (?from&to, defaulting to this year), with deltas
     * against the chosen comparison window (?compare).
     */
    public function show(Request $request, string $type): Response
    {
        $typeKey = collect(TypeRegistry::all())->search(fn (array $definition): bool => $definition['slug'] === $type);

        abort_if($typeKey === false || ! TypeRegistry::all()[$typeKey]['stats'], 404);

        $label = TypeRegistry::all()[$typeKey]['label'];

        [$start, $end] = $this->range($request);
        $compareMode = (string) $request->query('compare', 'previous-period');

        return Inertia::render('Stats', [
            'type' => $type,
            'og' => OgMeta::stats($label, $typeKey),
            'accent' => '#'.TypeColors::hex($typeKey),
            ...($this->stats)((string) $typeKey, $start, $end, $compareMode),
        ]);
    }

    /**
     * The selected range, defaulting to this year to date.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function range(Request $request): array
    {
        $from = $this->parseDate($request->query('from'));
        $to = $this->parseDate($request->query('to'));

        if ($from && $to && $from <= $to) {
            return [$from->startOfDay(), $to->endOfDay()];
        }

        return [Carbon::now()->startOfYear(), Carbon::now()->endOfDay()];
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }
    }
}
