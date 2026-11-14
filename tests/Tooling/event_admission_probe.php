<?php

// Real independent PHP connections; invoked only by the isolated MySQL operator.
use App\Livewire\AmigoSecreto\EventDetail;
use App\Livewire\AmigoSecreto\ExecuteDraw;
use App\Livewire\AmigoSecreto\JoinEvent;
use App\Livewire\AmigoSecreto\ManageExclusions;
use App\Livewire\AmigoSecreto\ManageParticipants;
use App\Models\AmigoSecretoEvent;
use App\Models\AmigoSecretoParticipant;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

function admissionCheck(bool $condition, string $label): void
{
    if (! $condition) {
        throw new LogicException($label);
    }
}

function admissionConnection(string $driver, string $directory): void
{
    admissionCheck(in_array($driver, ['mysql', 'sqlite'], true), 'driver_allowlist');
    if ($driver === 'sqlite') {
        config(['database.connections.admission' => [
            'driver' => 'sqlite', 'database' => $directory.'/admission.sqlite',
            'url' => null, 'prefix' => '', 'foreign_key_constraints' => true, 'busy_timeout' => 100,
        ], 'database.default' => 'admission']);
        DB::purge('admission');
    } else {
        config(['database.default' => 'mysql']);
    }
    admissionCheck(DB::connection()->getDriverName() === $driver, 'native_driver');
    config(['queue.default' => 'database', 'queue.connections.database.connection' => config('database.default')]);
    // Resolving this connection does not start a worker or deliver mail.
}

function admissionInput(): array
{
    $read = [STDIN];
    $write = $except = [];
    admissionCheck(stream_select($read, $write, $except, 15) === 1, 'worker_input_deadline');
    $line = fgets(STDIN, 8192);
    admissionCheck(is_string($line) && str_ends_with($line, "\n"), 'worker_input_bound');

    return json_decode($line, true, flags: JSON_THROW_ON_ERROR);
}

function admissionEmit(array $message): void
{
    fwrite(STDOUT, json_encode($message, JSON_THROW_ON_ERROR)."\n");
    fflush(STDOUT);
}

final class AdmissionStatement extends PDOStatement
{
    public static bool $busy = false;

    public function execute(?array $params = null): bool
    {
        try {
            return parent::execute($params);
        } catch (PDOException $error) {
            // Observe the real SQLite driver result even if a component catches
            // RuntimeException to render feedback. No result/exception is replaced.
            self::$busy = self::$busy || ($error->errorInfo[1] ?? null) === 5;
            throw $error;
        }
    }
}

