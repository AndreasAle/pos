{{-- Identitas merek untuk Google (Knowledge Graph / AI Overview). Membedakan Yoka POS dari
     produk lain bernama mirip (misalnya "yoKapo"). Hanya fakta yang benar dan publik. --}}
@php $brandBase = rtrim((string) config('app.url'), '/'); @endphp
<script type="application/ld+json">{!! json_encode([
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'Organization',
            '@id' => $brandBase.'/#organization',
            'name' => 'Yoka POS',
            'alternateName' => ['Yokapos', 'YokaPOS', 'Yoka'],
            'url' => $brandBase,
            'logo' => $brandBase.'/images/brand/yoka-logo.png',
            'description' => 'Yoka POS (yokapos.com) adalah aplikasi kasir berbasis web dari Indonesia untuk cafe, resto, bakery, dan UMKM, dikembangkan oleh Conwebid Indonesia Digital.',
            'parentOrganization' => ['@type' => 'Organization', 'name' => 'Conwebid Indonesia Digital'],
            'areaServed' => 'ID',
            'contactPoint' => [['@type' => 'ContactPoint', 'contactType' => 'customer support', 'telephone' => '+62-858-9111-1795', 'email' => 'support@yokapos.com', 'availableLanguage' => ['id', 'en']]],
        ],
        [
            '@type' => 'WebSite',
            '@id' => $brandBase.'/#website',
            'name' => 'Yoka POS',
            'url' => $brandBase,
            'inLanguage' => 'id-ID',
            'publisher' => ['@id' => $brandBase.'/#organization'],
        ],
        [
            '@type' => 'SoftwareApplication',
            'name' => 'Yoka POS',
            'applicationCategory' => 'BusinessApplication',
            'applicationSubCategory' => 'Point of Sale (POS)',
            'operatingSystem' => 'Web browser (Windows, macOS, Android, iOS)',
            'url' => $brandBase,
            'description' => 'Aplikasi kasir untuk cafe, resto, bakery, dan UMKM: transaksi, QRIS, stok dan resep, shift kasir, laporan profit, dan Bot Telegram.',
            'publisher' => ['@id' => $brandBase.'/#organization'],
            'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'IDR', 'description' => 'Trial gratis 7 hari'],
        ],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
