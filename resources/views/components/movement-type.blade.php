@props(['type'])
@php
    $map = [
        'in'         => ['green', 'Masuk'],
        'out'        => ['red',   'Keluar'],
        'adjustment' => ['blue',  'Penyesuaian'],
        'sale'       => ['amber', 'Penjualan'],
        'return'     => ['blue',  'Retur'],
        'waste'      => ['gray',  'Terbuang'],
    ];
    [$tone, $label] = $map[$type] ?? ['gray', ucfirst((string) $type)];
@endphp
<x-pill :tone="$tone">{{ $label }}</x-pill>
