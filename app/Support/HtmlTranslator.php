<?php

namespace App\Support;

/**
 * Menerjemahkan teks Indonesia di dalam HTML hasil render ke bahasa lain
 * memakai kamus lang/{locale}.json (kunci = teks Indonesia, nilai = terjemahan).
 *
 * Kenapa di tingkat HTML, bukan di tiap view: satu titik ini otomatis menjangkau
 * teks di view, komponen, pesan flash dari controller, dan string di skrip Alpine,
 * tanpa mengubah ratusan file. Teks yang belum ada di kamus dibiarkan apa adanya
 * (tetap Indonesia), jadi tidak pernah merusak halaman.
 *
 * Kunci boleh memakai penampung {0}, {1}, ... untuk bagian dinamis, misalnya
 * "{0} transaksi" => "{0} transactions".
 */
class HtmlTranslator
{
    /** @var array<string,string> */
    private array $exact = [];

    /** @var list<array{regex:string,to:string,hint:string}> */
    private array $patterns = [];

    /** Atribut yang isinya teks biasa. */
    private const TEXT_ATTRS = ['placeholder', 'title', 'aria-label', 'alt', 'label', 'data-tip', 'data-title', 'data-empty'];

    public function __construct(array $dictionary)
    {
        foreach ($dictionary as $from => $to) {
            if (! is_string($from) || ! is_string($to) || $to === '') {
                continue;
            }
            $key = self::normalize($from);
            if (preg_match('/\{\d+\}/', $key)) {
                // Pola yang artinya sama persis tidak berguna dan malah menghalangi
                // terjemahan per bagian (mis. judul "{0} — Yoka POS").
                if ($key === self::normalize($to)) {
                    continue;
                }
                $parts = preg_split('/(\{\d+\})/', $key, -1, PREG_SPLIT_DELIM_CAPTURE);
                $regex = '';
                $hint = '';
                // Kunci berbentuk "{0} kata" hanya untuk hitungan (angka), supaya "Semua outlet"
                // tidak ikut tertangkap pola "{0} outlet".
                $countOnly = (bool) preg_match('/^\{0\} \p{L}+$/u', $key);
                foreach ($parts as $part) {
                    if (preg_match('/^\{\d+\}$/', $part)) {
                        $regex .= $countOnly ? '((?:Rp\s*)?[\d.,]+%?)' : '(.+?)';
                    } else {
                        $regex .= preg_quote($part, '/');
                        if (mb_strlen($part) > mb_strlen($hint)) {
                            $hint = $part;
                        }
                    }
                }
                $this->patterns[] = ['regex' => '/^' . $regex . '$/su', 'to' => $to, 'hint' => $hint];
            } else {
                $this->exact[$key] = $to;
            }
        }

        // Pola paling spesifik (kunci terpanjang) dicoba lebih dulu.
        usort($this->patterns, fn ($a, $b) => strlen($b['regex']) <=> strlen($a['regex']));
    }

