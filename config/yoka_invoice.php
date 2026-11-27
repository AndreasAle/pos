<?php

// Identitas penerbit invoice langganan. Data legal (NIB, NPWP, alamat) hanya di .env server, tidak pernah di repo publik.
return [
    'issuer_name' => env('YOKA_INVOICE_ISSUER', 'Yoka POS'),
    'signer'      => env('YOKA_INVOICE_SIGNER', 'Yoka POS'),
    'address'     => env('YOKA_INVOICE_ADDRESS'),
    'email'       => env('YOKA_INVOICE_EMAIL'),
    'nib'         => env('YOKA_INVOICE_NIB'),
    'npwp'        => env('YOKA_INVOICE_NPWP'),
];
