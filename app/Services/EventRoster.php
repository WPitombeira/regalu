<?php

namespace App\Services;

use App\Models\AmigoSecretoEvent;
use Closure;
use Illuminate\Support\Facades\DB;

final class EventRoster
{
    /**
     * Serialize roster readers and writers on their event before reading membership.
     *
     * @template T
     *
     * @param  Closure(AmigoSecretoEvent): T  $operation
     * @return T
     */
    public static function locked(int $eventId, Closure $operation): mixed
    {
        return DB::transaction(function () use ($eventId, $operation) {
            // A write also obtains SQLite's writer lock; SELECT FOR UPDATE alone does not.
            // Do not inspect affected-row counts: unchanged rows may report zero on MySQL.
            DB::table('amigo_secreto_events')->where('id', $eventId)->update(['id' => DB::raw('id')]);
            $event = AmigoSecretoEvent::whereKey($eventId)->lockForUpdate()->firstOrFail();

            return $operation($event);
        });
    }
}
