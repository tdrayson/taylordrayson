<?php

use App\Support\PortableText;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Comments become Portable Text, the format everything else on the site is
 * written in, so a comment carrying a link is one document rather than a second
 * shape the conversation has to know how to render.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->rewrite(
            leaveDocuments: true,
            convert: fn (string $body): string => (string) json_encode(PortableText::fromPlainText($body)),
        );

        Schema::table('comments', function (Blueprint $table): void {
            $table->json('body')->change();
        });
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table): void {
            $table->text('body')->change();
        });

        $this->rewrite(
            leaveDocuments: false,
            convert: fn (string $body): string => PortableText::plainText(json_decode($body, true) ?: []),
        );
    }

    /**
     * Rewrite every body through $convert, leaving the ones already in the
     * target shape alone so a re-run cannot nest a document inside another.
     *
     * @param  bool  $leaveDocuments  Skip bodies that are already documents
     *                                (going up), or the ones that are not
     *                                (coming back down).
     * @param  callable(string): string  $convert
     */
    private function rewrite(bool $leaveDocuments, callable $convert): void
    {
        DB::table('comments')->orderBy('id')->chunkById(200, function ($comments) use ($leaveDocuments, $convert): void {
            foreach ($comments as $comment) {
                $body = (string) $comment->body;

                if ($this->isDocument($body) === $leaveDocuments) {
                    continue;
                }

                DB::table('comments')->where('id', $comment->id)->update(['body' => $convert($body)]);
            }
        });
    }

    /**
     * Decoded rather than sniffed for a leading bracket: a comment that opens
     * "[citation needed]" is text, and skipping it would leave it behind.
     */
    private function isDocument(string $body): bool
    {
        $decoded = json_decode($body, true);

        return is_array($decoded) && array_is_list($decoded) && ($decoded === [] || is_array($decoded[0] ?? null));
    }
};
