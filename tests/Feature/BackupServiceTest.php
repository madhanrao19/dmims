<?php

namespace Tests\Feature;

use App\Jobs\RunDatabaseBackup;
use App\Models\Customer;
use App\Services\BackupService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class BackupServiceTest extends TestCase
{
    private string $dbFile;

    protected function setUp(): void
    {
        parent::setUp();

        // Use a real on-disk SQLite database so the backup/restore code paths
        // (file copy) actually execute, rather than the in-memory default.
        $this->dbFile = tempnam(sys_get_temp_dir(), 'dmims_backup_').'.sqlite';
        touch($this->dbFile);

        config(['database.connections.backup_sqlite' => [
            'driver' => 'sqlite',
            'database' => $this->dbFile,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]]);
        config(['database.default' => 'backup_sqlite']);

        DB::purge('backup_sqlite');
        Artisan::call('migrate', ['--force' => true]);

        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        @unlink($this->dbFile);
        parent::tearDown();
    }

    public function test_it_creates_a_database_backup_file(): void
    {
        Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);

        $backup = app(BackupService::class)->backupDatabase();

        $this->assertSame('success', $backup->status);
        $this->assertTrue($backup->verified);
        $this->assertNotNull($backup->checksum);
        $this->assertNotNull($backup->file_path);
        $this->assertGreaterThan(0, $backup->file_size);
        Storage::disk('local')->assertExists($backup->file_path);
    }

    public function test_backup_file_is_encrypted_at_rest(): void
    {
        Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);

        $backup = app(BackupService::class)->backupDatabase();
        $stored = Storage::disk('local')->get($backup->file_path);

        // A plaintext SQLite file starts with this literal header; the
        // encrypted-at-rest version must not.
        $this->assertStringNotContainsString('SQLite format 3', $stored);
    }

    public function test_restore_refuses_a_tampered_backup(): void
    {
        Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        $backup = app(BackupService::class)->backupDatabase();

        Storage::disk('local')->append($backup->file_path, 'tampered');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('checksum mismatch');

        app(BackupService::class)->restoreDatabase($backup->fresh());
    }

    public function test_it_restores_a_database_backup(): void
    {
        Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        $backup = app(BackupService::class)->backupDatabase();

        // Mutate the database after the backup was taken.
        Customer::query()->delete();
        $this->assertSame(0, Customer::count());

        app(BackupService::class)->restoreDatabase($backup);

        $this->assertSame(1, Customer::count());
        $this->assertSame('restored', $backup->fresh()->status);
    }

    public function test_create_pending_does_not_run_the_dump_until_the_job_runs(): void
    {
        Queue::fake();

        $backup = app(BackupService::class)->createPending();
        $this->assertSame('pending', $backup->status);
        $this->assertNull($backup->file_path);

        RunDatabaseBackup::dispatch($backup);
        Queue::assertPushed(RunDatabaseBackup::class);

        // Nothing ran yet — the fake queue only recorded the dispatch.
        $this->assertSame('pending', $backup->fresh()->status);

        app(RunDatabaseBackup::class, ['backup' => $backup])->handle(app(BackupService::class));

        $this->assertSame('success', $backup->fresh()->status);
    }

    public function test_verify_latest_backup_command_passes_for_a_healthy_backup(): void
    {
        Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        app(BackupService::class)->backupDatabase();

        Artisan::call('dmims:verify-latest-backup');

        $this->assertSame(0, Artisan::call('dmims:verify-latest-backup'));
    }

    public function test_verify_latest_backup_command_fails_for_a_tampered_backup(): void
    {
        Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        $backup = app(BackupService::class)->backupDatabase();
        Storage::disk('local')->append($backup->file_path, 'tampered');

        $this->assertSame(1, Artisan::call('dmims:verify-latest-backup'));
    }
}
