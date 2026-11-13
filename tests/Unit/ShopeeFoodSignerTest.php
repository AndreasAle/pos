<?php

namespace Tests\Unit;

use App\Services\Channels\ShopeeFoodSigner;
use PHPUnit\Framework\TestCase;

/**
 * Vektor uji dari dokumentasi resmi ShopeeFood
 * (https://developer.shopeefood.co.id/vendor/api-docs, "How to generate the request signature").
 *
 * Catatan: tabel parameter di dokumentasi menulis Vendor ID 1000001, tetapi kedua tanda tangan
 * contohnya hanya cocok bila app_id = 10001 dan path memuat 10001 (sama seperti contoh header
 * "X-SF-AppID: 10001"). Angka 1000001 di tabel itu salah ketik; algoritmenya sendiri benar.
 */
class ShopeeFoodSignerTest extends TestCase
{
    private const SECRET = 'fTNmmnRhWWs23581';
    private const TOKEN = 'bade2ab86fe4fc530570eb36ea5eafe1';
    private const APP_ID = '10001';
    private const PATH = '/api/adaptor/10001/v1/store/menu/notification';
    private const TS = 1652860572;

    public function test_content_is_built_in_ascii_order(): void
    {
        $this->assertSame(
            'access_token=bade2ab86fe4fc530570eb36ea5eafe1&app_id=10001&path=/api/adaptor/10001/v1/store/menu/notification&payload={"store_id":"101"}&timestamp=1652860572',
            ShopeeFoodSigner::content(self::TOKEN, self::APP_ID, self::PATH, '{"store_id":"101"}', self::TS)
        );
    }

    public function test_signature_matches_documented_example_a_json_body(): void
    {
        $this->assertSame(
            '952d0e416a7d634f07e36a125a366b0128946be60cf922cbf9b43b0e22106948',
            ShopeeFoodSigner::sign(self::SECRET, self::TOKEN, self::APP_ID, self::PATH, '{"store_id":"101"}', self::TS)
        );
    }

    public function test_signature_matches_documented_example_b_query_string(): void
    {
        // Query string dipakai apa adanya, tanpa diurutkan ulang.
        $this->assertSame(
            'cb09f21a0bc3d04afc3947e5a82fecb4b729e0927646e97a3631f7b749e7dc6d',
            ShopeeFoodSigner::sign(self::SECRET, self::TOKEN, self::APP_ID, self::PATH, 'param_b=123&param_c=&param_a=456', self::TS)
        );
    }
}
