<?php

namespace Tests\Feature;

use Tests\TestCase;

class PwaTest extends TestCase
{
    public function test_html_responses_include_pwa_tags(): void
    {
        $response = $this->get('/admin/login');

        $response->assertOk();
        $response->assertSee('rel="manifest"', false);
        $response->assertSee('/manifest.webmanifest', false);
        $response->assertSee('name="theme-color"', false);
        $response->assertSee('apple-touch-icon', false);
        $response->assertSee('/sw-register.js', false);
    }

    public function test_manifest_is_valid_and_installable(): void
    {
        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true);

        $this->assertIsArray($manifest, 'manifest.webmanifest is not valid JSON');
        $this->assertNotEmpty($manifest['name']);
        $this->assertSame('standalone', $manifest['display']);

        $sizes = array_column($manifest['icons'], 'sizes');
        $this->assertContains('192x192', $sizes, 'A 192x192 icon is required for installability');
        $this->assertContains('512x512', $sizes, 'A 512x512 icon is required for installability');

        // Every referenced icon must actually exist.
        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')), "Manifest icon missing: {$icon['src']}");
        }
    }

    public function test_pwa_assets_are_present(): void
    {
        foreach ([
            'service-worker.js',
            'sw-register.js',
            'offline.html',
            'manifest.webmanifest',
            'icons/icon-192.png',
            'icons/icon-512.png',
            'icons/apple-touch-icon.png',
            'icons/mask-icon.svg',
        ] as $file) {
            $this->assertFileExists(public_path($file));
            $this->assertGreaterThan(0, filesize(public_path($file)), "{$file} is empty");
        }
    }
}
