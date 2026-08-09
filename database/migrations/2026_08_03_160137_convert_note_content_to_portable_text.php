<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Notes move from plaintext to Portable Text so they can carry @mentions. The
 * column stays TEXT and only its contents change shape; each existing note
 * becomes a single normal block, preserving its text exactly.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('notes')->orderBy('id')->chunkById(200, function ($notes): void {
            foreach ($notes as $note) {
                $content = $note->content ?? '';

                // Already converted, or empty: leave it alone so the migration
                // is safe to re-run.
                if ($content === '' || str_starts_with(ltrim($content), '[')) {
                    continue;
                }

                // Blank lines separate blocks, matching
                // PortableText::fromPlainText. Spelled out rather than called,
                // so this conversion cannot change meaning if that helper does.
                $paragraphs = array_values(array_filter(
                    preg_split('/\R\s*\R/', trim($content)) ?: [],
                    fn (string $paragraph): bool => trim($paragraph) !== '',
                ));

                $blocks = array_map(fn (string $paragraph, int $index): array => [
                    '_type' => 'block',
                    '_key' => 'b'.($index + 1),
                    'style' => 'normal',
                    'markDefs' => [],
                    'children' => [[
                        '_type' => 'span',
                        '_key' => 's'.($index + 1),
                        'text' => trim($paragraph),
                        'marks' => [],
                    ]],
                ], $paragraphs, array_keys($paragraphs));

                DB::table('notes')->where('id', $note->id)->update([
                    'content' => json_encode($blocks),
                ]);
            }
        });
    }

    /**
     * Flatten each note back to the plain text of its spans. Anything a note
     * gained that plaintext cannot hold (a mention, a list) is lost, which is
     * the nature of going back.
     */
    public function down(): void
    {
        DB::table('notes')->orderBy('id')->chunkById(200, function ($notes): void {
            foreach ($notes as $note) {
                $blocks = json_decode((string) $note->content, true);

                if (! is_array($blocks)) {
                    continue;
                }

                $text = collect($blocks)
                    ->flatMap(fn (array $block): array => $block['children'] ?? [])
                    ->map(fn (array $child): string => $child['text'] ?? '')
                    ->implode('');

                DB::table('notes')->where('id', $note->id)->update(['content' => $text]);
            }
        });
    }
};
