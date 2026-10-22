<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Full POS DIKI STORE catalogue from the Kumbang exhibition item list:
 * every colour and size becomes a variant, and the quantity brought to the
 * exhibition is written into the product description (products have no
 * stock count of their own yet).
 *
 * Safe to run again: products are matched by name, their variants are
 * rebuilt (past orders keep the variant name), and products no longer in the
 * list are only deactivated. Items without a known price are created
 * inactive at Rp 0 so the shop can fill the price in before selling.
 *
 * Needs DikiStoreSeeder to have run first.
 *
 *   php artisan db:seed --class=DikiStoreCatalogueSeeder --force
 */
class DikiStoreCatalogueSeeder extends Seeder
{
    private const BUSINESS_SLUG = 'diki-store';

    public function run(): void
    {
        $business = Business::where('slug', self::BUSINESS_SLUG)->first();

        if (! $business) {
            $this->command?->error('POS DIKI STORE belum ada — jalankan DikiStoreSeeder dulu.');

            return;
        }

        $outletId = $business->outlets()->value('id');
        $kept     = [];
        $sku      = 0;
        $sort     = 0;

        foreach ($this->rows() as $categoryName => $products) {
            $category = ProductCategory::updateOrCreate(
                ['business_id' => $business->id, 'name' => $categoryName],
                ['slug' => Str::slug($categoryName), 'sort_order' => ++$sort, 'is_active' => true],
            );

            foreach ($products as [$name, $price, $stock]) {
                $variants = $this->variants($stock);
                $total    = array_sum($variants) ?: (is_int($stock) ? $stock : 0);

                $product = Product::updateOrCreate(
                    ['business_id' => $business->id, 'name' => $name],
                    [
                        'outlet_id'           => $outletId,
                        'product_category_id' => $category->id,
                        'sku'                 => 'DK' . str_pad((string) ++$sku, 3, '0', STR_PAD_LEFT),
                        'description'         => $this->description($variants, $total, $price),
                        'price'               => $price,
                        'is_active'           => $price > 0,
                        'is_stock_tracked'    => false,
                        'sort_order'          => $sku,
                    ],
                );

                $product->variants()->delete();

                $i = 0;
                foreach (array_keys($variants) as $variantName) {
                    ProductVariant::create([
                        'product_id'       => $product->id,
                        'name'             => $variantName,
                        'price_adjustment' => 0,
                        'is_active'        => true,
                        'sort_order'       => ++$i,
                    ]);
                }

                $kept[] = $product->id;
            }
        }

        $hidden = Product::where('business_id', $business->id)
            ->whereNotIn('id', $kept)
            ->update(['is_active' => false]);

        $this->command?->info(count($kept) . " produk Diki Store diperbarui, {$hidden} produk lama dinonaktifkan.");
    }

    /**
     * Flattens [colour => [size => qty]] or [variant => qty] into
     * ['Colour - Size' => qty]. A plain number means no variants.
     */
    private function variants(int|array $stock): array
    {
        if (is_int($stock)) {
            return [];
        }

        $flat = [];
        foreach ($stock as $colour => $sizes) {
            if (is_array($sizes)) {
                foreach ($sizes as $size => $qty) {
                    $flat["{$colour} - {$size}"] = $qty;
                }
            } else {
                $flat[$colour] = $sizes;
            }
        }

        return $flat;
    }

    private function description(array $variants, int $total, int $price): string
    {
        $lines = ["Stok pameran Kumbang: {$total} bh"];

        foreach ($variants as $name => $qty) {
            $lines[] = "{$name}: {$qty}";
        }

        if ($price === 0) {
            $lines[] = 'Harga belum ada — isi harga lalu aktifkan.';
        }

        return implode("\n", $lines);
    }

