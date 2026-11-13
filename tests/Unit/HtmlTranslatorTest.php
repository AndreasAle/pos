<?php

namespace Tests\Unit;

use App\Support\HtmlTranslator;
use PHPUnit\Framework\TestCase;

class HtmlTranslatorTest extends TestCase
{
    private function tr(): HtmlTranslator
    {
        return new HtmlTranslator([
            'Simpan' => 'Save',
            'Batal' => 'Cancel',
            'Dashboard' => 'Dashboard',
            '{0} transaksi' => '{0} {0|transaction|transactions}',
            '{0} outlet' => '{0} {0|outlet|outlets}',
            'Kasir: {0}' => 'Cashier: {0}',
            'Cari produk...' => 'Search products...',
            'Kas sesuai' => 'Cash matches',
            'Gagal menyimpan.' => 'Failed to save.',
            'Cetak ulang' => 'Reprint',
            '{0} — Yoka POS' => '{0} — Yoka POS',
            'Detail Order' => 'Order Details',
        ]);
    }

    public function test_translates_text_nodes_and_keeps_surrounding_whitespace(): void
    {
        $this->assertSame("<button>\n  Save\n</button>", $this->tr()->translate("<button>\n  Simpan\n</button>"));
    }

    public function test_unknown_text_is_left_untouched(): void
    {
        $html = '<p>Teks yang belum ada di kamus</p>';
        $this->assertSame($html, $this->tr()->translate($html));
    }

    public function test_placeholders_and_plural_forms(): void
    {
        $this->assertSame('<span>1 transaction</span>', $this->tr()->translate('<span>1 transaksi</span>'));
        $this->assertSame('<span>9 transactions</span>', $this->tr()->translate('<span>9 transaksi</span>'));
        $this->assertSame('<span>Cashier: Budi</span>', $this->tr()->translate('<span>Kasir: Budi</span>'));
    }

    public function test_count_patterns_do_not_swallow_ordinary_text(): void
    {
        // "{0} outlet" hanya untuk hitungan, bukan teks seperti "Semua outlet".
        $this->assertSame('<span>Semua outlet</span>', $this->tr()->translate('<span>Semua outlet</span>'));
        $this->assertSame('<span>3 outlets</span>', $this->tr()->translate('<span>3 outlet</span>'));
    }

    public function test_translates_text_attributes_but_not_other_attributes(): void
    {
        $out = $this->tr()->translate('<input placeholder="Cari produk..." name="Simpan" value="Simpan">');
        $this->assertStringContainsString('placeholder="Search products..."', $out);
        $this->assertStringContainsString('name="Simpan"', $out);
        $this->assertStringContainsString('value="Simpan"', $out);
    }

    public function test_translates_alpine_expression_literals_even_with_greater_than_in_attribute(): void
    {
        $out = $this->tr()->translate('<p x-show="n > 1" x-text="diff === 0 ? \'Kas sesuai\' : \'Batal\'">x</p>');
        $this->assertStringContainsString("'Cash matches'", $out);
        $this->assertStringContainsString("'Cancel'", $out);
    }

    public function test_translates_script_string_literals_and_keeps_edge_spaces(): void
    {
        $out = $this->tr()->translate("<script>alert('Gagal menyimpan.'); const a = ' Simpan ';</script>");
        $this->assertStringContainsString("alert('Failed to save.')", $out);
        $this->assertStringContainsString("' Save '", $out);
    }

    public function test_translation_containing_a_quote_is_escaped_in_scripts(): void
    {
        $t = new HtmlTranslator(['Gagal' => "Couldn't save"]);
        $this->assertStringContainsString("'Couldn\'t save'", $t->translate("<script>x('Gagal')</script>"));
    }

    public function test_title_is_translated_per_part_and_identity_patterns_do_not_shadow_it(): void
    {
        $out = $this->tr()->translate('<title>Detail Order — Yoka POS</title>');
        $this->assertSame('<title>Order Details — Yoka POS</title>', $out);
    }

    public function test_textarea_and_style_content_are_never_touched(): void
    {
        $html = '<textarea>Simpan</textarea><style>.a:before{content:"Simpan"}</style>';
        $this->assertSame($html, $this->tr()->translate($html));
    }

    public function test_html_special_characters_in_translations_are_encoded(): void
    {
        $t = new HtmlTranslator(['Tombol' => 'Save & <close>']);
        $this->assertSame('<b>Save &amp; &lt;close&gt;</b>', $t->translate('<b>Tombol</b>'));
    }

    public function test_dynamic_part_of_a_pattern_is_translated_when_it_is_in_the_dictionary(): void
    {
        $t = new HtmlTranslator([
            'Ilustrasi · {0}' => 'Illustration · {0}',
            'Pelanggan & Promosi' => 'Customers & Promotions',
        ]);

        $this->assertSame('<p>Illustration · Customers &amp; Promotions</p>', $t->translate('<p>Ilustrasi · Pelanggan &amp; Promosi</p>'));
        // data yang tidak ada di kamus dibiarkan
        $this->assertSame('<p>Illustration · Kopi Susu</p>', $t->translate('<p>Ilustrasi · Kopi Susu</p>'));
    }
}
