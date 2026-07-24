<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DatabaseBackupService
{
    protected const DIRECTORY = 'backup';

    /**
     * Dump every table to a single .sql file under storage/app/private/backup.
     * Pure PHP/PDO (SHOW CREATE TABLE + INSERT statements) rather than shelling
     * out to the mysqldump binary, so it works the same on any machine without
     * needing that binary discoverable on PATH.
     */
    public function create(): string
    {
        $database = config('database.connections.'.config('database.default').'.database');
        $pdo = DB::connection()->getPdo();

        $sql = "-- Database backup for `{$database}`\n-- Generated: ".now()->toDateTimeString()."\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($this->tableNames() as $table) {
            $createRow = (array) DB::selectOne("SHOW CREATE TABLE `{$table}`");
            $createSql = $createRow['Create Table'] ?? reset($createRow);

            $sql .= "DROP TABLE IF EXISTS `{$table}`;\n{$createSql};\n\n";

            foreach (DB::table($table)->cursor() as $row) {
                $data = (array) $row;
                $columns = implode(', ', array_map(fn ($c) => "`{$c}`", array_keys($data)));
                $values = implode(', ', array_map(
                    fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v),
                    $data
                ));

                $sql .= "INSERT INTO `{$table}` ({$columns}) VALUES ({$values});\n";
            }

            $sql .= "\n";
        }

        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

        $filename = 'backup-'.$database.'-'.now()->format('Y-m-d_His').'.sql';
        Storage::disk('local')->put(self::DIRECTORY.'/'.$filename, $sql);

        return $filename;
    }

    /**
     * @return array<int, array{filename: string, size: int, modified: Carbon}>
     */
    public function list(): array
    {
        $disk = Storage::disk('local');

        return collect($disk->files(self::DIRECTORY))
            ->filter(fn ($path) => str_ends_with($path, '.sql'))
            ->map(fn ($path) => [
                'filename' => basename($path),
                'size' => $disk->size($path),
                'modified' => Carbon::createFromTimestamp($disk->lastModified($path)),
            ])
            ->sortByDesc('modified')
            ->values()
            ->all();
    }

    public function path(string $filename): string
    {
        return self::DIRECTORY.'/'.$this->safeFilename($filename);
    }

    public function exists(string $filename): bool
    {
        return Storage::disk('local')->exists($this->path($filename));
    }

    public function delete(string $filename): bool
    {
        return Storage::disk('local')->delete($this->path($filename));
    }

    /**
     * Only ever accept the exact filename shape this service itself generates —
     * guards download/delete against path traversal via a crafted filename.
     */
    protected function safeFilename(string $filename): string
    {
        $filename = basename($filename);

        abort_unless((bool) preg_match('/^backup-[A-Za-z0-9_\-]+\.sql$/', $filename), 404);

        return $filename;
    }

    /**
     * @return array<int, string>
     */
    protected function tableNames(): array
    {
        $driver = DB::connection()->getDriverName();
        $database = config('database.connections.'.config('database.default').'.database');

        $rows = match ($driver) {
            'sqlite' => DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"),
            default => DB::select('SHOW TABLES'),
        };

        return array_map(function ($row) use ($driver, $database) {
            $row = (array) $row;

            return $driver === 'sqlite' ? $row['name'] : ($row["Tables_in_{$database}"] ?? reset($row));
        }, $rows);
    }
}
