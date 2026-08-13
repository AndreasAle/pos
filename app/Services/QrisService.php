<?php

namespace App\Services;

use App\Exceptions\PosTransactionException;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\SvgWriter;

/**
 * Turns a merchant's static QRIS into a dynamic one with the amount locked in.
 *
 * QRIS payloads are EMVCo TLV: a flat run of `TTLLVALUE` fields. Making one
 * dynamic means three edits — set tag 01 to "12" (single use), insert tag 54
 * with the amount before tag 58 (country code), and recompute the CRC in
 * tag 63. Everything else, including the merchant identifiers, stays put.
 *
 * There is no gateway and no webhook here: the customer's banking app reads
 * the amount off the QR and pays the merchant account directly. The cashier
 * confirms receipt in the POS.
 */
class QrisService
{
    /** Tags that carry an amount or a service fee; dropped before re-inserting. */
    private const AMOUNT_TAGS = ['54', '55', '56', '57'];

    /**
     * @param  int  $amount  Rupiah, whole numbers only.
     * @param  array{type?: string, value?: float}  $fee  ['type' => 'fixed'|'percent', 'value' => n]
     *
     * @throws PosTransactionException
     */
    public function toDynamic(string $staticPayload, int $amount, array $fee = []): string
    {
        $staticPayload = trim($staticPayload);

        if ($staticPayload === '') {
            throw new PosTransactionException(
                'QRIS statis belum diatur. Isi di Pengaturan > QRIS terlebih dahulu.'
            );
        }

        if ($amount <= 0) {
            throw new PosTransactionException('Nominal QRIS harus lebih dari 0.');
        }

        // Drop the trailing CRC (tag 63) so the body can be rebuilt.
        $body = str_ends_with(substr($staticPayload, -8, 4), '6304')
            ? substr($staticPayload, 0, -8)
            : $staticPayload;

        $fields = array_values(array_filter(
            $this->parse($body),
            fn (array $f) => !in_array($f['tag'], self::AMOUNT_TAGS, true)
        ));

        if ($fields === []) {
            throw new PosTransactionException('QRIS statis tidak dapat dibaca. Periksa kembali kodenya.');
        }

        // 11 = reusable (static), 12 = single use (dynamic).
        foreach ($fields as $i => $field) {
            if ($field['tag'] === '01') {
                $fields[$i]['value'] = '12';
            }
        }

        $extra = [['tag' => '54', 'value' => (string) $amount]];

        if (($fee['type'] ?? null) === 'fixed' && ($fee['value'] ?? 0) > 0) {
            $extra[] = ['tag' => '55', 'value' => '02'];
            $extra[] = ['tag' => '56', 'value' => (string) (int) $fee['value']];
        } elseif (($fee['type'] ?? null) === 'percent' && ($fee['value'] ?? 0) > 0) {
            $extra[] = ['tag' => '55', 'value' => '03'];
            $extra[] = ['tag' => '57', 'value' => rtrim(rtrim(number_format((float) $fee['value'], 2, '.', ''), '0'), '.')];
        }

        $countryIndex = null;
        foreach ($fields as $i => $field) {
            if ($field['tag'] === '58') {
                $countryIndex = $i;
                break;
            }
        }

        if ($countryIndex === null) {
            throw new PosTransactionException('QRIS statis tidak memuat kode negara (tag 58).');
        }

        array_splice($fields, $countryIndex, 0, $extra);

        $payload = '';
        foreach ($fields as $field) {
            $payload .= $this->tlv($field['tag'], $field['value']);
        }

        $payload .= '6304';

        return $payload . $this->crc16($payload);
    }

    /**
     * Does this payload carry a valid CRC? Used to reject a mistyped static code
     * before it ever reaches a customer's phone.
     */
    public function isValid(string $payload): bool
    {
        $payload = trim($payload);

        if (strlen($payload) < 12 || substr($payload, -8, 4) !== '6304') {
            return false;
        }

        $body = substr($payload, 0, -4);

        return strtoupper(substr($payload, -4)) === $this->crc16($body);
    }

    /**
     * Merchant name (tag 59) — handy for showing the cashier who gets paid.
     */
    public function merchantName(string $payload): ?string
    {
        foreach ($this->parse($payload) as $field) {
            if ($field['tag'] === '59') {
                return $field['value'];
            }
        }

        return null;
    }

    /**
     * Inline SVG for the payload. SVG needs no GD or Imagick, which shared
     * hosting does not always provide.
     */
    public function svg(string $payload, int $size = 320): string
    {
        return (new Builder(
            writer: new SvgWriter(),
            writerOptions: [SvgWriter::WRITER_OPTION_EXCLUDE_XML_DECLARATION => true],
            data: $payload,
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: $size,
            margin: 8,
        ))->build()->getString();
    }

    /** @return array<int, array{tag: string, value: string}> */
    private function parse(string $payload): array
    {
        $out = [];
        $i = 0;
        $len = strlen($payload);

        while ($i + 4 <= $len) {
            $tag = substr($payload, $i, 2);
            $lengthPart = substr($payload, $i + 2, 2);

            if (!ctype_digit($lengthPart)) {
                return [];
            }

            $valueLength = (int) $lengthPart;

            if ($i + 4 + $valueLength > $len) {
                return [];
            }

            $out[] = ['tag' => $tag, 'value' => substr($payload, $i + 4, $valueLength)];
            $i += 4 + $valueLength;
        }

        return $out;
    }

    private function tlv(string $tag, string $value): string
    {
        return $tag . str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT) . $value;
    }

    /** CRC-16/CCITT-FALSE, the checksum EMVCo specifies for tag 63. */
    private function crc16(string $data): string
    {
        $crc = 0xFFFF;

        for ($i = 0, $len = strlen($data); $i < $len; $i++) {
            $crc ^= ord($data[$i]) << 8;

            for ($bit = 0; $bit < 8; $bit++) {
                $crc = ($crc & 0x8000)
                    ? (($crc << 1) ^ 0x1021) & 0xFFFF
                    : ($crc << 1) & 0xFFFF;
            }
        }

        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }
}
