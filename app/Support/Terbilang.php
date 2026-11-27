<?php

namespace App\Support;

/** Mengubah angka menjadi kata Bahasa Indonesia untuk dokumen resmi (invoice, kuitansi). */
class Terbilang
{
    private const WORDS = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];

    public static function rupiah(float|int $amount): string
    {
        $n = (int) round($amount);

        return ucfirst($n === 0 ? 'nol' : self::words($n)).' rupiah';
    }

    public static function words(int $n): string
    {
        if ($n < 12) {
            return self::WORDS[$n];
        }
        if ($n < 20) {
            return self::words($n - 10).' belas';
        }
        if ($n < 100) {
            return trim(self::words(intdiv($n, 10)).' puluh '.self::words($n % 10));
        }
        if ($n < 200) {
            return trim('seratus '.self::words($n - 100));
        }
        if ($n < 1000) {
            return trim(self::words(intdiv($n, 100)).' ratus '.self::words($n % 100));
        }
        if ($n < 2000) {
            return trim('seribu '.self::words($n - 1000));
        }
        foreach ([1_000_000_000_000 => 'triliun', 1_000_000_000 => 'miliar', 1_000_000 => 'juta', 1000 => 'ribu'] as $unit => $name) {
            if ($n >= $unit) {
                return trim(self::words(intdiv($n, $unit)).' '.$name.' '.self::words($n % $unit));
            }
        }

        return '';
    }
}
