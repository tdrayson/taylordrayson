<?php

use App\Support\PortableText;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Convert stored Editor.js documents on articles/pages to bare Portable
     * Text node arrays. Only rows whose decoded content is an object with a
     * `blocks` key are converted, so already-converted rows are skipped and
     * this migration is safely re-runnable.
     */
    public function up(): void
    {
        foreach (['articles', 'pages'] as $table) {
            foreach (DB::table($table)->select('id', 'content')->cursor() as $row) {
                $decoded = is_string($row->content) ? json_decode($row->content, true) : $row->content;

                if (! is_array($decoded) || ! array_key_exists('blocks', $decoded)) {
                    continue;
                }

                DB::table($table)->where('id', $row->id)->update([
                    'content' => json_encode(PortableText::fromEditorJs($decoded)),
                ]);
            }
        }
    }

    /**
     * One-way conversion: pre-conversion data is factory lorem, so there is
     * nothing meaningful to restore.
     */
    public function down(): void
    {
        //
    }
};
