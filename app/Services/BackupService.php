<?php

namespace App\Services;

use App\Models\Backup;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PDO;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class BackupService
{
    /**
     * The storage disk and directory backups are written to.
     */
    protected string $disk = 'local';

    protected string $directory = 'backups';

    /**
     * Create and execute a database backup, returning the persisted record.
     * Runs synchronously — fine for the scheduled CLI command, but web-request
     * callers should use createPending()+queue a RunDatabaseBackup job instead.
     */
    public function backupDatabase(array $data = []): Backup
    {
        return $this->run($this->createPending($data));
    }

    /**
     * Insert a pending backup record without running the (potentially slow)
     * dump — cheap enough to call inline from a web request.
     */
    public function createPending(array $data = []): Backup
    {
        return Backup::create([
            'backup_no' => $data['backup_no'] ?? $this->generateBackupNo(),
            'backup_type' => 'database',
            'storage_location' => $this->disk,
            'status' => 'pending',
            'created_by' => $data['created_by'] ?? auth()->id(),
            'remarks' => $data['remarks'] ?? null,
        ]);
    }

    /**
     * Run the actual dump for a pending backup record. This is the slow part
     * and belongs on a queue worker, not the web request.
     */
    public function run(Backup $backup): Backup
    {
        $backup->update(['status' => 'running', 'started_at' => now()]);

        try {
            $relativePath = $this->dumpDatabase($backup->backup_no);
            $checksum = $this->encryptAndVerify($relativePath);

            $backup->update([
                'status' => 'success',
                'file_path' => $relativePath,
                'file_size' => Storage::disk($this->disk)->size($relativePath),
                'checksum' => $checksum,
                'verified' => true,
                'completed_at' => now(),
            ]);
        } catch (Throwable $e) {
            $backup->update([
                'status' => 'failed',
                'verified' => false,
                'completed_at' => now(),
                'remarks' => trim(($backup->remarks ? $backup->remarks."\n" : '').'Error: '.$e->getMessage()),
            ]);

            throw $e;
        }

        return $backup->refresh();
    }

    /**
     * Restore a previously created database backup. Destructive: overwrites the
     * current database with the backup's contents. The file is checksum- and
     * structurally-verified before anything is overwritten.
     */
    public function restoreDatabase(Backup $backup): void
    {
        if (! $backup->file_path || ! Storage::disk($this->disk)->exists($backup->file_path)) {
            throw new RuntimeException('Backup file is missing; cannot restore.');
        }

        $encrypted = Storage::disk($this->disk)->get($backup->file_path);

        if ($backup->checksum && hash('sha256', $encrypted) !== $backup->checksum) {
            throw new RuntimeException('Backup checksum mismatch; refusing to restore a possibly corrupted or tampered file.');
        }

        $plaintext = Crypt::decryptString($encrypted);

        if (! $this->verifyDumpContents($backup->file_path, $plaintext)) {
            throw new RuntimeException('Backup failed integrity verification; refusing to restore.');
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'dmims_restore_');
        file_put_contents($tempPath, $plaintext);

        try {
            $connection = config('database.default');
            $driver = config("database.connections.{$connection}.driver");

            match ($driver) {
                'sqlite' => $this->restoreSqlite($connection, $tempPath),
                'mysql', 'mariadb' => $this->restoreMysql($connection, $tempPath),
                default => throw new RuntimeException("Unsupported database driver for restore: {$driver}"),
            };

            $backup->update(['status' => 'restored']);
        } finally {
            @unlink($tempPath);
        }
    }

    /**
     * Encrypt the just-written plaintext dump in place (encryption at rest),
     * then immediately decrypt and structurally verify it before trusting the
     * backup as restorable. Returns the checksum of the encrypted file.
     */
    protected function encryptAndVerify(string $relativePath): string
    {
        $plaintext = Storage::disk($this->disk)->get($relativePath);
        $encrypted = Crypt::encryptString($plaintext);

        Storage::disk($this->disk)->put($relativePath, $encrypted);

        $decrypted = Crypt::decryptString(Storage::disk($this->disk)->get($relativePath));

        if (! $this->verifyDumpContents($relativePath, $decrypted)) {
            throw new RuntimeException('Backup verification failed: dump content did not pass integrity check.');
        }

        return hash('sha256', $encrypted);
    }

    /**
     * Structural sanity check on a decrypted dump, keyed off the file
     * extension set by dumpSqlite()/dumpMysql(). Not a full restore drill
     * (that needs a scratch database) but catches truncated/corrupt dumps
     * before they're trusted.
     */
    protected function verifyDumpContents(string $relativePath, string $contents): bool
    {
        if (str_ends_with($relativePath, '.sqlite')) {
            return $this->verifySqliteDump($contents);
        }

        if (str_ends_with($relativePath, '.sql')) {
            return $contents !== '' && str_contains($contents, 'CREATE TABLE');
        }

        return false;
    }

    protected function verifySqliteDump(string $contents): bool
    {
        if ($contents === '') {
            return false;
        }

        $temp = tempnam(sys_get_temp_dir(), 'dmims_verify_');
        file_put_contents($temp, $contents);

        try {
            $pdo = new PDO('sqlite:'.$temp);

            return $pdo->query('PRAGMA integrity_check')->fetchColumn() === 'ok';
        } catch (Throwable) {
            return false;
        } finally {
            @unlink($temp);
        }
    }

    /**
     * Dump the default database connection to a file on the backup disk and
     * return the path relative to that disk.
     */
    protected function dumpDatabase(string $backupNo): string
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        Storage::disk($this->disk)->makeDirectory($this->directory);

        return match ($driver) {
            'sqlite' => $this->dumpSqlite($connection, $backupNo),
            'mysql', 'mariadb' => $this->dumpMysql($connection, $backupNo),
            default => throw new RuntimeException("Unsupported database driver for backup: {$driver}"),
        };
    }

    protected function dumpSqlite(string $connection, string $backupNo): string
    {
        $source = config("database.connections.{$connection}.database");

        if ($source === ':memory:' || ! is_file($source)) {
            throw new RuntimeException('SQLite database file not found for backup.');
        }

        $relativePath = "{$this->directory}/{$backupNo}.sqlite";
        Storage::disk($this->disk)->put($relativePath, file_get_contents($source));

        return $relativePath;
    }

    protected function dumpMysql(string $connection, string $backupNo): string
    {
        $config = config("database.connections.{$connection}");
        $relativePath = "{$this->directory}/{$backupNo}.sql";
        $absolutePath = Storage::disk($this->disk)->path($relativePath);

        Storage::disk($this->disk)->put($relativePath, '');

        $binary = config('database.mysqldump_path', 'mysqldump');

        $process = new Process([
            $binary,
            '--host='.$config['host'],
            '--port='.$config['port'],
            '--user='.$config['username'],
            '--single-transaction',
            '--routines',
            '--triggers',
            '--no-tablespaces',
            $config['database'],
        ], timeout: null, env: ['MYSQL_PWD' => (string) $config['password']]);

        $output = fopen($absolutePath, 'w');

        try {
            $process->run(function ($type, $buffer) use ($output) {
                if ($type === Process::OUT) {
                    fwrite($output, $buffer);
                }
            });
        } finally {
            fclose($output);
        }

        if (! $process->isSuccessful()) {
            throw new RuntimeException('mysqldump failed: '.$process->getErrorOutput());
        }

        return $relativePath;
    }

    protected function restoreSqlite(string $connection, string $absoluteBackupPath): void
    {
        $target = config("database.connections.{$connection}.database");

        if ($target === ':memory:') {
            throw new RuntimeException('Cannot restore into an in-memory SQLite database.');
        }

        DB::disconnect($connection);
        copy($absoluteBackupPath, $target);
    }

    protected function restoreMysql(string $connection, string $absoluteBackupPath): void
    {
        $config = config("database.connections.{$connection}");
        $binary = config('database.mysql_path', 'mysql');

        $process = new Process([
            $binary,
            '--host='.$config['host'],
            '--port='.$config['port'],
            '--user='.$config['username'],
            $config['database'],
        ], timeout: null, env: ['MYSQL_PWD' => (string) $config['password']]);

        $process->setInput(fopen($absoluteBackupPath, 'r'));
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('mysql restore failed: '.$process->getErrorOutput());
        }
    }

    protected function generateBackupNo(): string
    {
        return 'BK-'.now()->format('Ymd-His').'-'.Str::upper(Str::random(4));
    }
}
