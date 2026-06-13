<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ResourceSchemaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every Filament resource's model must map to a table that actually exists
     * and is queryable. Guards against table-name mismatches like the
     * BarcodeRegistry -> barcode_registries pluralisation bug.
     */
    public function test_every_resource_model_table_exists_and_is_queryable(): void
    {
        $files = glob(app_path('Filament/Resources/*.php'));
        $checked = 0;

        foreach ($files as $file) {
            $src = file_get_contents($file);
            if (! preg_match('/\$model = ([A-Za-z0-9_\\\\]+)::class/', $src, $m)) {
                continue;
            }

            $modelClass = 'App\\Models\\'.ltrim($m[1], '\\');
            $this->assertTrue(class_exists($modelClass), "Model {$modelClass} does not exist");

            $table = (new $modelClass)->getTable();
            $this->assertTrue(Schema::hasTable($table), "Table '{$table}' for {$modelClass} does not exist");

            // A real query must not throw (catches missing tables/columns).
            $modelClass::query()->count();
            $checked++;
        }

        $this->assertGreaterThan(20, $checked, 'Expected to verify the full set of resources');
    }
}
