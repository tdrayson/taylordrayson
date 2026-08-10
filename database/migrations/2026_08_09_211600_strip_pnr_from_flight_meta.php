<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Drops the booking reference from every flight's meta. */
return new class extends Migration
{
    private const KEY = 'pnr';

    public function up(): void
    {
        DB::table('flights')->whereNotNull('meta')->orderBy('id')->each(function (object $flight): void {
            $meta = json_decode((string) $flight->meta, true);

            if (! is_array($meta) || ! array_key_exists(self::KEY, $meta)) {
                return;
            }

            unset($meta[self::KEY]);

            DB::table('flights')->where('id', $flight->id)->update([
                'meta' => $meta === [] ? null : json_encode($meta),
            ]);
        });
    }

    /**
     * Deliberately irreversible: the point is that the value is gone, and there
     * is nowhere left to restore it from.
     */
    public function down(): void {}
};
