<?php

namespace Tests\Feature\Pos;

use App\Exceptions\PosTransactionException;
use App\Services\QrisService;
use Tests\TestCase;

/**
 * Static-to-dynamic QRIS conversion.
 *
 * The expected payloads below were produced by the reference Python
 * implementation and match this port byte for byte. A customer's banking app
 * rejects the QR outright if the CRC is wrong, so these are exact-match tests
 * rather than shape checks.
 */
class QrisServiceTest extends TestCase
{
    /** Real merchant static QRIS (DANA business, NMID ID1026562375893). */
    private const STATIC_QRIS = '00020101021126570011ID.DANA.WWW011893600915303371673602090337167360303UMI51440014ID.CO.QRIS.WWW0215ID10265623758930303UMI5204737253033605802ID5906Conweb6014Kota Palembang61053016163045C12';

    private function qris(): QrisService
    {
        return app(QrisService::class);
    }

    public function test_it_matches_the_reference_implementation_for_a_round_amount(): void
    {
        $expected = '00020101021226570011ID.DANA.WWW011893600915303371673602090337167360303UMI51440014ID.CO.QRIS.WWW0215ID10265623758930303UMI5204737253033605405250005802ID5906Conweb6014Kota Palembang6105301616304B25F';

        $this->assertSame($expected, $this->qris()->toDynamic(self::STATIC_QRIS, 25000));
    }

    public function test_it_matches_the_reference_implementation_for_a_six_digit_amount(): void
    {
        $expected = '00020101021226570011ID.DANA.WWW011893600915303371673602090337167360303UMI51440014ID.CO.QRIS.WWW0215ID10265623758930303UMI52047372530336054061375005802ID5906Conweb6014Kota Palembang61053016163044402';

        $this->assertSame($expected, $this->qris()->toDynamic(self::STATIC_QRIS, 137500));
    }

    public function test_the_static_source_is_recognised_as_valid(): void
    {
        $this->assertTrue($this->qris()->isValid(self::STATIC_QRIS));
    }

    public function test_every_generated_payload_carries_a_valid_crc(): void
    {
        foreach ([1000, 25000, 99999, 137500, 1500000] as $amount) {
            $payload = $this->qris()->toDynamic(self::STATIC_QRIS, $amount);

            $this->assertTrue(
                $this->qris()->isValid($payload),
                "CRC invalid for amount {$amount} — a phone would refuse this QR."
            );
        }
    }

    public function test_the_point_of_initiation_flips_to_single_use(): void
    {
        $payload = $this->qris()->toDynamic(self::STATIC_QRIS, 25000);

        // Tag 01 length 02: "11" static becomes "12" dynamic.
        $this->assertStringContainsString('010212', $payload);
        $this->assertStringNotContainsString('010211', $payload);
    }

    public function test_the_amount_is_written_into_tag_54(): void
    {
        $payload = $this->qris()->toDynamic(self::STATIC_QRIS, 25000);

        // 54 + length 05 + "25000"
        $this->assertStringContainsString('540525000', $payload);
    }

    public function test_the_merchant_identity_survives_the_conversion(): void
    {
        $payload = $this->qris()->toDynamic(self::STATIC_QRIS, 25000);

        $this->assertStringContainsString('ID1026562375893', $payload, 'NMID must not change.');
        $this->assertStringContainsString('Conweb', $payload);
        $this->assertSame('Conweb', $this->qris()->merchantName($payload));
    }

    public function test_converting_an_already_dynamic_payload_replaces_the_old_amount(): void
    {
        $first  = $this->qris()->toDynamic(self::STATIC_QRIS, 25000);
        $second = $this->qris()->toDynamic($first, 40000);

        $this->assertStringContainsString('540540000', $second);
        $this->assertStringNotContainsString('540525000', $second);
        $this->assertTrue($this->qris()->isValid($second));
    }

    public function test_a_fixed_service_fee_is_appended(): void
    {
        $payload = $this->qris()->toDynamic(self::STATIC_QRIS, 25000, ['type' => 'fixed', 'value' => 1000]);

        $this->assertStringContainsString('55020256041000', $payload);
        $this->assertTrue($this->qris()->isValid($payload));
    }

    public function test_a_percentage_service_fee_is_appended(): void
    {
        $payload = $this->qris()->toDynamic(self::STATIC_QRIS, 25000, ['type' => 'percent', 'value' => 0.7]);

        $this->assertStringContainsString('55020357030.7', $payload);
        $this->assertTrue($this->qris()->isValid($payload));
    }

    public function test_an_empty_static_code_is_rejected(): void
    {
        $this->expectException(PosTransactionException::class);
        $this->expectExceptionMessageMatches('/QRIS statis belum diatur/');

        $this->qris()->toDynamic('   ', 25000);
    }

    public function test_a_non_positive_amount_is_rejected(): void
    {
        $this->expectException(PosTransactionException::class);
        $this->expectExceptionMessageMatches('/harus lebih dari 0/');

        $this->qris()->toDynamic(self::STATIC_QRIS, 0);
    }

    public function test_a_malformed_static_code_is_rejected_rather_than_producing_a_bad_qr(): void
    {
        $this->expectException(PosTransactionException::class);

        $this->qris()->toDynamic('ini-bukan-qris-sama-sekali', 25000);
    }

    public function test_a_tampered_payload_fails_validation(): void
    {
        $payload = $this->qris()->toDynamic(self::STATIC_QRIS, 25000);
        $tampered = str_replace('540525000', '540510000', $payload);

        $this->assertFalse(
            $this->qris()->isValid($tampered),
            'Changing the amount without recomputing the CRC must invalidate the code.'
        );
    }

    public function test_it_renders_a_scannable_svg(): void
    {
        $svg = $this->qris()->svg($this->qris()->toDynamic(self::STATIC_QRIS, 25000));

        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringNotContainsString('<?xml', $svg, 'Inline SVG must not carry an XML declaration.');
    }
}
