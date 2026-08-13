<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Views must not link to a route that an optional module may have removed.
 *
 * The suite runs with every module enabled, so a stray `route('saas.current')`
 * on a shared page resolves happily here and only explodes in production, where
 * the route was never registered — a RouteNotFoundException and a 500 on the
 * page everyone lands on.
 *
 * This walks the Blade files instead, and insists that any reference to a
 * gateable route either lives inside that module's own views (unreachable when
 * the module is off) or sits behind a feature check.
 */
class FeatureFlagViewGuardTest extends TestCase
{
    /**
     * Route prefix => directory that module owns.
     *
     * @var array<string, string>
     */
    private const GATED = [
        "route('saas."               => 'saas',
        "route('balance."            => 'balance',
        "route('admin.withdrawals."  => 'admin/withdrawals',
        "route('audit."              => 'audit',
        "route('subscription.expired" => 'saas',
    ];

    public function test_no_shared_view_links_to_a_module_route_without_a_guard(): void
    {
        $viewPath = resource_path('views');
        $offenders = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($viewPath, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if (!str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($viewPath) + 1));
            $contents = file_get_contents($file->getPathname());

            foreach (self::GATED as $needle => $ownedBy) {
                if (!str_contains($contents, $needle)) {
                    continue;
                }

                // The module's own views are only reachable through its routes.
                if (str_starts_with($relative, $ownedBy . '/')) {
                    continue;
                }

                if (str_contains($contents, "config('pos.features.")) {
                    continue;
                }

                $offenders[] = "{$relative} memakai {$needle}...) tanpa penjaga fitur";
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "View berikut akan menyebabkan 500 saat modulnya dimatikan:\n  - "
                . implode("\n  - ", $offenders)
        );
    }
}
