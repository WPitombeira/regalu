<?php

namespace App\Console\Commands;

use App\Models\AmigoSecretoEvent;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class RevealAssignments extends Command
{
    protected $signature = 'secret-santa:reveal';

    protected $description = 'Reveal due Secret Santa assignments to their respective buyers';

    public function handle(): int
    {
        $eligible = AmigoSecretoEvent::query()->where('is_archived', false)
            ->where('status', 'DRAWS_COMPLETE')
            ->where(function (Builder $query) {
                $query->whereDate('reveal_date', '<=', today())
                    ->orWhere(function (Builder $query) {
                        $query->whereNull('reveal_date')->where('recipient_visibility', 'NAMED')
                            ->whereDate('event_date', '<=', today());
                    });
            });
        $count = 0;

        foreach ($eligible->lazyById(100) as $event) {
            $count += DB::transaction(function () use ($eligible, $event): int {
                $updated = (clone $eligible)->whereKey($event->id)->update(['status' => 'REVEALED']);
                if ($updated === 1) {
                    $event->draws()->whereNull('revealed_at')->update(['revealed_at' => now()]);
                    NotificationService::assignmentsAvailable($event->refresh(), revealed: true);
                }

                return $updated;
            });
        }

        $this->info("Revealed {$count} event(s).");

        return self::SUCCESS;
    }
}
