<?php

namespace App\Http\Middleware;

use App\Support\HtmlTranslator;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bahasa antarmuka: Indonesia (bawaan, bahasa sumber teks) atau Inggris.
 *
 * Pilihan disimpan di cookie `yoka_locale`. Saat bahasa Inggris dipilih, HTML hasil
 * render diterjemahkan lewat kamus lang/en.json (lihat HtmlTranslator).
 */
class Localize
{
    public const COOKIE = 'yoka_locale';
    public const SUPPORTED = ['id', 'en'];
    public const DEFAULT = 'id';

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->cookie(self::COOKIE);
        if (! in_array($locale, self::SUPPORTED, true)) {
            $locale = self::DEFAULT;
        }

        app()->setLocale($locale);
        Carbon::setLocale($locale);
        view()->share('appLocale', $locale);

        $response = $next($request);

        if ($locale === 'en' && $this->shouldTranslate($request, $response)) {
            $dictionary = $this->dictionary();
            if ($dictionary) {
                $response->setContent((new HtmlTranslator($dictionary))->translate((string) $response->getContent()));
            }
        }

        return $response;
    }

    private function shouldTranslate(Request $request, Response $response): bool
    {
        // Hanya respons halaman biasa; stream, file, dan JSON dibiarkan.
        if (! $response instanceof \Illuminate\Http\Response || $response->isRedirection()) {
            return false;
        }
        // Back office tim Yoka tetap Indonesia.
        if ($request->is('platform') || $request->is('platform/*')) {
            return false;
        }
        $type = (string) $response->headers->get('Content-Type');

        return str_contains($type, 'text/html');
    }

    /** @return array<string,string> */
    private function dictionary(): array
    {
        $path = lang_path('en.json');
        if (! is_file($path)) {
            return [];
        }

        // Cache per versi file supaya JSON tidak di-parse ulang tiap request.
        return Cache::rememberForever('i18n.en.' . filemtime($path), function () use ($path) {
            $data = json_decode((string) file_get_contents($path), true);

            return is_array($data) ? $data : [];
        });
    }
}
