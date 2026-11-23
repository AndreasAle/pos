<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * Serves the offline register's service worker from a route, not a static
 * file: deploy.sh copies only build/ and images/ into the web root, and the
 * worker needs this build's asset list baked in so a deploy replaces it.
 */
class PosServiceWorkerController extends Controller
{
    private const POS_ENTRIES = [
        'resources/css/app.css',
        'resources/js/app.js',
        'resources/js/alpine.js',
        'resources/js/pos-offline/index.js',
    ];

    public function __invoke(): Response
    {
        abort_unless(config('pos.features.pos_offline'), 404);

        $source   = file_get_contents(resource_path('js/pos-offline/sw.js'));
        $manifest = public_path('build/manifest.json');
        $assets   = [];

        if (is_file($manifest)) {
            $entries = json_decode((string) file_get_contents($manifest), true) ?: [];
            // Only what /pos loads, plus static imports. Dynamic chunks (the
            // 1 MB+ landing-page 3D viewer) are left out on purpose.
            $queue = self::POS_ENTRIES;
            $seen  = [];
            while ($key = array_shift($queue)) {
                if (isset($seen[$key]) || !isset($entries[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $entry      = $entries[$key];
                foreach (array_merge([$entry['file'] ?? null], $entry['css'] ?? []) as $file) {
                    if ($file) {
                        $assets[] = '/build/' . $file;
                    }
                }
                array_push($queue, ...($entry['imports'] ?? []));
            }
        }

        $assets  = array_values(array_unique($assets));
        $version = substr(hash('sha256', $source . implode('|', $assets)), 0, 12);

        $js = strtr($source, [
            "'__SW_VERSION__'" => json_encode($version),
            "'__PRECACHE__'"   => json_encode($assets, JSON_UNESCAPED_SLASHES),
        ]);

        return response($js, 200, [
            'Content-Type'           => 'application/javascript; charset=utf-8',
            // Always revalidate: a stale worker would keep serving an old register.
            'Cache-Control'          => 'no-cache, no-store, must-revalidate',
            'Service-Worker-Allowed' => '/',
        ]);
    }
}
