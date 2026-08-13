<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Standalone pages must ship whatever they depend on.
 *
 * Most views extend a layout that pulls Alpine from a CDN. A page that renders
 * its own <html> misses that, and Alpine directives silently do nothing — with
 * x-cloak in play the result is a blank screen rather than an error, which is
 * exactly what a cashier saw on the PIN pad.
 */
class StandaloneViewDependencyTest extends TestCase
{
    public function test_standalone_pages_using_alpine_actually_load_it(): void
    {
        $offenders = [];

        foreach ($this->bladeFiles() as $relative => $contents) {
            // Only pages that build their own document; the rest inherit a layout.
            if (!str_contains($contents, '<!DOCTYPE')) {
                continue;
            }

            $usesAlpine = str_contains($contents, 'x-data')
                || str_contains($contents, 'x-show')
                || str_contains($contents, 'x-text');

            if (!$usesAlpine) {
                continue;
            }

            if (!str_contains($contents, 'alpinejs')) {
                $offenders[] = $relative;
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Halaman berikut memakai Alpine tetapi tidak memuatnya — isinya tidak akan tampil:\n  - "
                . implode("\n  - ", $offenders)
        );
    }

    public function test_no_view_hides_content_with_x_cloak_without_alpine(): void
    {
        $offenders = [];

        foreach ($this->bladeFiles() as $relative => $contents) {
            if (!str_contains($contents, 'x-cloak')) {
                continue;
            }

            if (!str_contains($contents, '<!DOCTYPE')) {
                continue;
            }

            if (!str_contains($contents, 'alpinejs')) {
                $offenders[] = $relative;
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "x-cloak tanpa Alpine berarti layar kosong permanen:\n  - " . implode("\n  - ", $offenders)
        );
    }

    /** @return array<string, string> relative path => contents */
    private function bladeFiles(): array
    {
        $base  = resource_path('views');
        $out   = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if (!str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1));
            $out[$relative] = file_get_contents($file->getPathname());
        }

        return $out;
    }
}
