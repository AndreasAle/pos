@props(['status'])
@php
    $map = [
        'pending'   => ['yellow', 'Pending'],
        'draft'     => ['yellow', 'Draft'],
        'paid'      => ['green',  'Lunas'],
        'cancelled' => ['red',    'Dibatalkan'],
        'refunded'  => ['blue',   'Refund'],
    ];
    [$color, $label] = $map[$status] ?? ['gray', ucfirst((string) $status)];
@endphp
<x-badge :color="$color">{{ $label }}</x-badge>
