<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Label Barcode</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #fff; }

        .page { padding: 10mm; }

        .label-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 4mm;
        }

        .label {
            border: 1px solid #ccc;
            border-radius: 4px;
            padding: 4mm 3mm;
            text-align: center;
            page-break-inside: avoid;
            background: #fff;
        }

        .label .product-name {
            font-size: 9pt;
            font-weight: bold;
            color: #111;
            margin-bottom: 2mm;
            line-height: 1.2;
            word-break: break-word;
        }

        .label .barcode-svg {
            max-width: 100%;
            height: auto;
            display: block;
            margin: 0 auto;
        }

        .label .sku {
            font-size: 7pt;
            color: #555;
            font-family: monospace;
            margin-top: 1mm;
            letter-spacing: 0.5px;
        }

        .label .price {
            font-size: 10pt;
            font-weight: bold;
            color: #059669;
            margin-top: 2mm;
        }

        @media print {
            body { background: #fff; }
            .no-print { display: none; }
            @page { margin: 5mm; }
        }
    </style>
</head>
<body>

<div class="no-print" style="padding:10px;background:#f3f4f6;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;gap:12px;">
    <button onclick="window.print()"
            style="background:#059669;color:#fff;font-weight:bold;padding:8px 20px;border-radius:8px;border:none;cursor:pointer;font-size:14px;">
        🖨 Print Label
    </button>
    <a href="{{ route('barcodes.select') }}"
       style="color:#6b7280;font-size:13px;text-decoration:none;">← Kembali ke Pilih Produk</a>
    <span style="color:#9ca3af;font-size:12px;">{{ count($labels) }} label akan dicetak</span>
</div>

<div class="page">
    <div class="label-grid">
        @foreach($labels as $label)
        <div class="label">
            <div class="product-name">{{ $label['product']->name }}</div>
            <div class="barcode-svg">{!! $label['svg'] !!}</div>
            <div class="sku">{{ $label['sku'] }}</div>
            <div class="price">Rp {{ number_format($label['product']->price, 0, ',', '.') }}</div>
        </div>
        @endforeach
    </div>
</div>

</body>
</html>