function admissionWorker(string $directory): void
{
    $input = admissionInput();
    admissionConnection($input['driver'], $directory);
    $event = AmigoSecretoEvent::findOrFail($input['event']);
    $user = User::findOrFail($input['user']);
    [$class, $method, $arguments] = match ($input['action']) {
        'join' => [JoinEvent::class, 'join', []],
        'invite' => [ManageParticipants::class, 'inviteByEmail', []],
        'draw' => [ExecuteDraw::class, 'executeDraw', []],
        'remove' => [ManageParticipants::class, 'removeParticipant', [$input['participant']]],
        'exclude' => [ManageExclusions::class, 'addExclusion', []],
        'unexclude' => [ManageExclusions::class, 'removeExclusion', [$input['exclusion']]],
        'consent' => [EventDetail::class, 'saveWishlist', []],
    };
    // Mount through Livewire, then invoke the real action without the HTTP test renderer.
    // HTTP middleware/transport are covered separately by the browser/feature lanes.
    /** @var JoinEvent|ManageParticipants|ExecuteDraw|ManageExclusions|EventDetail $component */
    $component = Livewire::actingAs($user)->test($class, $input['action'] === 'join'
        ? ['code' => $event->event_code] : ['event' => $event])->instance();
    if ($input['action'] === 'invite') {
        $component->inviteEmail = $input['email'];
    } elseif ($input['action'] === 'exclude') {
        $component->userAId = $input['pair'][0];
        $component->userBId = $input['pair'][1];
    } elseif ($input['action'] === 'consent') {
        $component->wishlist_id = $input['wishlist'];
    }
    $connection = DB::connection();
    if ($input['driver'] === 'sqlite') {
        $connection->getPdo()->setAttribute(PDO::ATTR_STATEMENT_CLASS, [AdmissionStatement::class]);
    }
    $outer = $input['snapshot'] ?? false;
    if ($outer || ($input['rollback'] ?? false)) {
        $connection->beginTransaction();
        // Establish an older RR snapshot before the other connection commits.
        $event->participants()->count();
    }
    $identity = $input['driver'] === 'mysql'
        ? (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id : getmypid();
    $isLock = fn (string $sql): bool => preg_match('/^update ["`]amigo_secreto_events["`] set ["`]id["`] = id/', $sql) === 1;
    $attempted = false;
    $connection->beforeExecuting(function (string $sql) use ($isLock, &$attempted): void {
        if ($isLock($sql) && ! $attempted) {
            $attempted = true;
            admissionEmit(['stage' => 'attempt']);
        }
    });
    $held = false;
    DB::listen(function (QueryExecuted $query) use ($input, $isLock, &$held): void {
        if (($input['hold'] ?? false) && ! $held && $isLock($query->sql)) {
            $held = true;
            admissionEmit(['stage' => 'locked']);
            admissionCheck(admissionInput() === ['command' => 'release'], 'release_command');
        }
    });
    admissionEmit(['stage' => 'prepared', 'connection' => $identity,
        'isolation' => $input['driver'] === 'mysql' ? DB::selectOne('SELECT @@transaction_isolation AS level')->level : 'file']);
    admissionCheck(admissionInput() === ['command' => 'go'], 'go_command');
    for ($attempt = 0; $attempt < 2; $attempt++) {
        $outcome = 'ok';
        try {
            $component->{$method}(...$arguments);
            if ($component->getErrorBag()->isNotEmpty()) {
                $outcome = 'validation';
            }
        } catch (ValidationException) {
            $outcome = 'validation';
        } catch (AuthorizationException|HttpException $error) {
            $outcome = $error instanceof HttpException ? 'http_'.$error->getStatusCode() : 'http_403';
        } catch (QueryException $error) {
            $outcome = $input['driver'] === 'sqlite' && ($error->errorInfo[1] ?? null) === 5 ? 'busy' : 'database_error';
        }
        if ($input['rollback'] ?? false) {
            $connection->rollBack();
        } elseif ($outer) {
            $connection->commit();
        }
        admissionCheck($connection->transactionLevel() === 0, 'transaction_leaked');
        admissionEmit(['stage' => 'result', 'outcome' => AdmissionStatement::$busy ? 'busy' : $outcome]);
        if (! AdmissionStatement::$busy || ! ($input['retry'] ?? false) || $attempt === 1) {
            return;
        }
        admissionCheck(admissionInput() === ['command' => 'retry'], 'explicit_retry_command');
        AdmissionStatement::$busy = $attempted = false;
    }
}

final class AdmissionProcess
{
    private $process;

    private array $pipes = [];

    private string $buffer = '';

    private int $bytes = 0;

    public array $prepared;

    public ?int $exit = null;

    public function __construct(array $input)
    {
        $php = json_decode((string) getenv('REGALU_PROBE_PHP'), true, flags: JSON_THROW_ON_ERROR);
        admissionCheck(is_array($php) && $php[0] === 'php', 'worker_runtime');
        $env = getenv();
        $env['REGALU_ADMISSION_WORKER'] = '1';
        $this->process = proc_open([...$php, __DIR__.'/event_join_mysql.php'],
            [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $this->pipes, dirname(__DIR__, 2), $env);
        admissionCheck(is_resource($this->process), 'worker_start');
        stream_set_blocking($this->pipes[1], false);
        stream_set_blocking($this->pipes[2], false);
        $this->send($input);
    }

    public function send(array $input): void
    {
        $line = json_encode($input, JSON_THROW_ON_ERROR)."\n";
        admissionCheck(fwrite($this->pipes[0], $line) === strlen($line), 'worker_send');
        fflush($this->pipes[0]);
    }

    public function receive(?string $stage = null): array
    {
        $deadline = microtime(true) + 15;
        while (! str_contains($this->buffer, "\n")) {
            admissionCheck(microtime(true) < $deadline, 'parent_read_deadline');
            $read = array_values(array_filter([$this->pipes[1], $this->pipes[2]], fn ($pipe) => ! feof($pipe)));
            admissionCheck($read !== [], 'worker_eof');
            $write = $except = [];
            admissionCheck(stream_select($read, $write, $except, 1) !== false, 'worker_select');
            foreach ($read as $pipe) {
                $chunk = fread($pipe, 8192);
                admissionCheck(is_string($chunk), 'worker_read');
                $this->bytes += strlen($chunk);
                admissionCheck($this->bytes <= 65536, 'worker_output_limit');
                admissionCheck($pipe !== $this->pipes[2] || $chunk === '', 'worker_stderr');
                if ($pipe === $this->pipes[1]) {
                    $this->buffer .= $chunk;
                }
            }
        }
        [$line, $this->buffer] = explode("\n", $this->buffer, 2);
        $message = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
        admissionCheck($stage === null || ($message['stage'] ?? null) === $stage, 'worker_stage_'.$stage);

        return $message;
    }

    public function finish(?array $result = null): array
    {
        $result ??= $this->receive('result');
        fclose($this->pipes[0]);
        $deadline = microtime(true) + 5;
        do {
            $state = proc_get_status($this->process);
            if (! $state['running']) {
                break;
            }
            admissionCheck(microtime(true) < $deadline, 'worker_exit_deadline');
            usleep(10000);
        } while (true);
        admissionCheck($state['exitcode'] === 0, 'worker_nonzero');
        admissionCheck($this->buffer === '' && stream_get_contents($this->pipes[1]) === ''
            && stream_get_contents($this->pipes[2]) === '', 'worker_trailing_output');
        $this->close();
        admissionCheck($this->exit === 0, 'worker_reap');

        return $result;
    }

    public function close(): void
    {
        if (! is_resource($this->process)) {
            return;
        }
        if (proc_get_status($this->process)['running']) {
            proc_terminate($this->process, 9);
        }
        foreach ($this->pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }
        $this->exit = proc_close($this->process);
    }

    public function __destruct()
    {
        $this->close();
    }
}

function admissionMigration(string $root): array
{
    $migration = require $root.'/database/migrations/2026_11_14_000001_enforce_event_participant_identity.php';
    $rows = fn () => DB::table('amigo_secreto_participants')->orderBy('id')->get()->toJson();
    $before = $rows();
    $migration->down();
    $migration->up();
    admissionCheck($rows() === $before, 'migration_row_preservation');
    $indexes = DB::connection()->getSchemaBuilder()->getIndexes('amigo_secreto_participants');
    foreach (['event_participant_account_unique', 'event_participant_email_unique'] as $name) {
        admissionCheck(count(array_filter($indexes, fn ($index) => $index['name'] === $name && $index['unique'])) === 1, 'migration_unique_index');
    }
    $original = DB::table('amigo_secreto_participants')->whereNotNull('user_id')->first();
    if (DB::connection()->getDriverName() === 'mysql') {
        admissionCheck(! array_key_exists('invite_email_identity', (array) $original), 'guard_column_invisible');
        $columns = DB::table('information_schema.columns')->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
            ->where('TABLE_NAME', 'amigo_secreto_participants')->whereIn('COLUMN_NAME', ['invite_email', 'invite_email_identity'])
            ->get(['COLUMN_NAME as name', 'CHARACTER_SET_NAME as charset', 'COLLATION_NAME as collation', 'EXTRA as extra'])->keyBy('name');
        admissionCheck($columns['invite_email']->charset === $columns['invite_email_identity']->charset
            && $columns['invite_email']->collation === $columns['invite_email_identity']->collation
            && str_contains($columns['invite_email_identity']->extra, 'INVISIBLE'), 'guard_collation_visibility');
    }
    foreach (['account', 'email'] as $kind) {
        $copy = (array) $original;
        unset($copy['id']);
        if ($kind === 'email') {
            $copy['user_id'] = null;
            $copy['invite_email'] = ' Guard@Example.Test ';
            $guardRow = DB::table('amigo_secreto_participants')->insertGetId($copy);
            $copy['invite_email'] = 'guard@example.test';
        }
        $state = $rows();
        $rejected = false;
        try {
            DB::table('amigo_secreto_participants')->insert($copy);
        } catch (QueryException $error) {
            $rejected = ($error->errorInfo[0] ?? null) === '23000'
                && ($error->errorInfo[1] ?? null) === (DB::connection()->getDriverName() === 'mysql' ? 1062 : 19);
        }
        admissionCheck($rejected && $rows() === $state, 'native_unique_rejection_'.$kind);
        if ($kind === 'email') {
            DB::table('amigo_secreto_participants')->where('id', $guardRow)->delete();
        }
    }
    foreach (['account', 'email'] as $kind) {
        $migration->down();
        $copy = (array) $original;
        unset($copy['id']);
        if ($kind === 'email') {
            $copy['user_id'] = null;
            $copy['invite_email'] = ' Duplicate@Example.Test ';
            $first = DB::table('amigo_secreto_participants')->insertGetId($copy);
            $copy['invite_email'] = 'duplicate@example.test';
        }
        $duplicate = DB::table('amigo_secreto_participants')->insertGetId($copy);
        $collision = $rows();
        $refused = false;
        try {
            $migration->up();
        } catch (RuntimeException $error) {
            $refused = ! $error instanceof QueryException && str_contains($error->getMessage(), 'approved preservation plan');
        }
        admissionCheck($refused && $rows() === $collision, 'migration_collision_preserved_'.$kind);
        admissionCheck(! array_any(DB::connection()->getSchemaBuilder()->getIndexes('amigo_secreto_participants'),
            fn ($index) => str_starts_with($index['name'], 'event_participant_')), 'migration_no_partial_indexes');
        DB::table('amigo_secreto_participants')->where('id', $duplicate)->delete();
        if ($kind === 'email') {
            DB::table('amigo_secreto_participants')->where('id', $first)->delete();
        }
        $migration->up();
        admissionCheck($rows() === $before, 'migration_reapply_preserved');
    }

    return ['passed' => true, 'populated_round_trip' => true, 'native_unique_rejections' => true,
        'account_collision_preserved' => true, 'email_collision_preserved' => true];
}

function admissionFixture(): array
{
    $members = User::factory()->count(5)->create();
    $event = AmigoSecretoEvent::factory()->create(['organizer_id' => $members[0]->id]);
    $participants = [];
    foreach ($members->take(4) as $member) {
        $participants[] = AmigoSecretoParticipant::create(['event_id' => $event->id, 'user_id' => $member->id, 'status' => 'ACCEPTED'])->id;
    }

    return [$event, $members, $participants];
}

function admissionRace(string $driver, string $case): array
{
    [$event, $members, $participants] = admissionFixture();
    [$left, $right] = match ($case) {
        'same_account', 'rr_join' => ['join', 'join'],
        'same_email', 'rr_invite', 'rollback_invite' => ['invite', 'invite'],
        'draw_twice' => ['draw', 'draw'],
        'invite_then_join' => ['invite', 'join'],
        'join_then_invite' => ['join', 'invite'],
        'draw_then_join' => ['draw', 'join'],
        'join_then_draw' => ['join', 'draw'],
        'draw_then_remove' => ['draw', 'remove'],
        'remove_then_draw' => ['remove', 'draw'],
        'draw_then_exclude' => ['draw', 'exclude'],
        'exclude_then_draw' => ['exclude', 'draw'],
        'draw_then_unexclude' => ['draw', 'unexclude'],
        'unexclude_then_draw' => ['unexclude', 'draw'],
        'consent_then_remove' => ['consent', 'remove'],
        'remove_then_consent' => ['remove', 'consent'],
    };
    $exclusion = str_contains($case, 'unexclude')
        ? $event->exclusions()->create(['user_a_id' => $members[1]->id, 'user_b_id' => $members[2]->id])->id : null;
    $wishlist = str_contains($case, 'consent') ? Wishlist::factory()->create(['user_id' => $members[3]->id])->id : null;
    $base = ['driver' => $driver, 'event' => $event->id, 'email' => $members[4]->email,
        'participant' => $participants[3], 'pair' => [$members[1]->id, $members[2]->id],
        'exclusion' => $exclusion, 'wishlist' => $wishlist];
    $input = fn ($action, $hold) => $base + ['action' => $action, 'hold' => $hold,
        'user' => $members[match ($action) {
            'join' => 4, 'consent' => 3, default => 0
        }]->id,
        'retry' => $driver === 'sqlite' && ! $hold,
        'snapshot' => ! $hold && str_starts_with($case, 'rr_'),
        'rollback' => $hold && $case === 'rollback_invite'];
    $jobs = DB::table('jobs')->count();
    $notices = DB::table('notifications')->count();
    $processes = [];
    try {
        $a = $processes[] = new AdmissionProcess($input($left, true));
        $b = $processes[] = new AdmissionProcess($input($right, false));
        $a->prepared = $a->receive('prepared');
        $b->prepared = $b->receive('prepared');
        admissionCheck($a->prepared['connection'] !== $b->prepared['connection'], 'independent_connections');
        $a->send(['command' => 'go']);
        $a->receive('attempt');
        $a->receive('locked');
        $b->send(['command' => 'go']);
        $b->receive('attempt');
        if ($driver === 'mysql') {
            admissionCheck($a->prepared['isolation'] === 'REPEATABLE-READ' && $b->prepared['isolation'] === 'REPEATABLE-READ', 'native_isolation');
            $deadline = microtime(true) + 10;
            do {
                $waits = DB::select('SELECT 1 FROM performance_schema.data_lock_waits w '
                    .'JOIN performance_schema.threads r ON r.THREAD_ID = w.REQUESTING_THREAD_ID '
                    .'JOIN performance_schema.threads b ON b.THREAD_ID = w.BLOCKING_THREAD_ID '
                    .'JOIN performance_schema.data_locks l ON l.ENGINE_LOCK_ID = w.REQUESTING_ENGINE_LOCK_ID '
                    .'WHERE r.PROCESSLIST_ID = ? AND b.PROCESSLIST_ID = ? AND l.OBJECT_NAME = ?',
                    [$b->prepared['connection'], $a->prepared['connection'], 'amigo_secreto_events']);
                if ($waits !== []) {
                    break;
                }
                admissionCheck(microtime(true) < $deadline, 'native_wait_not_observed');
                usleep(10000);
            } while (true);
            $barrier = 'mysql_event_row_wait';
        } else {
            admissionCheck($b->receive('result')['outcome'] === 'busy', 'sqlite_contender_busy');
            $barrier = 'sqlite_busy_then_explicit_retry';
        }
        $a->send(['command' => 'release']);
        $outcomes = [$a->finish()['outcome']];
        if ($driver === 'sqlite') {
            $b->send(['command' => 'retry']);
        }
        // A post-draw management retry may refuse at its fresh pre-authorization
        // before attempting another lock. Collect that valid refusal explicitly.
        if ($driver === 'sqlite') {
            $next = $b->receive();
            admissionCheck(in_array($next['stage'], ['attempt', 'result'], true), 'retry_stage');
            $outcomes[] = $b->finish($next['stage'] === 'result' ? $next : null)['outcome'];
        } else {
            $outcomes[] = $b->finish()['outcome'];
        }
        $denied = str_starts_with($case, 'draw_then_') || $case === 'remove_then_consent';
        $duplicate = in_array($case, ['same_email', 'rr_invite', 'join_then_invite'], true);
        admissionCheck($outcomes === ['ok', $denied ? 'http_403' : ($duplicate ? 'validation' : 'ok')], 'action_outcomes');
        $counts = match ($case) {
            'same_account', 'rr_join', 'join_then_invite' => [5, 0, 0, 0],
            'same_email', 'rr_invite', 'invite_then_join', 'rollback_invite' => [5, 0, 1, 1],
            'draw_twice', 'draw_then_join', 'draw_then_remove', 'draw_then_exclude',
            'exclude_then_draw', 'draw_then_unexclude', 'unexclude_then_draw' => [4, 4, 4, 4],
            'join_then_draw' => [5, 5, 5, 5],
            'remove_then_draw' => [3, 3, 3, 3],
            'consent_then_remove', 'remove_then_consent' => [3, 0, 0, 0],
        };
        admissionCheck([$event->participants()->count(), $event->draws()->count(), DB::table('jobs')->count() - $jobs,
            DB::table('notifications')->count() - $notices] === $counts, 'persisted_exact_counts');
        if (in_array($case, ['same_account', 'rr_join', 'join_then_invite', 'invite_then_join', 'join_then_draw'], true)) {
            admissionCheck($event->participants()->where('user_id', $members[4]->id)->sole()->status === 'ACCEPTED', 'one_accepted_account');
        }
        $expectedExclusions = in_array($case, ['exclude_then_draw', 'draw_then_unexclude'], true) ? 1 : 0;
        admissionCheck($event->exclusions()->count() === $expectedExclusions, 'exclusion_count');
        if ($event->draws()->exists()) {
            $accepted = $event->participants()->where('status', 'ACCEPTED')->pluck('user_id')->sort()->values()->all();
            $draws = $event->draws()->get();
            admissionCheck($draws->pluck('drawer_user_id')->sort()->values()->all() === $accepted
                && $draws->pluck('target_user_id')->sort()->values()->all() === $accepted
                && $draws->every(fn ($draw) => $draw->drawer_user_id !== $draw->target_user_id)
                && $event->fresh()->status === 'DRAWS_COMPLETE', 'draw_bijection');
            foreach ($event->exclusions()->get() as $pair) {
                admissionCheck($draws->every(fn ($draw) => ! in_array([$draw->drawer_user_id, $draw->target_user_id],
                    [[$pair->user_a_id, $pair->user_b_id], [$pair->user_b_id, $pair->user_a_id]], true)), 'draw_exclusion_obeyed');
            }
        }

        return ['case' => $case, 'passed' => true, 'barrier' => $barrier, 'outcomes' => $outcomes,
            'workers' => count($processes), 'native_exits' => array_map(fn ($process) => $process->exit, $processes)];
    } finally {
        foreach ($processes as $process) {
            $process->close();
        }
    }
}

function admissionProbe(string $root, string $directory): array
{
    $results = [];
    $stage = 'initial';
    try {
        foreach (['mysql', 'sqlite'] as $driver) {
            $stage = $driver.'_bootstrap';
            if ($driver === 'sqlite') {
                admissionCheck(! file_exists($directory.'/admission.sqlite'), 'sqlite_already_exists');
                touch($directory.'/admission.sqlite');
            }
            admissionConnection($driver, $directory);
            if ($driver === 'sqlite') {
                admissionCheck(Artisan::call('migrate', ['--force' => true]) === 0, 'sqlite_migration');
                admissionFixture();
            }
            $stage = $driver.'_migration';
            $migration = admissionMigration($root);
            $cases = [];
            $matrix = ['same_account', 'same_email', 'draw_twice', 'invite_then_join', 'join_then_invite',
                'draw_then_join', 'join_then_draw', 'draw_then_remove', 'remove_then_draw',
                'draw_then_exclude', 'exclude_then_draw', 'draw_then_unexclude', 'unexclude_then_draw',
                'rollback_invite', 'consent_then_remove', 'remove_then_consent'];
            if ($driver === 'mysql') {
                array_push($matrix, 'rr_join', 'rr_invite');
            }
            foreach ($matrix as $case) {
                $stage = $driver.'_'.$case;
                $cases[] = admissionRace($driver, $case);
            }
            $results[] = ['driver' => $driver, 'migration' => $migration, 'cases' => $cases];
        }

        return ['passed' => true, 'drivers' => $results];
    } catch (Throwable $error) {
        return ['passed' => false, 'stage' => $stage, 'error_class' => $error::class,
            'error_location' => basename($error->getFile()).':'.$error->getLine(),
            'invariant' => $error instanceof LogicException ? $error->getMessage() : null,
            'sqlstate' => $error instanceof QueryException ? $error->errorInfo[0] ?? null : null,
            'driver_code' => $error instanceof QueryException ? $error->errorInfo[1] ?? null : null,
            'drivers' => $results];
    } finally {
        config(['database.default' => 'mysql']);
    }
}