    /**
     * category => [product name, price, stock]
     * stock: a number, [variant => qty], or [colour => [size => qty]].
     * Price 0 = not in the price list yet.
     */
    private function rows(): array
    {
        return [
            'Kaos Dewasa' => [
                ['Kaos 30s Dewasa', 75000, [
                    'Hitam' => ['M' => 2, 'L' => 3, 'XL' => 2],
                    'Putih' => ['L' => 3, 'XL' => 3],
                ]],
                ['Kaos 24s Dewasa', 85000, [
                    'Biru Muda'    => ['M' => 7, 'L' => 6],
                    'Hijau Toska'  => ['XL' => 1, 'XXL' => 1],
                    'Hijau Stabilo' => ['M' => 2, 'XL' => 3],
                    'Hijau Mint'   => ['L' => 1, 'XL' => 1],
                    'Hijau Sage'   => ['M' => 1, 'XL' => 2, 'XXL' => 1],
                    'Hijau Fuji'   => ['L' => 1, 'XL' => 2, 'XXL' => 1],
                    'Pink Muda'    => ['L' => 2, 'XL' => 1, 'XXL' => 1],
                    'Ungu'         => ['M' => 2, 'L' => 1, 'XL' => 1],
                    'Turkish'      => ['XL' => 2],
                    'Biru Benhur'  => ['L' => 1, 'XL' => 2, 'XXL' => 1],
                    'Merah Cabe'   => ['L' => 2, 'XL' => 3],
                    'Turkish Muda' => ['XL' => 1, 'XXL' => 1],
                ]],
                ['Kaos Boxy Dewasa', 85000, [
                    'Hitam' => ['M' => 2, 'L' => 4, 'XL' => 4],
                    'Putih' => ['M' => 5, 'L' => 5, 'XL' => 5],
                ]],
                ['Kaos Polos Kerah V', 35000, 12],
                ['Kaos + Kantong', 0, [
                    'Hijau Mint' => ['L' => 1],
                    'Hijau Sage' => ['L' => 1],
                    'Hitam'      => ['XL' => 1],
                    'Coksu'      => ['XL' => 1],
                ]],
                ['Kaos Polos Pajangan', 0, 24],
                ['Kaos 20s Dewasa', 100000, 0],
                ['Kaos 16s', 150000, 0],
                ['Kaos Jakarta Dewasa', 70000, 0],
            ],
            'Kaos Sablon' => [
                ['Kaos Sablon Yesus', 0, 17],
                ['Kaos Sablon Palembangan', 0, [
                    'Semi Cotton'  => 8,
                    'Kaos 20s'     => 9,
                    'Kaos Kerah V' => 8,
                    'Pajangan'     => 4,
                ]],
            ],
            'Kaos Anak-anak' => [
                ['Kaos Anak-anak', 60000, [
                    'Hitam'        => ['XS' => 3, 'S' => 3, 'L' => 3, 'XL' => 3, 'XXL' => 3],
                    'Turkish'      => ['M' => 3, 'L' => 3, 'XL' => 3, 'XXL' => 3],
                    'Turkish Muda' => ['XS' => 3, 'S' => 3],
                    'Coksu'        => ['S' => 3, 'M' => 3, 'L' => 3, 'XL' => 3, 'XXL' => 3],
                    'Putih'        => ['S' => 3, 'M' => 3, 'L' => 3, 'XL' => 3, 'XXL' => 3],
                    'Pink Fanta'   => ['XS' => 3],
                    'Hijau Mint'   => ['XS' => 3],
                    'Pink Muda'    => ['XS' => 1],
                    'Sablon Anak'  => ['-' => 1],
                ]],
            ],
            'Kaos Berkerah' => [
                ['Kaos Berkerah PE', 75000, [
                    'Biru Navy'  => ['S' => 2, 'M' => 2, 'L' => 2, 'XL' => 3],
                    'Hitam'      => ['S' => 2, 'M' => 1, 'L' => 2, 'XL' => 2],
                    'Merah Cabe' => ['S' => 1, 'M' => 3, 'L' => 2, 'XL' => 2],
                ]],
                ['Kaos Berkerah Standar (Lakos Biasa)', 110000, [
                    'Hitam'        => ['XL' => 1],
                    'Merah Cabe'   => ['XL' => 2],
                    'Biru Benhur'  => ['XL' => 1],
                    'Merah Maroon' => ['XL' => 1],
                    'Putih'        => ['XL' => 1],
                    'Kuning'       => ['M' => 1, 'XL' => 1, 'XXL' => 1],
                    'Biru Kerah Hitam' => ['L' => 2],
                ]],
                ['Lakos Super', 200000, 0],
            ],
            'Jaket' => [
                ['Jaket Sweater + Topi', 250000, [
                    'Navy'      => ['L' => 2, 'XL' => 3, 'XXL' => 1],
                    'Biru Muda' => ['XL' => 1, 'XXL' => 1],
                    'Hitam'     => ['M' => 1, 'L' => 2],
                    'Abu-abu'   => ['XL' => 1, 'XXL' => 1],
                ]],
                ['Jaket Sweater Tanpa Topi', 250000, [
                    'Hitam' => ['S' => 1, 'M' => 1],
                ]],
            ],
            'Topi' => [
                ['Topi', 60000, [
                    'Shot Bag Campur'  => 9,
                    'Rimba Campur'     => 10,
                    'Jaring Pink'      => 9,
                    'Jaring Hitam'     => 10,
                    'Jaring Abu-abu'   => 1,
                    'Jaring Hijau Army' => 1,
                ]],
            ],
            'Tas' => [
                ['Hand Bag Bagus', 125000, ['Lis Hijau' => 10, 'Tanpa Lis' => 10]],
                ['Hand Bag PMI', 35000, ['Merah' => 10, 'Navy' => 10, 'Hitam' => 10]],
                ['Hand Bag Hitam Bagus', 35000, 0],
                ['Dompet Navy', 0, 10],
                ['Tas Serut Warna-warni', 15000, 200],
                ['Tas Serut Putih', 25000, 0],
                ['Totebag Campur', 0, 100],
            ],
            'Tumbler' => [
                ['Tumbler Stainless', 100000, 20],
                ['Tumbler Kaca', 50000, 20],
            ],
            'Aksesoris' => [
                ['Gantungan Kunci Akrilik PESPARANI', 10000, 65],
                ['Buku Memo PESPARANI', 10000, 12],
                ['Monte-monte (1 gram)', 2000, 0],
            ],
            'Minuman & Snack' => [
                ['Minuman Rasa-rasa 25g (Es / Hot)', 20000, [
                    'Taro'       => 25,
                    'Coklat'     => 20,
                    'Thai Tea'   => 20,
                    'Green Tea'  => 19,
                    'Red Velvet' => 20,
                ]],
                ['Minuman Rasa-rasa 50g (Es / Hot)', 20000, [
                    'Taro'        => 24,
                    'Thai Tea'    => 20,
                    'Matcha'      => 15,
                    'Red Velvet'  => 11,
                    'Coklat'      => 8,
                    'Dark Coklat' => 4,
                    'Kopi'        => 4,
                    'Gula Aren'   => 34,
                ]],
                ['Popcorn', 10000, 0],
            ],
        ];
    }
}
