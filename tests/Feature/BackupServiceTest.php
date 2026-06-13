<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Services\BackupService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
        $this->assertNotNull($backup->file_path);
        $this->assertGreaterThan(0, $backup->file_size);
        Storage::disk('local')->assertExists($backup->file_path);
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
}
