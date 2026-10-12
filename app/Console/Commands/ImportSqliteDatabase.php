<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

/**
 * Einmaliger Datenumzug von der alten SQLite-Datei in die aktuelle Datenbank
 * (Postgres). Das Zielschema legt `php artisan migrate` an; dieser Befehl
 * kopiert nur Daten:
 *
 * - bricht ab, wenn eine Ziel-Tabelle schon Daten enthält oder fehlt;
 * - kopiert in der Reihenfolge der Fremdschlüssel (Eltern vor Kindern), damit
 *   keine Constraints abgeschaltet werden müssen;
 * - wandelt SQLite-Booleans (0/1) in echte Booleans;
 * - setzt danach die id-Sequenzen und vergleicht die Zeilenzahlen.
 *
 * Alles läuft in einer Transaktion: Bei einem Fehler bleibt das Ziel leer.
 */
#[Signature('db:import-sqlite {path : Pfad zur SQLite-Datei}')]
#[Description('Kopiert alle Daten aus einer SQLite-Datei in die leere Ziel-Datenbank (einmaliger Umzug nach Postgres).')]
class ImportSqliteDatabase extends Command
{
    private const SOURCE_CONNECTION = 'sqlite_import';

    /**
     * Laufzeit-Tabellen, die neu entstehen dürfen, und die Migrations-Historie,
     * die `migrate` im Ziel selbst schreibt.
     */
    private const SKIPPED_TABLES = ['migrations', 'cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs'];

    private const CHUNK_SIZE = 500;

    public function handle(): int
    {
        $path = (string) $this->argument('path');

        if (! is_file($path)) {
            $this->error("SQLite-Datei nicht gefunden: {$path}");

            return self::FAILURE;
        }

        config(['database.connections.'.self::SOURCE_CONNECTION => [
            'driver' => 'sqlite',
            'database' => $path,
            'foreign_key_constraints' => false,
        ]]);

        $source = DB::connection(self::SOURCE_CONNECTION);
        $target = DB::connection();
        $tables = $this->tablesToCopy($source);

        if (! $this->targetIsReady($target, $tables)) {
            return self::FAILURE;
        }

        $target->transaction(function () use ($source, $target, $tables): void {
            foreach ($this->inDependencyOrder($target, $tables) as $table) {
                $this->copyTable($source, $target, $table);
            }

            if ($target->getDriverName() === 'pgsql') {
                $this->resetSequences($target, $tables);
            }
        });

        return $this->verifyRowCounts($source, $target, $tables) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return list<string>
     */
    private function tablesToCopy(Connection $source): array
    {
        $tables = array_column($source->getSchemaBuilder()->getTables(), 'name');

        return array_values(array_filter(
            $tables,
            fn (string $table): bool => ! in_array($table, self::SKIPPED_TABLES, true) && ! str_starts_with($table, 'sqlite_'),
        ));
    }

    /**
     * Jede Tabelle muss im Ziel existieren und leer sein; sonst würden Daten
     * verdoppelt oder ein veraltetes Schema befüllt.
     *
     * @param  list<string>  $tables
     */
    private function targetIsReady(Connection $target, array $tables): bool
    {
        $problems = [];

        foreach ($tables as $table) {
            if (! $target->getSchemaBuilder()->hasTable($table)) {
                $problems[] = "{$table}: fehlt im Ziel (erst `php artisan migrate` ausführen, gleicher Code-Stand wie die SQLite-Datei)";
            } elseif ($target->table($table)->exists()) {
                $problems[] = "{$table}: enthält im Ziel bereits Daten";
            }
        }

        foreach ($problems as $problem) {
            $this->error($problem);
        }

        if ($problems !== []) {
            $this->error('Abgebrochen, es wurde nichts kopiert.');
        }

        return $problems === [];
    }

    /**
     * Sortiert so, dass jede Tabelle nach den Tabellen kommt, auf die ihre
     * Fremdschlüssel zeigen.
     *
     * @param  list<string>  $tables
     * @return list<string>
     */
    private function inDependencyOrder(Connection $target, array $tables): array
    {
        $dependencies = [];

        foreach ($tables as $table) {
            $parents = array_column($target->getSchemaBuilder()->getForeignKeys($table), 'foreign_table');
            $dependencies[$table] = array_values(array_intersect(array_unique($parents), array_diff($tables, [$table])));
        }

        $ordered = [];

        while ($dependencies !== []) {
            $ready = array_keys(array_filter($dependencies, fn (array $parents): bool => array_diff($parents, $ordered) === []));

            if ($ready === []) {
                throw new \RuntimeException('Zirkuläre Fremdschlüssel zwischen: '.implode(', ', array_keys($dependencies)));
            }

            sort($ready);

            foreach ($ready as $table) {
                $ordered[] = $table;
                unset($dependencies[$table]);
            }
        }

        return $ordered;
    }

    private function copyTable(Connection $source, Connection $target, string $table): void
    {
        $booleanColumns = array_column(array_filter(
            $target->getSchemaBuilder()->getColumns($table),
            fn (array $column): bool => in_array($column['type_name'], ['bool', 'boolean'], true),
        ), 'name');

        $copied = 0;

        $source->table($table)->orderByRaw('rowid')->chunk(self::CHUNK_SIZE, function ($rows) use ($target, $table, $booleanColumns, &$copied): void {
            $records = $rows->map(function (object $row) use ($booleanColumns): array {
                $record = (array) $row;

                foreach ($booleanColumns as $column) {
                    if (isset($record[$column])) {
                        $record[$column] = (bool) $record[$column];
                    }
                }

                return $record;
            })->all();

            $target->table($table)->insert($records);
            $copied += count($records);
        });

        $this->line(sprintf('  %-25s %6d Zeilen', $table, $copied));
    }

    /**
     * Nach Inserts mit festen ids stehen die Postgres-Sequenzen noch auf 1;
     * neue Datensätze würden sonst mit vorhandenen ids kollidieren.
     *
     * @param  list<string>  $tables
     */
    private function resetSequences(Connection $target, array $tables): void
    {
        foreach ($tables as $table) {
            if (! $target->getSchemaBuilder()->hasColumn($table, 'id')) {
                continue;
            }

            $sequence = $target->scalar('SELECT pg_get_serial_sequence(?, ?)', [$table, 'id']);

            if ($sequence === null) {
                continue;
            }

            $target->statement(
                "SELECT setval(?, COALESCE((SELECT MAX(id) FROM \"{$table}\"), 1), (SELECT MAX(id) FROM \"{$table}\") IS NOT NULL)",
                [$sequence],
            );
        }
    }

    /**
     * @param  list<string>  $tables
     */
    private function verifyRowCounts(Connection $source, Connection $target, array $tables): bool
    {
        $rows = [];
        $matches = true;

        foreach ($tables as $table) {
            $sourceCount = $source->table($table)->count();
            $targetCount = $target->table($table)->count();
            $matches = $matches && $sourceCount === $targetCount;
            $rows[] = [$table, $sourceCount, $targetCount, $sourceCount === $targetCount ? 'ok' : 'ABWEICHUNG'];
        }

        $this->table(['Tabelle', 'SQLite', 'Ziel', ''], $rows);

        if ($matches) {
            $this->info('Umzug abgeschlossen, alle Zeilenzahlen stimmen.');
        } else {
            $this->error('Zeilenzahlen weichen ab, bitte prüfen.');
        }

        return $matches;
    }
}
