<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Halaman sedang dibuka dari aplikasi Android Yoka POS (Play Store), bukan browser.
 * Aplikasinya menambahkan "YokaPOSApp/<versi>" ke user agent WebView. Aplikasi klien
 * lama (POSKasirApp, pos.conweb.id) sengaja tidak dikenali di sini.
 */
class InApp
{
    public static function check(?Request $request = null): bool
    {
        $request ??= request();

        return str_contains((string) $request->userAgent(), 'YokaPOSApp/');
    }
}
