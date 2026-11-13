@props(['type'])
@php
    $map = [
        'in'         => ['green', 'Stok masuk'],
        'out'        => ['red',   'Stok keluar'],
        'adjustment' => ['blue',  'Penyesuaian'],
        'sale'       => ['amber', 'Penjualan'],
        'return'     => ['blue',  'Retur'],
        'waste'      => ['gray',  'Terbuang'],
    ];
    [$tone, $label] = $map[$type] ?? ['gray', ucfirst((string) $type)];
@endphp
<x-pill :tone="$tone">{{ $label }}</x-pill>
