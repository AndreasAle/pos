<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>QR {{ $table->name }}</title><script src="https://cdn.tailwindcss.com"></script></head>
<body class="bg-slate-100 p-8 text-[#0B1B3F]" onload="window.print()"><main class="mx-auto flex aspect-[4/5] max-w-md flex-col items-center justify-center rounded-3xl bg-white p-10 text-center shadow-sm">
    <img src="{{ asset('images/brand/yoka-icon.png') }}" class="h-14 w-14" alt="Yoka"><p class="mt-4 text-sm font-bold uppercase tracking-[.2em] text-[#16A34A]">Pesan dari meja</p>
    <h1 class="mt-2 text-4xl font-black">{{ $table->name }}</h1><p class="mt-1 text-sm text-slate-500">{{ $table->outlet->name }} · {{ $table->area?->name }}</p>
    <img class="my-8 h-64 w-64" src="{{ route('qr-tables.qr',$table) }}" alt="QR"><p class="text-lg font-bold">Pindai untuk lihat menu</p><p class="mt-2 text-sm text-slate-500">Pilih pesanan dan bayar melalui Yoka POS.</p>
</main></body></html>
