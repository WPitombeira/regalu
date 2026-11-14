<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->assertNoCollisions();
            $column = DB::table('information_schema.columns')
                ->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
                ->where('TABLE_NAME', 'amigo_secreto_participants')
                ->where('COLUMN_NAME', 'invite_email')
                ->first(['CHARACTER_SET_NAME as charset', 'COLLATION_NAME as collation']);
            if (! $column || ! preg_match('/\A[a-zA-Z0-9_]+\z/', $column->charset ?? '')
                || ! preg_match('/\A[a-zA-Z0-9_]+\z/', $column->collation ?? '')) {
                throw new RuntimeException('Cannot verify the invitation email collation. No schema was changed.');
            }

            // One atomic ALTER: a concurrent collision cannot leave half the guards installed.
            DB::statement('ALTER TABLE amigo_secreto_participants '
                .'ADD COLUMN invite_email_identity VARCHAR(255) CHARACTER SET '.$column->charset
                .' COLLATE '.$column->collation.' GENERATED ALWAYS AS (LOWER(TRIM(invite_email))) VIRTUAL INVISIBLE, '
                .'ADD UNIQUE INDEX event_participant_account_unique (event_id, user_id), '
                .'ADD UNIQUE INDEX event_participant_email_unique (event_id, invite_email_identity)');

            return;
        }

        if (! in_array($driver, ['sqlite', 'pgsql'], true)) {
            throw new RuntimeException('Participant identity migration requires SQLite, PostgreSQL or MySQL-family SQL.');
        }

        DB::transaction(function () {
            $this->assertNoCollisions();
            DB::statement('CREATE UNIQUE INDEX event_participant_account_unique ON amigo_secreto_participants (event_id, user_id)');
            DB::statement('CREATE UNIQUE INDEX event_participant_email_unique ON amigo_secreto_participants (event_id, LOWER(TRIM(invite_email)))');
        });
    }

    public function down(): void
    {
        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            // InnoDB may discard its implicit event FK index when the new unique
            // indexes cover it. Restore that prerequisite in the same ALTER.
            $hasEventIndex = array_any(DB::connection()->getSchemaBuilder()->getIndexes('amigo_secreto_participants'),
                fn (array $index): bool => ($index['columns'][0] ?? null) === 'event_id'
                    && ! in_array($index['name'], ['event_participant_account_unique', 'event_participant_email_unique'], true));
            DB::statement('ALTER TABLE amigo_secreto_participants '
                .($hasEventIndex ? '' : 'ADD INDEX amigo_secreto_participants_event_id_foreign (event_id), ')
                .'DROP INDEX event_participant_email_unique, '
                .'DROP INDEX event_participant_account_unique, '
                .'DROP COLUMN invite_email_identity');

            return;
        }

        DB::transaction(function () {
            DB::statement('DROP INDEX event_participant_email_unique');
            DB::statement('DROP INDEX event_participant_account_unique');
        });
    }

    private function assertNoCollisions(): void
    {
        if (DB::table('amigo_secreto_participants')
            ->selectRaw('1')
            ->whereNotNull('user_id')
            ->groupBy('event_id', 'user_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists()) {
            throw new RuntimeException('Duplicate event participants require an approved preservation plan before migration. No rows were changed.');
        }

        if (DB::table('amigo_secreto_participants')
            ->selectRaw('1')
            ->whereNotNull('invite_email')
            ->groupBy('event_id')
            ->groupByRaw('LOWER(TRIM(invite_email))')
            ->havingRaw('COUNT(*) > 1')
            ->exists()) {
            throw new RuntimeException('Duplicate event invitations require an approved preservation plan before migration. No rows were changed.');
        }
    }
};