    public static function normalize(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\u{00A0}", ' ', $text);

        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    /** Terjemahkan satu potong teks polos (sudah bukan HTML). Null jika tidak dikenal. */
    public function lookup(string $text): ?string
    {
        $key = self::normalize($text);
        if ($key === '' || ! preg_match('/\pL{2}/u', $key)) {
            return null;
        }
        if (isset($this->exact[$key])) {
            return $this->exact[$key];
        }
        foreach ($this->patterns as $p) {
            if ($p['hint'] !== '' && ! str_contains($key, $p['hint'])) {
                continue;
            }
            if (preg_match($p['regex'], $key, $m)) {
                $captures = array_slice($m, 1);
                $out = $p['to'];

                // Bentuk tunggal/jamak: {0|item|items} memilih kata menurut angka di penampung 0.
                $out = preg_replace_callback('/\{(\d+)\|([^|{}]*)\|([^{}]*)\}/u', function ($pm) use ($captures) {
                    $n = $captures[(int) $pm[1]] ?? '';
                    $digits = preg_replace('/[^\d]/', '', (string) $n);

                    return ($digits !== '' && (int) $digits === 1) ? $pm[2] : $pm[3];
                }, $out);

                foreach ($captures as $i => $val) {
                    $out = str_replace('{' . $i . '}', $val, $out);
                }

                return $out;
            }
        }

        return null;
    }

    public function translate(string $html): string
    {
        $tokens = preg_split(
            // Tag dikenali dengan memperhitungkan tanda ">" di dalam nilai atribut (mis. x-show="n > 1").
            '/(<script\b(?:"[^"]*"|\'[^\']*\'|[^\'">])*>.*?<\/script>|<style\b(?:"[^"]*"|\'[^\']*\'|[^\'">])*>.*?<\/style>|<textarea\b(?:"[^"]*"|\'[^\']*\'|[^\'">])*>.*?<\/textarea>|<!--.*?-->|<[a-zA-Z\/!](?:"[^"]*"|\'[^\']*\'|[^\'">])*>)/is',
            $html,
            -1,
            PREG_SPLIT_DELIM_CAPTURE
        );

        $inTitle = false;
        $out = '';
        foreach ($tokens as $i => $tok) {
            if ($tok === '') {
                continue;
            }
            if ($i % 2 === 0) {
                // teks biasa
                $out .= $inTitle ? $this->translateTitle($tok) : $this->translateText($tok);

                continue;
            }
            // tag, skrip, gaya, textarea, atau komentar
            if (stripos($tok, '<script') === 0) {
                $out .= $this->translateScript($tok);
            } elseif (stripos($tok, '<textarea') === 0 || str_starts_with($tok, '<!--') || stripos($tok, '<style') === 0) {
                $out .= $tok;
            } else {
                if (stripos($tok, '<title') === 0) {
                    $inTitle = true;
                } elseif (stripos($tok, '</title') === 0) {
                    $inTitle = false;
                }
                $out .= $this->translateTag($tok);
            }
        }

        return $out;
    }

    private function translateText(string $raw): string
    {
        if (! preg_match('/^(\s*)(.*?)(\s*)$/su', $raw, $m) || $m[2] === '') {
            return $raw;
        }
        $to = $this->lookup($m[2]);

        return $to === null ? $raw : $m[1] . self::encode($to) . $m[3];
    }

    /** Judul tab: "Detail Order — Yoka POS" diterjemah per bagian. */
    private function translateTitle(string $raw): string
    {
        $whole = $this->lookup($raw);
        if ($whole !== null) {
            return self::encode($whole);
        }
        $parts = preg_split('/(\s+[—–-]\s+)/u', $raw, -1, PREG_SPLIT_DELIM_CAPTURE);
        foreach ($parts as $i => $part) {
            if ($i % 2 === 0) {
                $to = $this->lookup($part);
                if ($to !== null) {
                    $parts[$i] = self::encode($to);
                }
            }
        }

        return implode('', $parts);
    }

    private function translateTag(string $tag): string
    {
        if (! str_contains($tag, '=')) {
            return $tag;
        }

        return preg_replace_callback(
            '/([\w:@.\-]+)=("([^"]*)"|\'([^\']*)\')/u',
            function ($m) {
                $name = strtolower($m[1]);
                $quote = $m[2][0];
                $value = $m[3] !== '' ? $m[3] : ($m[4] ?? '');

                if (in_array($name, self::TEXT_ATTRS, true)) {
                    $to = $this->lookup($value);

                    return $to === null ? $m[0] : $m[1] . '=' . $quote . self::encode($to, true) . $quote;
                }

                // Ekspresi Alpine / handler inline: ganti hanya string literal yang dikenal.
                if (preg_match('/^(x-|:|@|on)/', $name) && preg_match('/[\'"]|&quot;|&#039;/', $value)) {
                    $new = $this->translateLiterals($value, true);

                    return $new === $value ? $m[0] : $m[1] . '=' . $quote . $new . $quote;
                }

                return $m[0];
            },
            $tag
        );
    }

    private function translateScript(string $block): string
    {
        return preg_replace_callback(
            '/(<script\b[^>]*>)(.*?)(<\/script>)/is',
            fn ($m) => $m[1] . $this->translateLiterals($m[2], false) . $m[3],
            $block
        );
    }

    /** Ganti string literal ('...', "...", `...`) yang ada di kamus. */
    private function translateLiterals(string $code, bool $inAttribute): string
    {
        $pattern = $inAttribute
            ? '/(\'|&quot;|&#039;)((?:(?!\1).){2,160}?)\1/su'
            : '/([\'"`])((?:(?!\1)[^\\\\\n]){2,160}?)\1/su';

        return preg_replace_callback($pattern, function ($m) use ($inAttribute) {
            // Spasi di awal/akhir (potongan kalimat yang digabung di JS) tetap dipertahankan.
            preg_match('/^(\s*)(.*?)(\s*)$/su', $m[2], $w);
            $to = $this->lookup($w[2] ?? $m[2]);
            if ($to === null) {
                return $m[0];
            }
            $to = ($w[1] ?? '') . $to . ($w[3] ?? '');
            // Pertahankan pembatas semula. Kutip yang dipakai literal JS di atribut
            // bisa berupa entitas (&#039; / &quot;), jadi tentukan karakter aslinya.
            $q = $m[1];
            $jsQuote = in_array($q, ["'", '&#039;'], true) ? "'" : (in_array($q, ['"', '&quot;'], true) ? '"' : '`');
            $js = addcslashes($to, $jsQuote . '\\');
            $body = $inAttribute ? htmlspecialchars($js, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false) : $js;

            return $q . $body . $q;
        }, $code);
    }

    private static function encode(string $text, bool $attribute = false): string
    {
        $flags = $attribute ? ENT_COMPAT : ENT_NOQUOTES;

        return htmlspecialchars($text, $flags | ENT_SUBSTITUTE, 'UTF-8', false);
    }
}
