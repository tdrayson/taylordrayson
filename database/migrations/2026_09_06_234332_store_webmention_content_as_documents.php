<?php

use App\Support\PortableText;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A mention's content becomes Portable Text, so a reply keeps the links, quotes
 * and lists it was written with instead of arriving as flattened text.
 *
 * Existing rows were stored as that flattened text, and there is no way back to
 * what was thrown away: they are wrapped as they are, and only mentions fetched
 * after this carry the richer form.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->rewrite(
            leaveDocuments: true,
            convert: fn (string $content): string => (string) json_encode(PortableText::fromPlainText($content)),
        );

        Schema::table('webmentions', function (Blueprint $table): void {
            $table->json('content')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('webmentions', function (Blueprint $table): void {
            $table->text('content')->nullable()->change();
        });

        $this->rewrite(
            leaveDocuments: false,
            convert: fn (string $content): string => PortableText::plainText(json_decode($content, true) ?: []),
        );
    }

    /**
     * @param  callable(string): string  $convert
     */
    private function rewrite(bool $leaveDocuments, callable $convert): void
    {
        DB::table('webmentions')->orderBy('id')->chunkById(200, function ($mentions) use ($leaveDocuments, $convert): void {
            foreach ($mentions as $mention) {
                $content = (string) ($mention->content ?? '');

                if ($content === '' || $this->isDocument($content) === $leaveDocuments) {
                    continue;
                }

                DB::table('webmentions')->where('id', $mention->id)->update(['content' => $convert($content)]);
            }
        });
    }

    private function isDocument(string $content): bool
    {
        $decoded = json_decode($content, true);

        return is_array($decoded) && array_is_list($decoded) && ($decoded === [] || is_array($decoded[0] ?? null));
    }
};
